<?php
/* Remove a product only when its farm belongs to the current farmer and the CSRF token is valid. */
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
requireApprovedFarmer();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Product deletion must be submitted using the form.');
}

$submittedToken = $_POST['csrf_token'] ?? null;
$sessionToken = $_SESSION['csrf_token'] ?? null;
if (
    !is_string($submittedToken)
    || !is_string($sessionToken)
    || !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit('This request could not be verified. Reload the products page and try again.');
}

$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($productId === false || $productId === null) {
    header('Location: manage-products.php?message=not_found', true, 303);
    exit;
}

$farmerId = (int) $_SESSION['farmer_id'];

try {
    $delete = $conn->prepare(
        'DELETE p FROM products AS p
         INNER JOIN farms AS f ON f.farm_id = p.farm_id
         WHERE p.product_id = ? AND f.farmer_id = ?'
    );
    $delete->bind_param('ii', $productId, $farmerId);
    $delete->execute();
    $deleted = $delete->affected_rows === 1;
    $delete->close();

    header(
        'Location: manage-products.php?message=' . ($deleted ? 'deleted' : 'not_found'),
        true,
        303
    );
    exit;
} catch (Throwable $error) {
    error_log('AgroNest product deletion failed.');
    http_response_code(500);
    exit('We could not delete this product. Please try again later.');
}
