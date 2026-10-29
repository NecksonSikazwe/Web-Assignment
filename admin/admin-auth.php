<?php
/* Keep administrator sessions, CSRF tokens, and database-backed authorization separate from farmer authentication. */
declare(strict_types=1);

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('AGRONEST_ADMIN_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function adminCsrfToken(): string
{
    startAdminSession();

    if (!isset($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function destroyAdminSession(): void
{
    startAdminSession();
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

function requireAdministrator(): void
{
    startAdminSession();

    $adminId = $_SESSION['admin_id'] ?? null;
    if (!is_int($adminId) || $adminId < 1) {
        header('Location: admin-login.php', true, 302);
        exit;
    }

    // Re-check the active administrator record so deactivated accounts lose access immediately.
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        global $conn;
        require_once __DIR__ . '/../config/database.php';
        $statement = $conn->prepare(
            'SELECT email FROM administrators WHERE admin_id = ? AND is_active = 1 LIMIT 1'
        );
        $statement->bind_param('i', $adminId);
        $statement->execute();
        $statement->bind_result($email);
        $isActive = $statement->fetch();
        $statement->close();
    } catch (Throwable $error) {
        error_log('AgroNest administrator authorization check failed.');
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('We could not verify administrator access right now. Please try again later.');
    }

    if (!$isActive) {
        destroyAdminSession();
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('This administrator account is not active.');
    }

    $_SESSION['admin_email'] = $email;
}
