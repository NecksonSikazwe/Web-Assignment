<?php
/* Render and process the separate administrator sign-in form without sharing farmer auth state. */
declare(strict_types=1);

require_once __DIR__ . '/admin-auth.php';

// Escape every value inserted into the reusable HTML response.
function renderAdminLogin(string $error = '', string $csrfToken = ''): never
{
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, private');
    $safeError = htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeToken = htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="Secure AgroNest administrator sign in.">
        <title>Administrator Login | AgroNest</title>
        <link rel="stylesheet" href="../css/global.css">
        <link rel="stylesheet" href="../css/login.css">
        <link rel="stylesheet" href="../css/admin.css">
    </head>
    <body class="login-page">
        <header>
            <a href="../index.html" class="logo-container" aria-label="AgroNest home">
                <img src="../images/logo.png" alt="" width="40" height="40">
                <span>AgroNest</span>
            </a>
            <nav class="header-actions" aria-label="Quick actions">
                <a href="../pages/products-services.html">Explore our farm</a>
            </nav>
        </header>
        <nav aria-label="Main Navigation">
            <ul>
                <li><a href="../index.html">Home</a></li>
                <li><a href="../pages/about.html">About Us</a></li>
                <li><a href="../marketplace.php">Marketplace</a></li>
                <li><a href="../pages/products-services.html">Products &amp; Services</a></li>
                <li><a href="../pages/farm-resources.html">Farm &amp; Resources</a></li>
                <li><a href="../pages/contact.html">Contact Us</a></li>
                <li><a href="../farmer/login.php">Farmer login</a></li>
            </ul>
        </nav>
        <main class="login-main">
            <section class="login-card" aria-labelledby="admin-login-heading">
                <header class="login-heading">
                    <p class="login-kicker">Restricted access</p>
                    <h1 id="admin-login-heading">Administrator login</h1>
                    <p>Sign in to review pending farmer registrations.</p>
                </header>
                <?php if ($safeError !== ''): ?>
                    <p class="admin-message admin-message-error" role="alert"><?= $safeError ?></p>
                <?php endif; ?>
                <form class="login-form" action="admin-login.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $safeToken ?>">
                    <p class="login-field">
                        <label for="admin-email">Email address</label>
                        <input id="admin-email" name="email" type="email" autocomplete="username"
                            maxlength="150" required>
                    </p>
                    <p class="login-field">
                        <label for="admin-password">Password</label>
                        <input id="admin-password" name="password" type="password"
                            autocomplete="current-password" required>
                    </p>
                    <button class="login-submit" type="submit">Sign in</button>
                </form>
                <p class="admin-login-note"><a href="../index.html">Return to AgroNest</a></p>
            </section>
        </main>
        <footer>
            <section class="footer-col">
                <h3>AgroNest</h3>
                <p>Growing Food. Building Futures.</p>
                <p>Integrated farming and practical agricultural support in Ndola, Zambia.</p>
            </section>
            <nav class="footer-col" aria-label="Explore AgroNest">
                <h3>Explore</h3>
                <ul>
                    <li><a href="../index.html">Home</a></li>
                    <li><a href="../pages/about.html">About Us</a></li>
                    <li><a href="../pages/products-services.html">Products &amp; Services</a></li>
                    <li><a href="../pages/farm-resources.html">Farm &amp; Resources</a></li>
                    <li><a href="../pages/contact.html">Contact Us</a></li>
                </ul>
            </nav>
            <section class="footer-col">
                <h3>Contact</h3>
                <address>
                    <p><a href="tel:+260970000000">+260 970 000 000</a></p>
                    <p><a href="mailto:info@agronest.com">info@agronest.com</a></p>
                    <p>Ndola, Copperbelt Province, Zambia</p>
                </address>
            </section>
        </footer>
    </body>
    </html>
    <?php
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'GET') {
    startAdminSession();
    if (isset($_SESSION['admin_id'])) {
        header('Location: admin-dashboard.php', true, 303);
        exit;
    }
    renderAdminLogin('', adminCsrfToken());
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    renderAdminLogin('This request method is not supported.');
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
    renderAdminLogin('This sign-in request could not be verified.', adminCsrfToken());
}

$email = $_POST['email'] ?? null;
$submittedPassword = $_POST['password'] ?? null;
if (
    !is_string($email)
    || !is_string($submittedPassword)
    || strlen($email) > 150
    || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false
    || $submittedPassword === ''
) {
    http_response_code(401);
    renderAdminLogin('The email or password is incorrect, or this account is inactive.', adminCsrfToken());
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/../config/database.php';
    $statement = $conn->prepare(
        'SELECT admin_id, password_hash FROM administrators
         WHERE email = ? AND is_active = 1 LIMIT 1'
    );
    $email = trim($email);
    $statement->bind_param('s', $email);
    $statement->execute();
    $statement->bind_result($adminId, $passwordHash);
    $adminFound = $statement->fetch();
    $statement->close();
} catch (Throwable $error) {
    error_log('AgroNest administrator login database operation failed.');
    http_response_code(503);
    renderAdminLogin('Administrator sign in is temporarily unavailable.');
}

if (!$adminFound || !password_verify($submittedPassword, $passwordHash)) {
    http_response_code(401);
    renderAdminLogin('The email or password is incorrect, or this account is inactive.', adminCsrfToken());
}

if (!session_regenerate_id(true)) {
    error_log('AgroNest administrator session ID regeneration failed.');
    http_response_code(500);
    renderAdminLogin('We could not start a secure administrator session.');
}

$_SESSION = [
    'admin_id' => (int) $adminId,
    'admin_csrf_token' => bin2hex(random_bytes(32)),
];
header('Location: admin-dashboard.php', true, 303);
exit;
