<?php
/* Validate a new farmer application, reject duplicate email addresses, and store a password hash. */
declare(strict_types=1);

// Keep validation responses escaped and consistent while reporting the correct HTTP status.
function renderResponse(int $statusCode, string $title, array $messages, bool $success = false): never
{
    http_response_code($statusCode);
    header('Content-Type: text/html; charset=UTF-8');

    $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $headingClass = $success ? '' : ' class="response-error"';
    $safeMessages = array_map(
        static fn (string $message): string => htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        $messages
    );
    $list = '';
    if (count($safeMessages) > 1) {
        $list = '<ul>' . implode('', array_map(
            static fn (string $message): string => '<li>' . $message . '</li>',
            $safeMessages
        )) . '</ul>';
        $messageContent = $list;
    } else {
        $messageContent = '<p' . $headingClass . '>' . ($safeMessages[0] ?? '') . '</p>';
    }

    $statusHeadingClass = $success ? '' : ' class="response-error"';
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $safeTitle . ' | AgroNest</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/register.css">
</head>
<body class="registration-page">
    <header>
        <a href="../index.html" class="logo-container" aria-label="AgroNest home">
            <img src="../images/logo.png" alt="" width="40" height="40">
            <span>AgroNest</span>
        </a>
    </header>
    <main>
        <section class="registration-response" aria-labelledby="response-heading" role="status">
            <h1 id="response-heading"' . $statusHeadingClass . '>' . $safeTitle . '</h1>
            ' . $messageContent . '
            <nav class="response-actions" aria-label="Next steps">
                <a href="../pages/register.html">Back to registration</a>
                <a href="../index.html">AgroNest home</a>
            </nav>
        </section>
    </main>
</body>
</html>';
    exit;
}

function textLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    renderResponse(405, 'Method not allowed', ['Please submit registration using the form.']);
}

$requiredFields = ['full_name', 'email', 'phone', 'password', 'confirm_password'];
$missingFields = [];
$input = [];
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || !is_string($_POST[$field])) {
        $missingFields[] = $field;
    } else {
        $input[$field] = $_POST[$field];
    }
}

if ($missingFields !== []) {
    renderResponse(422, 'Please check your information', ['Complete all required fields and try again.']);
}

$fullName = trim($input['full_name']);
$email = trim($input['email']);
$phone = trim($input['phone']);
$submittedPassword = $input['password'];
$confirmation = $input['confirm_password'];
$errors = [];

if ($fullName === '' || textLength($fullName) > 100) {
    $errors[] = 'Enter your full name using no more than 100 characters.';
}

if (
    $email === ''
    || textLength($email) > 150
    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
) {
    $errors[] = 'Enter a valid email address (up to 150 characters).';
}

$digitCount = preg_match_all('/[0-9]/', $phone);
if (
    $phone === ''
    || textLength($phone) > 20
    || preg_match('/\A\+?[0-9][0-9\s().-]*\z/u', $phone) !== 1
    || $digitCount === false
    || $digitCount < 7
    || $digitCount > 15
) {
    $errors[] = 'Enter a valid phone number with 7 to 15 digits; spaces, +, parentheses, periods and hyphens are allowed.';
}

if (textLength($submittedPassword) < 12 || strlen($submittedPassword) > 72) {
    $errors[] = 'Choose a password with at least 12 characters and no more than 72 bytes.';
}

if ($submittedPassword !== $confirmation) {
    $errors[] = 'The password and confirmation do not match.';
}

if ($errors !== []) {
    renderResponse(422, 'Please check your information', $errors);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/../config/database.php';
} catch (Throwable $error) {
    error_log('AgroNest registration could not load the database connection.');
    renderResponse(503, 'Registration is temporarily unavailable', [
        'We could not connect to the registration service. Please try again later.'
    ]);
}

try {
    $check = $conn->prepare('SELECT farmer_id FROM farmers WHERE email = ? LIMIT 1');
    $check->bind_param('s', $email);
    $check->execute();
    $check->store_result();
    $emailExists = $check->num_rows > 0;
    $check->close();

    if ($emailExists) {
        renderResponse(409, 'Email already registered', [
            'An account already uses this email address. Please use another email address.'
        ]);
    }

    $passwordHash = password_hash($submittedPassword, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new RuntimeException('Password hashing failed.');
    }

    $insert = $conn->prepare(
        "INSERT INTO farmers (full_name, email, phone, password_hash, status)
         VALUES (?, ?, ?, ?, 'pending')"
    );
    $insert->bind_param('ssss', $fullName, $email, $phone, $passwordHash);
    $insert->execute();
    $insert->close();

    renderResponse(201, 'Registration received', [
        'Your farmer account has been created and is pending approval.'
    ], true);
} catch (mysqli_sql_exception $error) {
    if ((int) $error->getCode() === 1062) {
        renderResponse(409, 'Email already registered', [
            'An account already uses this email address. Please use another email address.'
        ]);
    }

    error_log('AgroNest registration database operation failed (MySQL error ' . (int) $error->getCode() . ').');
    renderResponse(500, 'Registration could not be completed', [
        'We could not save your registration. Please try again later.'
    ]);
} catch (Throwable $error) {
    error_log('AgroNest registration failed (' . get_class($error) . ').');
    renderResponse(500, 'Registration could not be completed', [
        'We could not save your registration. Please try again later.'
    ]);
}
