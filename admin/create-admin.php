<?php
/* CLI-only administrator bootstrap; reads passwords without terminal echo and stores only a hash. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

if (!function_exists('exec') || !function_exists('shell_exec') || !stream_isatty(STDIN)) {
    fwrite(STDERR, "Run this setup command in an interactive terminal.\n");
    exit(1);
}

$terminalMode = trim((string) shell_exec('stty -g 2>/dev/null'));
if ($terminalMode === '' || preg_match('/\A[0-9a-fA-F:;]+\z/', $terminalMode) !== 1) {
    fwrite(STDERR, "Could not safely read terminal settings.\n");
    exit(1);
}

function readHiddenPassword(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    exec('stty -echo 2>/dev/null', $output, $status);
    if ($status !== 0) {
        fwrite(STDOUT, PHP_EOL);
        throw new RuntimeException('Could not disable terminal password echo.');
    }

    try {
        $password = fgets(STDIN);
        if ($password === false) {
            throw new RuntimeException('Could not read the administrator password.');
        }
    } finally {
        exec('stty echo 2>/dev/null', $output, $restoreStatus);
        fwrite(STDOUT, PHP_EOL);
        if ($restoreStatus !== 0) {
            throw new RuntimeException('Could not restore terminal settings.');
        }
    }

    return rtrim($password, "\r\n");
}

fwrite(STDOUT, 'Administrator email: ');
$emailInput = fgets(STDIN);
$email = trim($emailInput === false ? '' : $emailInput);
if (
    $email === ''
    || strlen($email) > 150
    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
) {
    fwrite(STDERR, "Enter a valid administrator email address of 150 characters or fewer.\n");
    exit(1);
}

try {
    $adminPassword = readHiddenPassword('Password (12 to 72 bytes): ');
    $confirmation = readHiddenPassword('Confirm password: ');
} catch (Throwable $error) {
    fwrite(STDERR, "Could not read the administrator password securely.\n");
    exit(1);
}

if (strlen($adminPassword) < 12 || strlen($adminPassword) > 72 || $adminPassword !== $confirmation) {
    fwrite(STDERR, "Passwords must match and contain 12 to 72 bytes.\n");
    exit(1);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/../config/database.php';
    $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new RuntimeException('Password hashing failed.');
    }

    $insert = $conn->prepare(
        'INSERT INTO administrators (email, password_hash, is_active) VALUES (?, ?, 1)'
    );
    $insert->bind_param('ss', $email, $passwordHash);
    $insert->execute();
    $insert->close();
    fwrite(STDOUT, "Administrator account created. Keep the password secure.\n");
} catch (mysqli_sql_exception $error) {
    if ((int) $error->getCode() === 1062) {
        fwrite(STDERR, "An administrator already uses that email address.\n");
        exit(1);
    }

    error_log('AgroNest administrator setup failed.');
    fwrite(STDERR, "Could not create the administrator account. Check the database service and table setup.\n");
    exit(1);
} catch (Throwable $error) {
    error_log('AgroNest administrator setup failed.');
    fwrite(STDERR, "Could not create the administrator account.\n");
    exit(1);
}
