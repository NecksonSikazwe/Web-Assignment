<?php
/* Create and list this farmer's products, validating optional images and farm ownership. */
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/product-images.php';
requireApprovedFarmer();

$farmerId = (int) $_SESSION['farmer_id'];
$farmerName = htmlspecialchars(
    (string) ($_SESSION['farmer_name'] ?? 'Farmer'),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$csrfToken = htmlspecialchars(farmerCsrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formValues = [
    'farm_id' => '',
    'category_id' => '',
    'product_name' => '',
    'description' => '',
    'quantity' => '',
    'unit' => '',
    'price_per_unit' => '',
];
$errors = [];
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

        $farmId = filter_var($formValues['farm_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $categoryId = filter_var($formValues['category_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $quantity = filter_var($formValues['quantity'], FILTER_VALIDATE_FLOAT);
        $price = filter_var($formValues['price_per_unit'], FILTER_VALIDATE_FLOAT);

        if ($farmId === false || $farmId === null) {
            $errors[] = 'Select one of your farms.';
        }
        if ($categoryId === false || $categoryId === null) {
            $errors[] = 'Select a product category.';
        }
        if ($formValues['product_name'] === '' || strlen($formValues['product_name']) > 150) {
            $errors[] = 'Enter a product name using no more than 150 characters.';
        }
        if (strlen($formValues['description']) > 5000) {
            $errors[] = 'Keep the description to 5,000 characters or fewer.';
        }
        if ($quantity === false || $quantity < 0 || !is_finite((float) $quantity) || $quantity > 99999999.99) {
            $errors[] = 'Enter a quantity from 0 to 99,999,999.99.';
        }
        if ($formValues['unit'] === '' || strlen($formValues['unit']) > 20) {
            $errors[] = 'Enter a unit using no more than 20 characters.';
        }
        if ($price === false || $price < 0 || !is_finite((float) $price) || $price > 99999999.99) {
            $errors[] = 'Enter a price from 0 to 99,999,999.99.';
        }

        if ($errors === []) {
            try {
                $farmCheck = $conn->prepare(
                    'SELECT farm_id FROM farms WHERE farm_id = ? AND farmer_id = ? LIMIT 1'
                );
                $farmCheck->bind_param('ii', $farmId, $farmerId);
                $farmCheck->execute();
                $farmCheck->store_result();
                $ownsFarm = $farmCheck->num_rows === 1;
                $farmCheck->close();

                if (!$ownsFarm) {
                    $errors[] = 'Select one of your farms.';
                }

                $categoryCheck = $conn->prepare(
                    'SELECT category_id FROM categories WHERE category_id = ? LIMIT 1'
                );
                $categoryCheck->bind_param('i', $categoryId);
                $categoryCheck->execute();
                $categoryCheck->store_result();
                $categoryExists = $categoryCheck->num_rows === 1;
                $categoryCheck->close();

                if (!$categoryExists) {
                    $errors[] = 'Select a valid product category.';
                }

                if ($errors === []) {
                    $imagePath = null;
                    try {
                        $imagePath = storeProductImageUpload($_FILES['product_image'] ?? null);
                    } catch (InvalidArgumentException $error) {
                        $errors[] = $error->getMessage();
                    }

                    if ($errors === []) {
                        try {
                            $conn->begin_transaction();
                            $status = 'available';
                            $insert = $conn->prepare(
                                'INSERT INTO products
                                    (farm_id, category_id, product_name, description, quantity, unit, price_per_unit, status)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                            );
                            $quantityValue = (float) $quantity;
                            $priceValue = (float) $price;
                            $insert->bind_param(
                                'iissdsds',
                                $farmId,
                                $categoryId,
                                $formValues['product_name'],
                                $formValues['description'],
                                $quantityValue,
                                $formValues['unit'],
                                $priceValue,
                                $status
                            );
                            $insert->execute();
                            $productId = $conn->insert_id;
                            $insert->close();

                            if ($imagePath !== null) {
                                $isPrimary = 1;
                                $imageInsert = $conn->prepare(
                                    'INSERT INTO product_images (product_id, image_path, is_primary)
                                     VALUES (?, ?, ?)'
                                );
                                $imageInsert->bind_param('isi', $productId, $imagePath, $isPrimary);
                                $imageInsert->execute();
                                $imageInsert->close();
                            }

                            $conn->commit();
                            $imagePath = null;
                            header('Location: manage-products.php?message=created', true, 303);
                            exit;
                        } catch (Throwable $error) {
                            $conn->rollback();
                            removeStoredProductImage($imagePath);
                            throw $error;
                        }
                    }
                }
            } catch (Throwable $error) {
                error_log('AgroNest product creation failed.');
                http_response_code(500);
                $notice = 'We could not save this product. Please try again later.';
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

$messages = [
    'created' => ['Your product has been added.', 'success'],
    'updated' => ['Your product has been updated.', 'success'],
    'deleted' => ['Your product has been deleted.', 'success'],
    'not_found' => ['That product could not be found in your account.', 'error'],
];
$messageKey = $_GET['message'] ?? '';
if (isset($messages[$messageKey])) {
    [$notice, $noticeType] = $messages[$messageKey];
}

try {
    $farmsQuery = $conn->prepare(
        'SELECT farm_id, farm_name FROM farms WHERE farmer_id = ? ORDER BY farm_name'
    );
    $farmsQuery->bind_param('i', $farmerId);
    $farmsQuery->execute();
    $farmsQuery->bind_result($ownedFarmId, $ownedFarmName);
    $farms = [];
    while ($farmsQuery->fetch()) {
        $farms[] = ['farm_id' => $ownedFarmId, 'farm_name' => $ownedFarmName];
    }
    $farmsQuery->close();

    $categoriesResult = $conn->query('SELECT category_id, category_name FROM categories ORDER BY category_name');
    $categories = [];
    while ($category = $categoriesResult->fetch_assoc()) {
        $categories[] = $category;
    }

    $productsQuery = $conn->prepare(
        'SELECT p.product_id, p.product_name, p.description, p.quantity, p.unit,
                p.price_per_unit, p.status, f.farm_name, c.category_name,
                (SELECT pi.image_path
                 FROM product_images AS pi
                 WHERE pi.product_id = p.product_id
                 ORDER BY pi.is_primary DESC, pi.image_id ASC
                 LIMIT 1) AS image_path
         FROM products AS p
         INNER JOIN farms AS f ON f.farm_id = p.farm_id
         INNER JOIN categories AS c ON c.category_id = p.category_id
         WHERE f.farmer_id = ?
         ORDER BY p.created_at DESC, p.product_id DESC'
    );
    $productsQuery->bind_param('i', $farmerId);
    $productsQuery->execute();
    $productsQuery->bind_result(
        $productId,
        $productName,
        $description,
        $productQuantity,
        $unit,
        $pricePerUnit,
        $productStatus,
        $productFarmName,
        $categoryName,
        $productImagePath
    );
    $products = [];
    while ($productsQuery->fetch()) {
        $products[] = [
            'product_id' => $productId,
            'product_name' => $productName,
            'description' => $description,
            'quantity' => $productQuantity,
            'unit' => $unit,
            'price_per_unit' => $pricePerUnit,
            'status' => $productStatus,
            'farm_name' => $productFarmName,
            'category_name' => $categoryName,
            'image_path' => $productImagePath,
        ];
    }
    $productsQuery->close();
} catch (Throwable $error) {
    error_log('AgroNest product management data could not be loaded.');
    http_response_code(500);
    $farms = [];
    $categories = [];
    $products = [];
    $notice = 'We could not load your product information. Please try again later.';
    $noticeType = 'error';
}

// Escape database values before returning them to the HTML listing and form.
function escapeProductValue(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage products belonging to your AgroNest farms.">
    <title>Manage Products | AgroNest</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/products.css">
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
            <li><a href="manage-farms.php">Manage Farms</a></li>
            <li><a href="manage-products.php" aria-current="page">Manage Products</a></li>
            <li>
                <form action="logout.php" method="post" class="dashboard-logout">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <button type="submit">Logout</button>
                </form>
            </li>
        </ul>
    </nav>

    <main class="dashboard-main product-management">
        <section class="dashboard-welcome" aria-labelledby="page-heading">
            <p class="dashboard-kicker">Farmer tools</p>
            <h1 id="page-heading">Manage Products</h1>
            <p>Hello, <?= $farmerName ?>. Add and manage products from your farms.</p>
        </section>

        <?php if ($notice !== ''): ?>
            <p class="product-notice product-notice--<?= escapeProductValue($noticeType) ?>" role="status">
                <?= escapeProductValue($notice) ?>
            </p>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <section class="product-notice product-notice--error" role="alert" aria-label="Product form errors">
                <p>Please correct the following:</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escapeProductValue($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="product-form-card" aria-labelledby="add-product-heading">
            <h2 id="add-product-heading">Add a product</h2>
            <?php if ($farms === []): ?>
                <p>You need to <a href="manage-farms.php">add a farm</a> before you can add products.</p>
            <?php elseif ($categories === []): ?>
                <p>No product categories are available. Please contact AgroNest support.</p>
            <?php else: ?>
                <form class="product-form" action="manage-products.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <p class="product-field">
                        <label for="product-farm">Farm</label>
                        <select id="product-farm" name="farm_id" required>
                            <option value="">Choose one of your farms</option>
                            <?php foreach ($farms as $farm): ?>
                                <option value="<?= (int) $farm['farm_id'] ?>"
                                    <?= $formValues['farm_id'] === (string) $farm['farm_id'] ? 'selected' : '' ?>>
                                    <?= escapeProductValue($farm['farm_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p class="product-field">
                        <label for="product-category">Category</label>
                        <select id="product-category" name="category_id" required>
                            <option value="">Choose a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['category_id'] ?>"
                                    <?= $formValues['category_id'] === (string) $category['category_id'] ? 'selected' : '' ?>>
                                    <?= escapeProductValue($category['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p class="product-field">
                        <label for="product-name">Product name</label>
                        <input id="product-name" name="product_name" type="text" maxlength="150"
                            value="<?= escapeProductValue($formValues['product_name']) ?>" required>
                    </p>
                    <p class="product-field">
                        <label for="product-description">Description <span>(optional)</span></label>
                        <textarea id="product-description" name="description" rows="4" maxlength="5000"><?= escapeProductValue($formValues['description']) ?></textarea>
                    </p>
                    <p class="product-field">
                        <label for="product-image">Product image <span>(optional)</span></label>
                        <input id="product-image" name="product_image" type="file"
                            accept="image/jpeg,image/png,image/webp" aria-describedby="product-image-help">
                        <small id="product-image-help">JPEG, PNG or WebP, up to 5 MB.</small>
                    </p>
                    <fieldset class="product-field product-field--split">
                        <legend class="visually-hidden">Product quantity and price details</legend>
                        <label>
                            <span>Quantity</span>
                            <input id="product-quantity" name="quantity" type="number" min="0"
                                max="99999999.99" step="0.01"
                                value="<?= escapeProductValue($formValues['quantity']) ?>" required>
                        </label>
                        <label>
                            <span>Unit</span>
                            <input id="product-unit" name="unit" type="text" maxlength="20"
                                placeholder="kg, bunch, crate" value="<?= escapeProductValue($formValues['unit']) ?>" required>
                        </label>
                        <label>
                            <span>Price per unit</span>
                            <input id="product-price" name="price_per_unit" type="number" min="0"
                                max="99999999.99" step="0.01"
                                value="<?= escapeProductValue($formValues['price_per_unit']) ?>" required>
                        </label>
                    </fieldset>
                    <button type="submit">Add Product</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="product-list-section" aria-labelledby="product-list-heading">
            <h2 id="product-list-heading">Your products</h2>
            <?php if ($products === []): ?>
                <p class="product-empty-state">You have not added any products yet.</p>
            <?php else: ?>
                <ul class="product-list" aria-label="Products managed by this account">
                    <?php foreach ($products as $product): ?>
                        <li>
                            <article class="product-item">
                            <?php
                            $imagePath = $product['image_path'];
                            $imageIsSafe = is_string($imagePath)
                                && str_starts_with($imagePath, 'images/products/')
                                && preg_match('~\Aimages/products/[a-f0-9]{32}\.(?:jpg|png|webp)\z~', $imagePath) === 1
                                && is_file(dirname(__DIR__) . '/' . $imagePath);
                            ?>
                            <?php if ($imageIsSafe): ?>
                                <img class="product-item-image"
                                    src="../<?= escapeProductValue($imagePath) ?>"
                                    alt="<?= escapeProductValue($product['product_name']) ?>">
                            <?php endif; ?>
                            <section class="product-item-details">
                                <header class="product-item-heading">
                                    <h3><?= escapeProductValue($product['product_name']) ?></h3>
                                    <span class="product-status product-status--<?= $product['status'] === 'sold_out' ? 'sold-out' : 'available' ?>">
                                        <?= $product['status'] === 'sold_out' ? 'Sold out' : 'Available' ?>
                                    </span>
                                </header>
                                <p><?= escapeProductValue($product['farm_name']) ?> &middot;
                                    <?= escapeProductValue($product['category_name']) ?></p>
                                <p><?= escapeProductValue($product['quantity']) ?>
                                    <?= escapeProductValue($product['unit']) ?> &middot;
                                    <?= escapeProductValue($product['price_per_unit']) ?> per unit</p>
                                <?php if ($product['description'] !== null && $product['description'] !== ''): ?>
                                    <p><?= nl2br(escapeProductValue($product['description'])) ?></p>
                                <?php endif; ?>
                            </section>
                            <nav class="product-item-actions" aria-label="Product actions">
                                <a class="product-action-link"
                                    href="product-edit.php?product_id=<?= (int) $product['product_id'] ?>">Edit</a>
                                <form action="product-delete.php" method="post"
                                    onsubmit="return confirm('Delete this product? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
                                    <button class="product-delete-button" type="submit">Delete</button>
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
