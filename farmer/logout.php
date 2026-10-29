<?php
/* Accept logout through the farmer-specific CSRF-protected session and clear its cookie. */
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';

function renderLogoutResponse(int $statusCode, string $title, string $message): never
{
    http_response_code($statusCode);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, private');

    $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $safeTitle . ' | AgroNest</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/login.css">
</head>
<body class="login-page">
    <header>
        <a href="../index.html" class="logo-container" aria-label="AgroNest home">
            <img src="../images/logo.png" alt="" width="40" height="40">
            <span>AgroNest</span>
        </a>
    </header>
    <main>
        <section class="login-response" aria-labelledby="logout-heading" role="status">
            <h1 id="logout-heading">' . $safeTitle . '</h1>
            <p>' . $safeMessage . '</p>
            <p><a href="../pages/login.html">Farmer login</a> or <a href="../index.html">AgroNest home</a>.</p>
        </section>
    </main>
</body>
</html>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    renderLogoutResponse(405, 'Method not allowed', 'Logout must be submitted using the secure form.');
}

startFarmerSession();
$submittedToken = $_POST['csrf_token'] ?? null;
$sessionToken = $_SESSION['csrf_token'] ?? null;
if (
    !is_string($submittedToken)
    || !is_string($sessionToken)
    || !hash_equals($sessionToken, $submittedToken)
) {
    renderLogoutResponse(403, 'Logout could not be verified', 'Please return to your account page and try again.');
}

destroyFarmerSession();
renderLogoutResponse(200, 'You are logged out', 'Your farmer session has been ended.');
