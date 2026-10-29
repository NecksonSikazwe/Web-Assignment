<?php
/* Shared farmer session, CSRF-token, logout, and approved-account authorization helpers. */
declare(strict_types=1);

function startFarmerSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('AGRONEST_FARMER_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function farmerCsrfToken(): string
{
    startFarmerSession();

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function destroyFarmerSession(): void
{
    startFarmerSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function requireApprovedFarmer(): void
{
    startFarmerSession();

    $farmerId = $_SESSION['farmer_id'] ?? null;
    if (!is_int($farmerId) || $farmerId < 1) {
        http_response_code(401);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Please log in with an approved farmer account to continue.');
    }

    // Verify current approval in the database on every protected request, not just at login.
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        global $conn;
        require_once __DIR__ . '/database.php';
        $statement = $conn->prepare('SELECT status, full_name FROM farmers WHERE farmer_id = ? LIMIT 1');
        $statement->bind_param('i', $farmerId);
        $statement->execute();
        $statement->bind_result($status, $farmerName);
        $isApproved = $statement->fetch() && $status === 'approved';
        $statement->close();
    } catch (Throwable $error) {
        error_log('AgroNest farmer authorization check failed.');
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('We could not verify your account right now. Please try again later.');
    }

    if (!$isApproved) {
        destroyFarmerSession();
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('This farmer account is not approved to access this feature.');
    }

    $_SESSION['farmer_name'] = $farmerName;
}
