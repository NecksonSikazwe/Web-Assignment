<?php
/* Admin-only pending farmer queue; decisions require CSRF validation and a prepared status update. */
declare(strict_types=1);

require_once __DIR__ . '/admin-auth.php';
requireAdministrator();

$message = '';
$messageType = 'success';

// A POST can change only a still-pending record; the GET path only loads the review queue.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;
    $sessionToken = $_SESSION['admin_csrf_token'] ?? null;
    if (
        !is_string($submittedToken)
        || !is_string($sessionToken)
        || !hash_equals($sessionToken, $submittedToken)
    ) {
        http_response_code(403);
        $message = 'This request could not be verified. Reload the page and try again.';
        $messageType = 'error';
    } else {
        $farmerId = filter_var(
            $_POST['farmer_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $decision = $_POST['decision'] ?? null;

        if ($farmerId === false || $farmerId === null || !in_array($decision, ['approve', 'reject'], true)) {
            http_response_code(422);
            $message = 'Choose a valid pending farmer and review action.';
            $messageType = 'error';
        } else {
            try {
                $newStatus = $decision === 'approve' ? 'approved' : 'rejected';
                $update = $conn->prepare(
                    'UPDATE farmers SET status = ? WHERE farmer_id = ? AND status = ?'
                );
                $pending = 'pending';
                $update->bind_param('sis', $newStatus, $farmerId, $pending);
                $update->execute();
                $changed = $update->affected_rows === 1;
                $update->close();

                if ($changed) {
                    $message = $decision === 'approve'
                        ? 'The farmer account has been approved.'
                        : 'The farmer registration has been rejected.';
                } else {
                    http_response_code(409);
                    $message = 'That farmer is no longer pending or could not be found.';
                    $messageType = 'error';
                }
            } catch (Throwable $error) {
                error_log('AgroNest administrator farmer review action failed.');
                http_response_code(500);
                $message = 'We could not save this decision. Please try again later.';
                $messageType = 'error';
            }
        }
    }
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    $message = 'This request method is not supported.';
    $messageType = 'error';
}

try {
    $result = $conn->query(
        "SELECT farmer_id, full_name, email, phone, created_at
         FROM farmers
         WHERE status = 'pending'
         ORDER BY created_at ASC, farmer_id ASC"
    );
    $pendingFarmers = [];
    while ($farmer = $result->fetch_assoc()) {
        $pendingFarmers[] = $farmer;
    }
} catch (Throwable $error) {
    error_log('AgroNest pending farmer registrations could not be loaded.');
    http_response_code(503);
    $pendingFarmers = [];
    $message = 'We could not load pending registrations right now.';
    $messageType = 'error';
}

function escapeAdminValue(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$csrfToken = escapeAdminValue(adminCsrfToken());
$adminEmail = escapeAdminValue($_SESSION['admin_email'] ?? '');
header('Cache-Control: no-store, private');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Review pending AgroNest farmer registrations.">
    <title>Pending Farmer Registrations | AgroNest</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="farmer-dashboard">
    <header>
        <a href="../index.html" class="logo-container" aria-label="AgroNest home">
            <img src="../images/logo.png" alt="" width="40" height="40">
            <span>AgroNest</span>
        </a>
        <span class="dashboard-account">Administrator: <?= $adminEmail ?></span>
    </header>
    <nav aria-label="Administrator navigation">
        <ul>
            <li><a href="admin-dashboard.php">Dashboard</a></li>
            <li><a href="admin-farmers.php" aria-current="page">Pending Farmers</a></li>
            <li>
                <form action="admin-logout.php" method="post" class="dashboard-logout">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <button type="submit">Logout</button>
                </form>
            </li>
        </ul>
    </nav>
    <main class="admin-main">
        <section class="dashboard-welcome" aria-labelledby="pending-heading">
            <p class="dashboard-kicker">Administrator tools</p>
            <h1 id="pending-heading">Pending farmer registrations</h1>
            <p>Review each registration and approve or reject it. Only pending accounts are actionable.</p>
        </section>
        <?php if ($message !== ''): ?>
            <p class="admin-message <?= $messageType === 'error' ? 'admin-message-error' : '' ?>" role="status">
                <?= escapeAdminValue($message) ?>
            </p>
        <?php endif; ?>
        <?php if ($pendingFarmers === []): ?>
            <p class="admin-empty" role="status">There are no pending farmer registrations.</p>
        <?php else: ?>
            <section class="admin-table-wrap" aria-label="Pending farmer registration records">
                <table class="admin-farmer-table">
                    <caption>Farmers awaiting account review</caption>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Registered</th>
                            <th scope="col">Decision</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingFarmers as $farmer): ?>
                            <tr>
                                <td><?= escapeAdminValue($farmer['full_name']) ?></td>
                                <td><a href="mailto:<?= escapeAdminValue($farmer['email']) ?>"><?= escapeAdminValue($farmer['email']) ?></a></td>
                                <td><?= escapeAdminValue($farmer['phone']) ?></td>
                                <td><?= escapeAdminValue($farmer['created_at']) ?></td>
                                <td>
                                    <ul class="admin-review-actions" aria-label="Review this registration">
                                        <?php foreach (['approve' => 'Approve', 'reject' => 'Reject'] as $decision => $label): ?>
                                            <li>
                                                <form action="admin-farmers.php" method="post">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="farmer_id" value="<?= (int) $farmer['farmer_id'] ?>">
                                                    <input type="hidden" name="decision" value="<?= $decision ?>">
                                                    <button class="admin-decision admin-decision-<?= $decision ?>" type="submit">
                                                        <?= $label ?>
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>
    </main>
    <footer>
        <section class="footer-col">
            <h3>AgroNest</h3>
            <p>Growing Food. Building Futures.</p>
            <p>Integrated farming and practical agricultural support in Ndola, Zambia.</p>
        </section>
        <section class="footer-col">
            <h3>Administrator access</h3>
            <p>This area is restricted to active administrator accounts.</p>
        </section>
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
