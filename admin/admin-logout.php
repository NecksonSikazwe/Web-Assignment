<?php
/* End administrator sessions only through a CSRF-checked POST request. */
declare(strict_types=1);

require_once __DIR__ . '/admin-auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Administrator logout must be submitted using the secure form.');
}

startAdminSession();
$submittedToken = $_POST['csrf_token'] ?? null;
$sessionToken = $_SESSION['admin_csrf_token'] ?? null;
if (
    !is_string($submittedToken)
    || !is_string($sessionToken)
    || !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('This logout request could not be verified.');
}

destroyAdminSession();
header('Location: admin-login.php?logged_out=1', true, 303);
exit;
