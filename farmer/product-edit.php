<?php
/* Update an owned product's farm, category, price, quantity, description, or availability. */
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
requireApprovedFarmer();

$farmerId = (int) $_SESSION['farmer_id'];
$farmerName = htmlspecialchars((string) ($_SESSION['farmer_name'] ?? 'Farmer'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrfToken = htmlspecialchars(farmerCsrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$productIdValue = $_POST['product_id'] ?? $_GET['product_id'] ?? null;
$productId = filter_var($productIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($productId === false || $productId === null) {
    http_response_code(404);
    exit('That product could not be found in your account.');
}

$product = [
    'farm_id' => '',
    'category_id' => '',
    'product_name' => '',
    'description' => '',
    'quantity' => '',
    'unit' => '',
    'price_per_unit' => '',
    'status' => '',
];
$errors = [];
$notice = '';

try {
    $find = $conn->prepare(
        'SELECT p.farm_id, p.category_id, p.product_name, p.description, p.quantity,
                p.unit, p.price_per_unit, p.status
         FROM products AS p
         INNER JOIN farms AS f ON f.farm_id = p.farm_id
         WHERE p.product_id = ? AND f.farmer_id = ?
         LIMIT 1'
    );
    $find->bind_param('ii', $productId, $farmerId);
    $find->execute();
    $find->bind_result($farmIdValue, $categoryIdValue, $nameValue, $descriptionValue, $quantityValue, $unitValue, $priceValue, $statusValue);
    $productFound = $find->fetch();
    if ($productFound) {
        $product = [
            'farm_id' => (string) $farmIdValue,
            'category_id' => (string) $categoryIdValue,
            'product_name' => $nameValue,
            'description' => $descriptionValue ?? '',
            'quantity' => (string) $quantityValue,
            'unit' => $unitValue,
            'price_per_unit' => (string) $priceValue,
            'status' => $statusValue,
        ];
    }
    $find->close();
} catch (Throwable $error) {
    error_log('AgroNest product details could not be loaded.');
    http_response_code(500);
    exit('We could not load this product. Please try again later.');
}

if (!$productFound) {
    http_response_code(404);
    exit('That product could not be found in your account.');
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
        foreach (array_keys($product) as $field) {
            $value = $_POST[$field] ?? '';
            $product[$field] = is_string($value) ? trim($value) : '';
        }
        $farmId = filter_var($product['farm_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $categoryId = filter_var($product['category_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $quantity = filter_var($product['quantity'], FILTER_VALIDATE_FLOAT);
        $price = filter_var($product['price_per_unit'], FILTER_VALIDATE_FLOAT);

        if ($farmId === false || $farmId === null) {
            $errors[] = 'Select one of your farms.';
        }
        if ($categoryId === false || $categoryId === null) {
            $errors[] = 'Select a valid product category.';
        }
        if ($product['product_name'] === '' || strlen($product['product_name']) > 150) {
            $errors[] = 'Enter a product name using no more than 150 characters.';
        }
        if (strlen($product['description']) > 5000) {
            $errors[] = 'Keep the description to 5,000 characters or fewer.';
        }
        if ($quantity === false || $quantity < 0 || !is_finite((float) $quantity) || $quantity > 99999999.99) {
            $errors[] = 'Enter a quantity from 0 to 99,999,999.99.';
        }
        if ($product['unit'] === '' || strlen($product['unit']) > 20) {
            $errors[] = 'Enter a unit using no more than 20 characters.';
        }
        if ($price === false || $price < 0 || !is_finite((float) $price) || $price > 99999999.99) {
            $errors[] = 'Enter a price from 0 to 99,999,999.99.';
        }
        if (!in_array($product['status'], ['available', 'sold_out'], true)) {
            $errors[] = 'Choose available or sold out.';
        }

        if ($errors === []) {
            try {
                $farmCheck = $conn->prepare('SELECT farm_id FROM farms WHERE farm_id = ? AND farmer_id = ? LIMIT 1');
                $farmCheck->bind_param('ii', $farmId, $farmerId);
                $farmCheck->execute();
                $farmCheck->store_result();
                $ownsFarm = $farmCheck->num_rows === 1;
                $farmCheck->close();
                if (!$ownsFarm) {
                    $errors[] = 'Select one of your farms.';
                }

                $categoryCheck = $conn->prepare('SELECT category_id FROM categories WHERE category_id = ? LIMIT 1');
                $categoryCheck->bind_param('i', $categoryId);
                $categoryCheck->execute();
                $categoryCheck->store_result();
                $categoryExists = $categoryCheck->num_rows === 1;
                $categoryCheck->close();
                if (!$categoryExists) {
                    $errors[] = 'Select a valid product category.';
                }

                if ($errors === []) {
                    $quantityValue = (float) $quantity;
                    $priceValue = (float) $price;
                    $update = $conn->prepare(
                        'UPDATE products AS p
                         INNER JOIN farms AS f ON f.farm_id = p.farm_id
                         SET p.farm_id = ?, p.category_id = ?, p.product_name = ?, p.description = ?,
                             p.quantity = ?, p.unit = ?, p.price_per_unit = ?, p.status = ?
                         WHERE p.product_id = ? AND f.farmer_id = ?'
                    );
                    $update->bind_param(
                        'iissdsdsii',
                        $farmId,
                        $categoryId,
                        $product['product_name'],
                        $product['description'],
                        $quantityValue,
                        $product['unit'],
                        $priceValue,
                        $product['status'],
                        $productId,
                        $farmerId
                    );
                    $update->execute();
                    $update->close();

                    header('Location: manage-products.php?message=updated', true, 303);
                    exit;
                }
            } catch (Throwable $error) {
                error_log('AgroNest product update failed.');
                http_response_code(500);
                $notice = 'We could not update this product. Please try again later.';
            }
        }
    }
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    $notice = 'This request method is not supported.';
}

try {
    $farmsQuery = $conn->prepare('SELECT farm_id, farm_name FROM farms WHERE farmer_id = ? ORDER BY farm_name');
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
} catch (Throwable $error) {
    error_log('AgroNest product form options could not be loaded.');
    http_response_code(500);
    exit('We could not load product options. Please try again later.');
}

function escapeProductEditValue(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product | AgroNest</title>
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
            <h1 id="page-heading">Edit Product</h1>
            <p>Update a product belonging to one of your farms.</p>
        </section>

        <?php if ($notice !== ''): ?>
            <p class="product-notice product-notice--error" role="alert"><?= escapeProductEditValue($notice) ?></p>
        <?php endif; ?>
        <?php if ($errors !== []): ?>
            <section class="product-notice product-notice--error" role="alert" aria-label="Product update errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escapeProductEditValue($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="product-form-card" aria-labelledby="edit-product-heading">
            <h2 id="edit-product-heading">Product details</h2>
            <form class="product-form" action="product-edit.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                <p class="product-field">
                    <label for="product-farm">Farm</label>
                    <select id="product-farm" name="farm_id" required>
                        <?php foreach ($farms as $farm): ?>
                            <option value="<?= (int) $farm['farm_id'] ?>"
                                <?= $product['farm_id'] === (string) $farm['farm_id'] ? 'selected' : '' ?>>
                                <?= escapeProductEditValue($farm['farm_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p class="product-field">
                    <label for="product-category">Category</label>
                    <select id="product-category" name="category_id" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>"
                                <?= $product['category_id'] === (string) $category['category_id'] ? 'selected' : '' ?>>
                                <?= escapeProductEditValue($category['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p class="product-field">
                    <label for="product-name">Product name</label>
                    <input id="product-name" name="product_name" type="text" maxlength="150"
                        value="<?= escapeProductEditValue($product['product_name']) ?>" required>
                </p>
                <p class="product-field">
                    <label for="product-description">Description <span>(optional)</span></label>
                    <textarea id="product-description" name="description" rows="4" maxlength="5000"><?= escapeProductEditValue($product['description']) ?></textarea>
                </p>
                <fieldset class="product-field product-field--split">
                    <legend class="visually-hidden">Product quantity and price details</legend>
                    <label>
                        <span>Quantity</span>
                        <input id="product-quantity" name="quantity" type="number" min="0"
                            max="99999999.99" step="0.01" value="<?= escapeProductEditValue($product['quantity']) ?>" required>
                    </label>
                    <label>
                        <span>Unit</span>
                        <input id="product-unit" name="unit" type="text" maxlength="20"
                            value="<?= escapeProductEditValue($product['unit']) ?>" required>
                    </label>
                    <label>
                        <span>Price per unit</span>
                        <input id="product-price" name="price_per_unit" type="number" min="0"
                            max="99999999.99" step="0.01" value="<?= escapeProductEditValue($product['price_per_unit']) ?>" required>
                    </label>
                </fieldset>
                <p class="product-field">
                    <label for="product-status">Availability</label>
                    <select id="product-status" name="status" required>
                        <option value="available" <?= $product['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                        <option value="sold_out" <?= $product['status'] === 'sold_out' ? 'selected' : '' ?>>Sold out</option>
                    </select>
                </p>
                <button type="submit">Save Changes</button>
                <a class="product-cancel-link" href="manage-products.php">Cancel</a>
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
