<?php
/* Authenticate farmer accounts independently from administrators and require approved status. */
declare(strict_types=1);

// Render one escaped response template for validation errors and post-login notices.
function renderLoginResponse(int $statusCode, string $title, string $message, bool $success = false, string $csrfToken = ''): never
{
    http_response_code($statusCode);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, private');

    $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $messageClass = $success ? '' : ' class="login-error"';
    $nextStep = $success
        ? '<p>Farmer-only services will be available here as they are added to AgroNest.</p>
            <form action="logout.php" method="post">
                <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">
                <button type="submit">Log Out</button>
            </form>'
        : '<p><a href="login.php">Return to farmer login</a> or <a href="../index.html">visit AgroNest home</a>.</p>';

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
        <section class="login-response" aria-labelledby="login-response-heading" role="status">
            <h1 id="login-response-heading">' . $safeTitle . '</h1>
            <p' . $messageClass . '>' . $safeMessage . '</p>
            ' . $nextStep . '
        </section>
    </main>
</body>
</html>';
    exit;
}

function renderLoginForm(string $csrfToken): never
{
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, private');
    $safeToken = htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Log in to your AgroNest farmer account.">
    <title>Farmer Login | AgroNest</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/login.css">
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
            <li><a href="login.php" aria-current="page">Farmer login</a></li>
        </ul>
    </nav>
    <main class="login-main">
        <section class="login-card" aria-labelledby="login-heading">
            <header class="login-heading">
                <p class="login-kicker">Welcome back</p>
                <h1 id="login-heading">Farmer login</h1>
                <p>Sign in to your approved AgroNest farmer account.</p>
            </header>
            <form class="login-form" action="login.php" method="post">
                <input type="hidden" name="csrf_token" value="' . $safeToken . '">
                <p class="login-field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="username"
                        maxlength="150" placeholder="you@example.com" required>
                </p>
                <p class="login-field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password"
                        autocomplete="current-password" placeholder="Enter your password" required>
                </p>
                <button class="login-submit" type="submit">Log In</button>
            </form>
            <p class="login-registration-note">
                New to AgroNest? <a href="../pages/register.html">Create a farmer account</a>.
            </p>
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
</html>';
    exit;
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
if ($requestMethod === 'GET') {
    require_once __DIR__ . '/../config/auth.php';
    renderLoginForm(farmerCsrfToken());
}

if ($requestMethod !== 'POST') {
    header('Allow: POST');
    renderLoginResponse(405, 'Method not allowed', 'Please open the login page and submit its form.');
}

require_once __DIR__ . '/../config/auth.php';
startFarmerSession();
$submittedToken = $_POST['csrf_token'] ?? null;
$sessionToken = $_SESSION['csrf_token'] ?? null;
if (
    !is_string($submittedToken)
    || !is_string($sessionToken)
    || !hash_equals($sessionToken, $submittedToken)
) {
    renderLoginResponse(403, 'Login request could not be verified', 'Please reload the login page and try again.');
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
    renderLoginResponse(401, 'Login unsuccessful', 'The email or password is incorrect, or this account is not approved.');
}

$email = trim($email);

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/../config/database.php';
    $statement = $conn->prepare(
        'SELECT farmer_id, password_hash, status FROM farmers WHERE email = ? LIMIT 1'
    );
    $statement->bind_param('s', $email);
    $statement->execute();
    $statement->bind_result($farmerId, $passwordHash, $status);
    $farmerFound = $statement->fetch();
    $statement->close();
} catch (Throwable $error) {
    error_log('AgroNest farmer login database operation failed.');
    renderLoginResponse(503, 'Login temporarily unavailable', 'We could not verify your account right now. Please try again later.');
}

if (
    !$farmerFound
    || !password_verify($submittedPassword, $passwordHash)
    || $status !== 'approved'
) {
    renderLoginResponse(401, 'Login unsuccessful', 'The email or password is incorrect, or this account is not approved.');
}

try {
    require_once __DIR__ . '/../config/auth.php';
    startFarmerSession();
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Session ID regeneration failed.');
    }

    $_SESSION = [
        'farmer_id' => (int) $farmerId,
        'status' => 'approved',
    ];
    $csrfToken = farmerCsrfToken();
} catch (Throwable $error) {
    error_log('AgroNest farmer login session could not be established.');
    renderLoginResponse(500, 'Login could not be completed', 'We could not start a secure session. Please try again.');
}

header('Location: dashboard.php', true, 303);
exit;
