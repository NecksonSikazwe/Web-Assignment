<?php
/* Public, account-free catalog that lists available products and safely renders local images. */
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once __DIR__ . '/config/database.php';

    $categoriesResult = $conn->query(
        'SELECT category_id, category_name FROM categories ORDER BY category_name'
    );
    $categories = [];
    while ($category = $categoriesResult->fetch_assoc()) {
        $categories[] = $category;
    }

    $selectedCategory = null;
    $categoryValue = $_GET['category_id'] ?? null;
    if ($categoryValue !== null) {
        $selectedCategory = filter_var(
            $categoryValue,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($selectedCategory === false || $selectedCategory === null) {
            http_response_code(400);
            $selectedCategory = -1;
            $filterError = 'Please select a valid category.';
        } else {
            $categoryExists = false;
            foreach ($categories as $category) {
                if ((int) $category['category_id'] === $selectedCategory) {
                    $categoryExists = true;
                    break;
                }
            }
            if (!$categoryExists) {
                http_response_code(404);
                $selectedCategory = -1;
                $filterError = 'The selected category could not be found.';
            }
        }
    }

    $products = [];
    if ($selectedCategory !== -1) {
        $sql = 'SELECT p.product_id, p.product_name, p.description, p.quantity,
                       p.unit, p.price_per_unit, c.category_name, f.farm_name,
                       f.location,
                       (SELECT pi.image_path
                        FROM product_images AS pi
                        WHERE pi.product_id = p.product_id
                        ORDER BY pi.is_primary DESC, pi.image_id ASC
                        LIMIT 1) AS image_path
                FROM products AS p
                INNER JOIN categories AS c ON c.category_id = p.category_id
                INNER JOIN farms AS f ON f.farm_id = p.farm_id
                WHERE p.status = ?';
        if ($selectedCategory !== null) {
            $sql .= ' AND p.category_id = ?';
        }
        $sql .= ' ORDER BY p.created_at DESC, p.product_id DESC';

        $listing = $conn->prepare($sql);
        $available = 'available';
        if ($selectedCategory !== null) {
            $listing->bind_param('si', $available, $selectedCategory);
        } else {
            $listing->bind_param('s', $available);
        }
        $listing->execute();
        $listing->bind_result(
            $productId,
            $productName,
            $description,
            $quantity,
            $unit,
            $pricePerUnit,
            $categoryName,
            $farmName,
            $location,
            $imagePath
        );
        while ($listing->fetch()) {
            $products[] = [
                'product_id' => $productId,
                'product_name' => $productName,
                'description' => $description,
                'quantity' => $quantity,
                'unit' => $unit,
                'price_per_unit' => $pricePerUnit,
                'category_name' => $categoryName,
                'farm_name' => $farmName,
                'location' => $location,
                'image_path' => $imagePath,
            ];
        }
        $listing->close();
    }
} catch (Throwable $error) {
    error_log('AgroNest public marketplace could not load product data.');
    http_response_code(503);
    $categories = [];
    $products = [];
    $selectedCategory = null;
    $loadError = true;
}

