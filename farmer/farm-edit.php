<?php
/* Edit one farm belonging to the signed-in farmer; submitted IDs never bypass the ownership check. */
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
$farmIdValue = $_POST['farm_id'] ?? $_GET['farm_id'] ?? null;
$farmId = filter_var($farmIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($farmId === false || $farmId === null) {
    http_response_code(404);
    exit('That farm could not be found in your account.');
}

$errors = [];
$notice = '';
$noticeType = 'error';
$farm = [
    'farm_name' => '',
    'location' => '',
    'description' => '',
];

try {
    $find = $conn->prepare(
        'SELECT farm_name, location, description
         FROM farms
         WHERE farm_id = ? AND farmer_id = ?
         LIMIT 1'
    );
    $find->bind_param('ii', $farmId, $farmerId);
    $find->execute();
    $find->bind_result($farmNameValue, $locationValue, $descriptionValue);
    $farmFound = $find->fetch();
    if ($farmFound) {
        $farm = [
            'farm_name' => $farmNameValue,
            'location' => $locationValue,
            'description' => $descriptionValue ?? '',
        ];
    }
    $find->close();
} catch (Throwable $error) {
    error_log('AgroNest farm details could not be loaded.');
    http_response_code(500);
    exit('We could not load this farm. Please try again later.');
}

if (!$farmFound) {
    http_response_code(404);
    exit('That farm could not be found in your account.');
}

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
    } else {
        foreach (array_keys($farm) as $field) {
            $value = $_POST[$field] ?? '';
            $farm[$field] = is_string($value) ? trim($value) : '';
        }

        if ($farm['farm_name'] === '' || strlen($farm['farm_name']) > 150) {
            $errors[] = 'Enter a farm name using no more than 150 characters.';
        }
        if ($farm['location'] === '' || strlen($farm['location']) > 150) {
            $errors[] = 'Enter a location using no more than 150 characters.';
        }
        if (strlen($farm['description']) > 5000) {
            $errors[] = 'Keep the description to 5,000 characters or fewer.';
        }

        if ($errors === []) {
            try {
                $update = $conn->prepare(
                    'UPDATE farms
                     SET farm_name = ?, location = ?, description = ?
                     WHERE farm_id = ? AND farmer_id = ?'
                );
                $update->bind_param(
                    'sssii',
                    $farm['farm_name'],
                    $farm['location'],
                    $farm['description'],
                    $farmId,
                    $farmerId
                );
                $update->execute();
                $update->close();

                header('Location: manage-farms.php?updated=1', true, 303);
                exit;
            } catch (Throwable $error) {
                error_log('AgroNest farm update failed.');
                http_response_code(500);
                $notice = 'We could not update this farm. Please try again later.';
            }
        }
    }
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    $notice = 'This request method is not supported.';
}

function escapeFarmEditValue(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Farm | AgroNest</title>
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
            <h1 id="page-heading">Edit Farm</h1>
            <p>Update details for a farm owned by your account.</p>
        </section>

        <?php if ($notice !== ''): ?>
            <p class="farm-notice farm-notice--<?= escapeFarmEditValue($noticeType) ?>" role="alert">
                <?= escapeFarmEditValue($notice) ?>
            </p>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <section class="farm-notice farm-notice--error" role="alert" aria-label="Farm update errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escapeFarmEditValue($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="farm-form-card" aria-labelledby="edit-farm-heading">
            <h2 id="edit-farm-heading">Farm details</h2>
            <form class="farm-form" action="farm-edit.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="farm_id" value="<?= (int) $farmId ?>">
                <p class="farm-field">
                    <label for="farm-name">Farm name</label>
                    <input id="farm-name" name="farm_name" type="text" maxlength="150"
                        value="<?= escapeFarmEditValue($farm['farm_name']) ?>" required>
                </p>
                <p class="farm-field">
                    <label for="farm-location">Location</label>
                    <input id="farm-location" name="location" type="text" maxlength="150"
                        value="<?= escapeFarmEditValue($farm['location']) ?>" required>
                </p>
                <p class="farm-field">
                    <label for="farm-description">Description <span>(optional)</span></label>
                    <textarea id="farm-description" name="description" rows="4" maxlength="5000"><?= escapeFarmEditValue($farm['description']) ?></textarea>
                </p>
                <button type="submit">Save Changes</button>
                <a class="farm-cancel-link" href="manage-farms.php">Cancel</a>
            </form>
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
