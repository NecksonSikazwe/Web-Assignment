<?php
/* Protected administrator home page with database-derived registration totals and review links. */
declare(strict_types=1);

require_once __DIR__ . '/admin-auth.php';
requireAdministrator();

$adminEmail = htmlspecialchars(
    (string) ($_SESSION['admin_email'] ?? ''),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$csrfToken = htmlspecialchars(adminCsrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$summary = null;
$loadError = false;

// Aggregate all four statuses in one query so dashboard cards share a consistent snapshot.
try {
    $counts = $conn->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status = 'pending'), 0) AS pending,
                COALESCE(SUM(status = 'approved'), 0) AS approved,
                COALESCE(SUM(status = 'rejected'), 0) AS rejected
         FROM farmers"
    );
    $counts->execute();
    $counts->bind_result($totalFarmers, $pendingFarmers, $approvedFarmers, $rejectedFarmers);
    if (!$counts->fetch()) {
        throw new RuntimeException('Farmer registration totals were not returned.');
    }
    $counts->close();

    $summary = [
        'total' => (int) $totalFarmers,
        'pending' => (int) $pendingFarmers,
        'approved' => (int) $approvedFarmers,
        'rejected' => (int) $rejectedFarmers,
    ];
} catch (Throwable $error) {
    error_log('AgroNest administrator dashboard totals could not be loaded.');
    http_response_code(503);
    $loadError = true;
}

header('Cache-Control: no-store, private');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Administrator dashboard for AgroNest farmer registrations.">
    <title>Administrator Dashboard | AgroNest</title>
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
            <li><a href="admin-dashboard.php" aria-current="page">Dashboard</a></li>
            <li><a href="admin-farmers.php">Farmer registrations</a></li>
            <li>
                <form action="admin-logout.php" method="post" class="dashboard-logout">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <button type="submit">Logout</button>
                </form>
            </li>
        </ul>
    </nav>

    <main class="admin-main">
        <section class="dashboard-welcome" aria-labelledby="dashboard-heading">
            <p class="dashboard-kicker">Administrator tools</p>
            <h1 id="dashboard-heading">Welcome to the AgroNest dashboard</h1>
            <p>Review farmer registrations and monitor account approval status.</p>
        </section>

        <?php if ($loadError): ?>
            <p class="admin-message admin-message-error" role="alert">
                We could not load registration totals right now. Please try again later.
            </p>
        <?php else: ?>
            <section class="admin-summary-grid" aria-label="Farmer registration summary">
                <article class="admin-summary-card">
                    <p class="admin-summary-label">Registered farmers</p>
                    <p class="admin-summary-count"><?= $summary['total'] ?></p>
                    <p class="admin-summary-note">All farmer accounts</p>
                </article>
                <article class="admin-summary-card admin-summary-card-pending">
                    <p class="admin-summary-label">Pending review</p>
                    <p class="admin-summary-count"><?= $summary['pending'] ?></p>
                    <p class="admin-summary-note"><span class="admin-status-dot"></span>Awaiting a decision</p>
                </article>
                <article class="admin-summary-card admin-summary-card-approved">
                    <p class="admin-summary-label">Approved</p>
                    <p class="admin-summary-count"><?= $summary['approved'] ?></p>
                    <p class="admin-summary-note"><span class="admin-status-dot"></span>Can access farmer tools</p>
                </article>
                <article class="admin-summary-card admin-summary-card-rejected">
                    <p class="admin-summary-label">Rejected</p>
                    <p class="admin-summary-count"><?= $summary['rejected'] ?></p>
                    <p class="admin-summary-note"><span class="admin-status-dot"></span>Not approved for access</p>
                </article>
            </section>

            <section class="admin-dashboard-action" aria-labelledby="review-heading">
                <header>
                    <h2 id="review-heading">Farmer registrations</h2>
                    <p>
                        <?php if ($summary['pending'] > 0): ?>
                            <?= $summary['pending'] ?> registration<?= $summary['pending'] === 1 ? '' : 's' ?>
                            <?= $summary['pending'] === 1 ? 'is' : 'are' ?> waiting for review.
                        <?php else: ?>
                            There are no registrations waiting for review.
                        <?php endif; ?>
                    </p>
                </header>
                <a class="admin-dashboard-link" href="admin-farmers.php">Review registrations</a>
            </section>
        <?php endif; ?>
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
                <li><a href="../marketplace.php">Marketplace</a></li>
                <li><a href="../pages/contact.html">Contact Us</a></li>
            </ul>
        </nav>
        <section class="footer-col">
            <h3>Administrator</h3>
            <ul>
                <li><a href="admin-dashboard.php">Dashboard</a></li>
                <li><a href="admin-farmers.php">Farmer registrations</a></li>
            </ul>
        </section>
    </footer>
</body>

</html>