function escapeMarketplaceValue(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Only serve recognized product-image paths that resolve to an existing local file.
function safeMarketplaceImage(?string $path): ?string
{
    if (
        $path === null
        || !str_starts_with($path, 'images/')
        || preg_match('/\A[a-zA-Z0-9._\/-]+\z/', $path) !== 1
        || preg_match('/\.(?:png|jpe?g|gif|webp)\z/i', $path) !== 1
        || preg_match('~(?:^|/)\.\.(?:/|$)~', $path) === 1
        || !is_file(__DIR__ . '/' . $path)
    ) {
        return null;
    }

    return $path;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Browse available products from AgroNest farms and local farmers.">
    <title>Available Products | AgroNest</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/products-services.css">
    <link rel="stylesheet" href="css/marketplace.css">
</head>

<body>
    <header>
        <a href="index.html" class="logo-container" aria-label="AgroNest home">
            <img src="images/logo.png" alt="" width="40" height="40">
            <span>AgroNest</span>
        </a>
        <nav class="header-actions" aria-label="Quick actions">
            <a href="pages/contact.html">Contact AgroNest</a>
        </nav>
    </header>

    <nav aria-label="Main Navigation">
        <ul>
            <li><a href="index.html">Home</a></li>
            <li><a href="pages/about.html">About Us</a></li>
            <li><a href="marketplace.php" aria-current="page">Marketplace</a></li>
            <li><a href="pages/products-services.html">Products &amp; Services</a></li>
            <li><a href="pages/farm-resources.html">Farm &amp; Resources</a></li>
            <li><a href="pages/contact.html">Contact Us</a></li>
            <li><a href="farmer/login.php">Farmer login</a></li>
        </ul>
    </nav>

    <main>
        <header class="page-header products-services-header marketplace-header">
            <p class="products-kicker">From local farms</p>
            <h1>AgroNest Marketplace</h1>
            <p>Browse products currently available from AgroNest farmers.</p>
        </header>

        <section class="marketplace-section" aria-labelledby="marketplace-list-heading">
            <form class="marketplace-filter" action="marketplace.php" method="get">
                <label for="category-filter">Browse by category</label>
                <select id="category-filter" name="category_id">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['category_id'] ?>"
                            <?= $selectedCategory === (int) $category['category_id'] ? 'selected' : '' ?>>
                            <?= escapeMarketplaceValue($category['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Filter</button>
                <?php if ($selectedCategory !== null): ?>
                    <a href="marketplace.php">Clear filter</a>
                <?php endif; ?>
            </form>

            <h2 class="visually-hidden" id="marketplace-list-heading">Available products</h2>
            <?php if (!empty($loadError)): ?>
                <p class="marketplace-empty" role="alert">
                    We could not load products right now. Please try again later.
                </p>
            <?php elseif (isset($filterError)): ?>
                <p class="marketplace-empty" role="alert">
                    <?= escapeMarketplaceValue($filterError) ?>
                </p>
            <?php elseif ($products === []): ?>
                <p class="marketplace-empty" role="status">
                    No available products were found. Please check back soon.
                </p>
            <?php else: ?>
                <ul class="marketplace-grid" aria-label="Available product listings">
                    <?php foreach ($products as $product): ?>
                        <li>
                            <article class="marketplace-card">
                                <?php $image = safeMarketplaceImage($product['image_path']); ?>
                                <?php if ($image !== null): ?>
                                    <img class="marketplace-card-image"
                                        src="<?= escapeMarketplaceValue($image) ?>"
                                        alt="<?= escapeMarketplaceValue($product['product_name']) ?>"
                                        loading="lazy">
                                <?php else: ?>
                                    <span class="marketplace-card-placeholder" aria-hidden="true">
                                        AgroNest
                                    </span>
                                <?php endif; ?>
                                <section class="marketplace-card-content">
                                    <p class="marketplace-category"><?= escapeMarketplaceValue($product['category_name']) ?></p>
                                    <h3>
                                        <a href="product-details.php?product_id=<?= (int) $product['product_id'] ?>">
                                            <?= escapeMarketplaceValue($product['product_name']) ?>
                                        </a>
                                    </h3>
                                    <p class="marketplace-farm">
                                        <?= escapeMarketplaceValue($product['farm_name']) ?> &middot;
                                        <?= escapeMarketplaceValue($product['location']) ?>
                                    </p>
                                    <?php if ($product['description'] !== null && $product['description'] !== ''): ?>
                                        <p class="marketplace-description"><?= escapeMarketplaceValue($product['description']) ?></p>
                                    <?php endif; ?>
                                    <p class="marketplace-price">
                                        <strong>K<?= escapeMarketplaceValue($product['price_per_unit']) ?></strong>
                                        <span>/ <?= escapeMarketplaceValue($product['unit']) ?></span>
                                    </p>
                                    <a class="marketplace-view-link"
                                        href="product-details.php?product_id=<?= (int) $product['product_id'] ?>">
                                        View product details
                                    </a>
                                </section>
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
                <li><a href="index.html">Home</a></li>
                <li><a href="pages/about.html">About Us</a></li>
                <li><a href="marketplace.php">Marketplace</a></li>
                <li><a href="pages/products-services.html">Products &amp; Services</a></li>
                <li><a href="pages/farm-resources.html">Farm &amp; Resources</a></li>
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
