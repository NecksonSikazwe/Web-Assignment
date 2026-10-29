<?php
/* Approved farmers land here to navigate to their farm and product management tools. */
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
requireApprovedFarmer();

$farmerName = htmlspecialchars(
    (string) ($_SESSION['farmer_name'] ?? 'Farmer'),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$csrfToken = htmlspecialchars(farmerCsrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Cache-Control: no-store, private');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Your AgroNest farmer dashboard.">
    <title>Farmer Dashboard | AgroNest</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/dashboard.css">
</head>

<body class="farmer-dashboard">
    <header>
        <a href="../index.html" class="logo-container" aria-label="AgroNest home">
            <img src="../images/logo.png" alt="" width="40" height="40">
            <span>AgroNest</span>
        </a>
        <span class="dashboard-account">Signed in as <?= $farmerName ?></span>
    </header>

    <nav aria-label="Farmer dashboard navigation">
        <ul>
            <li><a href="dashboard.php" aria-current="page">Dashboard</a></li>
            <li><a href="manage-farms.php">Manage Farms</a></li>
            <li><a href="manage-products.php">Manage Products</a></li>
            <li>
                <form action="logout.php" method="post" class="dashboard-logout">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <button type="submit">Logout</button>
                </form>
            </li>
        </ul>
    </nav>

    <main class="dashboard-main">
        <section class="dashboard-welcome" aria-labelledby="dashboard-heading">
            <p class="dashboard-kicker">Farmer dashboard</p>
            <h1 id="dashboard-heading">Welcome, <?= $farmerName ?></h1>
            <p>Your AgroNest account is approved. Use the links below to manage your farm presence.</p>
        </section>

        <section class="dashboard-links" aria-label="Farmer tools">
            <a class="dashboard-tool-card" href="manage-farms.php">
                <h2>Manage Farms</h2>
                <p>Farm management tools will be available here in a future update.</p>
                <span>Open farm management &rarr;</span>
            </a>
            <a class="dashboard-tool-card" href="manage-products.php">
                <h2>Manage Products</h2>
                <p>Product management tools will be available here in a future update.</p>
                <span>Open product management &rarr;</span>
            </a>
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
