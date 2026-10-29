<?php
/* Create and list farms for the current approved farmer; database writes are owner-scoped. */
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
requireApprovedFarmer();

$farmerId = (int) $_SESSION['farmer_id'];
$farmerName = htmlspecialchars(
    (string) ($_SESSION['farmer_name'] ?? 'Farmer'),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$csrfToken = htmlspecialchars(farmerCsrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$errors = [];
$formValues = [
    'farm_name' => '',
    'location' => '',
    'description' => '',
];
$notice = '';
$noticeType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    if (
        !is_string($submittedToken)
        || !is_string($sessionToken)
        || !hash_equals($sessionToken, $submittedToken)
    ) {
        http_response_code(403);
        $notice = 'This request could not be verified. Reload the page and try again.';
        $noticeType = 'error';
    } else {
        foreach (array_keys($formValues) as $field) {
            $value = $_POST[$field] ?? '';
            $formValues[$field] = is_string($value) ? trim($value) : '';
        }

        if ($formValues['farm_name'] === '' || strlen($formValues['farm_name']) > 150) {
            $errors[] = 'Enter a farm name using no more than 150 characters.';
        }
        if ($formValues['location'] === '' || strlen($formValues['location']) > 150) {
            $errors[] = 'Enter a location using no more than 150 characters.';
        }
        if (strlen($formValues['description']) > 5000) {
            $errors[] = 'Keep the description to 5,000 characters or fewer.';
        }

        if ($errors === []) {
            try {
                $insert = $conn->prepare(
                    'INSERT INTO farms (farmer_id, farm_name, location, description)
                     VALUES (?, ?, ?, ?)'
                );
                $insert->bind_param(
                    'isss',
                    $farmerId,
                    $formValues['farm_name'],
                    $formValues['location'],
                    $formValues['description']
                );
                $insert->execute();
                $insert->close();

                header('Location: manage-farms.php?created=1', true, 303);
                exit;
            } catch (Throwable $error) {
                error_log('AgroNest farm creation failed.');
                http_response_code(500);
                $notice = 'We could not save this farm. Please try again later.';
                $noticeType = 'error';
            }
        }
    }
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    $notice = 'This request method is not supported.';
    $noticeType = 'error';
}

if (($_GET['created'] ?? '') === '1') {
    $notice = 'Your farm has been added.';
}
if (($_GET['updated'] ?? '') === '1') {
    $notice = 'Your farm details have been updated.';
}
if (($_GET['deleted'] ?? '') === '1') {
    $notice = 'Your farm has been deleted.';
}
if (($_GET['delete_blocked'] ?? '') === '1') {
    $notice = 'This farm has products linked to it and cannot be deleted. Remove or move those products first.';
    $noticeType = 'error';
}
if (($_GET['not_found'] ?? '') === '1') {
    $notice = 'That farm could not be found in your account.';
    $noticeType = 'error';
}

try {
    $list = $conn->prepare(
        'SELECT farm_id, farm_name, location, description, created_at
         FROM farms
         WHERE farmer_id = ?
         ORDER BY created_at DESC, farm_id DESC'
    );
    $list->bind_param('i', $farmerId);
    $list->execute();
    $list->bind_result($farmId, $farmName, $location, $description, $createdAt);
    $farms = [];
    while ($list->fetch()) {
        $farms[] = [
            'farm_id' => $farmId,
            'farm_name' => $farmName,
            'location' => $location,
            'description' => $description,
            'created_at' => $createdAt,
        ];
    }
    $list->close();
} catch (Throwable $error) {
    error_log('AgroNest farm list could not be loaded.');
    http_response_code(500);
    $farms = [];
    $notice = 'We could not load your farms. Please try again later.';
    $noticeType = 'error';
}

function escapeFarmValue(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage farms owned by your AgroNest farmer account.">
    <title>Manage Farms | AgroNest</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/farms.css">
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
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="manage-farms.php" aria-current="page">Manage Farms</a></li>
            <li><a href="manage-products.php">Manage Products</a></li>
            <li>
                <form action="logout.php" method="post" class="dashboard-logout">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <button type="submit">Logout</button>
                </form>
            </li>
        </ul>
    </nav>

    <main class="dashboard-main farm-management">
        <section class="dashboard-welcome" aria-labelledby="page-heading">
            <p class="dashboard-kicker">Farmer tools</p>
            <h1 id="page-heading">Manage Farms</h1>
            <p>Hello, <?= $farmerName ?>. Add and manage the farms belonging to your account.</p>
        </section>

        <?php if ($notice !== ''): ?>
            <p class="farm-notice farm-notice--<?= escapeFarmValue($noticeType) ?>" role="status">
                <?= escapeFarmValue($notice) ?>
            </p>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <section class="farm-notice farm-notice--error" role="alert" aria-label="Farm form errors">
                <p>Please correct the following:</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escapeFarmValue($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="farm-form-card" aria-labelledby="add-farm-heading">
            <h2 id="add-farm-heading">Add a farm</h2>
            <form class="farm-form" action="manage-farms.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <p class="farm-field">
                    <label for="farm-name">Farm name</label>
                    <input id="farm-name" name="farm_name" type="text" maxlength="150"
                        value="<?= escapeFarmValue($formValues['farm_name']) ?>" required>
                </p>
                <p class="farm-field">
                    <label for="farm-location">Location</label>
                    <input id="farm-location" name="location" type="text" maxlength="150"
                        placeholder="Town, district or area"
                        value="<?= escapeFarmValue($formValues['location']) ?>" required>
                </p>
                <p class="farm-field">
                    <label for="farm-description">Description <span>(optional)</span></label>
                    <textarea id="farm-description" name="description" rows="4" maxlength="5000"><?= escapeFarmValue($formValues['description']) ?></textarea>
                </p>
                <button type="submit">Add Farm</button>
            </form>
        </section>

        <section class="farm-list-section" aria-labelledby="farm-list-heading">
            <h2 id="farm-list-heading">Your farms</h2>
            <?php if ($farms === []): ?>
                <p class="farm-empty-state">You have not added any farms yet.</p>
            <?php else: ?>
                <ul class="farm-list" aria-label="Farms owned by this account">
                    <?php foreach ($farms as $farm): ?>
                        <li>
                            <article class="farm-item">
                                <section class="farm-item-details">
                                    <h3><?= escapeFarmValue($farm['farm_name']) ?></h3>
                                    <p class="farm-item-location"><?= escapeFarmValue($farm['location']) ?></p>
                                    <?php if ($farm['description'] !== null && $farm['description'] !== ''): ?>
                                        <p><?= nl2br(escapeFarmValue($farm['description'])) ?></p>
                                    <?php endif; ?>
                                </section>
                                <nav class="farm-item-actions" aria-label="Farm actions">
                                    <a class="farm-action-link"
                                        href="farm-edit.php?farm_id=<?= (int) $farm['farm_id'] ?>">Edit</a>
                                    <form action="farm-delete.php" method="post"
                                        onsubmit="return confirm('Delete this farm? Farms with products cannot be deleted.');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="farm_id" value="<?= (int) $farm['farm_id'] ?>">
                                        <button class="farm-delete-button" type="submit">Delete</button>
                                    </form>
                                </nav>
                            </article>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
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
