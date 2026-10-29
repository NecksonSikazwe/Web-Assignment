<?php
/* Show an available product and its public farm/contact details after validating its ID. */
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$productId = filter_var(
    $_GET['product_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($productId === false || $productId === null) {
    http_response_code(404);
    $product = null;
} else {
    try {
        require_once __DIR__ . '/config/database.php';
        $detail = $conn->prepare(
            'SELECT p.product_id, p.product_name, p.description, p.quantity,
                    p.unit, p.price_per_unit, p.status, c.category_name,
                    f.farm_name, f.location, farmers.full_name, farmers.phone, farmers.email,
                    (SELECT pi.image_path
                     FROM product_images AS pi
                     WHERE pi.product_id = p.product_id
                     ORDER BY pi.is_primary DESC, pi.image_id ASC
                     LIMIT 1) AS image_path
             FROM products AS p
             INNER JOIN categories AS c ON c.category_id = p.category_id
             INNER JOIN farms AS f ON f.farm_id = p.farm_id
             INNER JOIN farmers ON farmers.farmer_id = f.farmer_id
             WHERE p.product_id = ? AND p.status = ?
             LIMIT 1'
        );
        $available = 'available';
        $detail->bind_param('is', $productId, $available);
        $detail->execute();
        $detail->bind_result(
            $id,
            $name,
            $description,
            $quantity,
            $unit,
            $price,
            $status,
            $category,
            $farmName,
            $location,
            $farmerName,
            $farmerPhone,
            $farmerEmail,
            $imagePath
        );
        if ($detail->fetch()) {
            $product = [
                'product_id' => $id,
                'product_name' => $name,
                'description' => $description,
                'quantity' => $quantity,
                'unit' => $unit,
                'price_per_unit' => $price,
                'category_name' => $category,
                'farm_name' => $farmName,
                'location' => $location,
                'farmer_name' => $farmerName,
                'farmer_phone' => $farmerPhone,
                'farmer_email' => $farmerEmail,
                'image_path' => $imagePath,
            ];
        } else {
            $product = null;
        }
        $detail->close();

        if ($product === null) {
            http_response_code(404);
        }
    } catch (Throwable $error) {
        error_log('AgroNest public product details could not be loaded.');
        http_response_code(503);
        $product = null;
        $loadError = true;
    }
}

function escapeProductDetails(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function safeProductDetailsImage(?string $path): ?string
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
    <meta name="description" content="Product details from the AgroNest marketplace.">
    <title><?= $product !== null ? escapeProductDetails($product['product_name']) . ' | AgroNest' : 'Product unavailable | AgroNest' ?></title>
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
            <a href="marketplace.php">Browse products</a>
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

    <main class="marketplace-details-main">
        <?php if (!empty($loadError)): ?>
            <section class="marketplace-detail-card" role="alert">
                <h1>Product details unavailable</h1>
                <p>We could not load this product right now. Please try again later.</p>
                <a class="marketplace-view-link" href="marketplace.php">Return to marketplace</a>
            </section>
        <?php elseif ($product === null): ?>
            <section class="marketplace-detail-card" role="status">
                <h1>Product unavailable</h1>
                <p>This product could not be found or is no longer available.</p>
                <a class="marketplace-view-link" href="marketplace.php">Return to marketplace</a>
            </section>
        <?php else: ?>
            <article class="marketplace-detail-card">
                <?php $image = safeProductDetailsImage($product['image_path']); ?>
                <?php if ($image !== null): ?>
                    <img class="marketplace-detail-image"
                        src="<?= escapeProductDetails($image) ?>"
                        alt="<?= escapeProductDetails($product['product_name']) ?>">
                <?php endif; ?>
                <section class="marketplace-detail-content">
                    <p class="marketplace-category"><?= escapeProductDetails($product['category_name']) ?></p>
                    <h1><?= escapeProductDetails($product['product_name']) ?></h1>
                    <p class="marketplace-price">
                        <strong>K<?= escapeProductDetails($product['price_per_unit']) ?></strong>
                        <span>/ <?= escapeProductDetails($product['unit']) ?></span>
                    </p>
                    <p><strong>Quantity available:</strong>
                        <?= escapeProductDetails($product['quantity']) ?> <?= escapeProductDetails($product['unit']) ?></p>
                    <?php if ($product['description'] !== null && $product['description'] !== ''): ?>
                        <p><?= nl2br(escapeProductDetails($product['description'])) ?></p>
                    <?php endif; ?>

                    <section class="marketplace-farm-details" aria-labelledby="farm-details-heading">
                        <h2 id="farm-details-heading">Farm and farmer</h2>
                        <p><strong>Farm:</strong> <?= escapeProductDetails($product['farm_name']) ?></p>
                        <p><strong>Location:</strong> <?= escapeProductDetails($product['location']) ?></p>
                        <p><strong>Farmer:</strong> <?= escapeProductDetails($product['farmer_name']) ?></p>
                        <p><strong>Phone:</strong>
                            <a href="tel:<?= escapeProductDetails($product['farmer_phone']) ?>">
                                <?= escapeProductDetails($product['farmer_phone']) ?>
                            </a>
                        </p>
                        <p><strong>Email:</strong>
                            <a href="mailto:<?= escapeProductDetails($product['farmer_email']) ?>">
                                <?= escapeProductDetails($product['farmer_email']) ?>
                            </a>
                        </p>
                    </section>
                    <a class="marketplace-view-link" href="marketplace.php">Back to marketplace</a>
                </section>
            </article>
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
