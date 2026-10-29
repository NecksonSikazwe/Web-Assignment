<?php
/* Delete a farm only after farmer authorization, CSRF validation, and an owner-scoped lookup. */
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
requireApprovedFarmer();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Farm deletion must be submitted using the form.');
}

$submittedToken = $_POST['csrf_token'] ?? null;
$sessionToken = $_SESSION['csrf_token'] ?? null;
if (
    !is_string($submittedToken)
    || !is_string($sessionToken)
    || !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit('This request could not be verified. Reload the farms page and try again.');
}

$farmId = filter_var($_POST['farm_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($farmId === false || $farmId === null) {
    header('Location: manage-farms.php?not_found=1', true, 303);
    exit;
}

$farmerId = (int) $_SESSION['farmer_id'];

try {
    $find = $conn->prepare(
        'SELECT farm_id FROM farms WHERE farm_id = ? AND farmer_id = ? LIMIT 1'
    );
    $find->bind_param('ii', $farmId, $farmerId);
    $find->execute();
    $find->store_result();
    $farmExists = $find->num_rows === 1;
    $find->close();

    if (!$farmExists) {
        header('Location: manage-farms.php?not_found=1', true, 303);
        exit;
    }

    $products = $conn->prepare('SELECT product_id FROM products WHERE farm_id = ? LIMIT 1');
    $products->bind_param('i', $farmId);
    $products->execute();
    $products->store_result();
    $hasProducts = $products->num_rows > 0;
    $products->close();

    if ($hasProducts) {
        header('Location: manage-farms.php?delete_blocked=1', true, 303);
        exit;
    }

    $delete = $conn->prepare('DELETE FROM farms WHERE farm_id = ? AND farmer_id = ?');
    $delete->bind_param('ii', $farmId, $farmerId);
    $delete->execute();
    $deleted = $delete->affected_rows === 1;
    $delete->close();

    header(
        'Location: manage-farms.php?' . ($deleted ? 'deleted=1' : 'not_found=1'),
        true,
        303
    );
    exit;
} catch (mysqli_sql_exception $error) {
    if ((int) $error->getCode() === 1451) {
        header('Location: manage-farms.php?delete_blocked=1', true, 303);
        exit;
    }

    error_log('AgroNest farm deletion failed (MySQL error ' . (int) $error->getCode() . ').');
    http_response_code(500);
    exit('We could not delete this farm. Please try again later.');
} catch (Throwable $error) {
    error_log('AgroNest farm deletion failed.');
    http_response_code(500);
    exit('We could not delete this farm. Please try again later.');
}
