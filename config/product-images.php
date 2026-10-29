<?php
/* Validate uploaded image contents and store generated local paths for product-image records. */
declare(strict_types=1);

function storeProductImageUpload(?array $upload): ?string
{
    if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($upload['error'] ?? null) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Choose a valid JPEG, PNG or WebP product image up to 5 MB.');
    }

    $temporaryPath = $upload['tmp_name'] ?? null;
    if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('The selected product image could not be uploaded.');
    }

    $fileSize = filesize($temporaryPath);
    if ($fileSize === false || $fileSize > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Choose a product image no larger than 5 MB.');
    }

    // Trust detected file content rather than the browser-supplied filename or MIME header.
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $imageInformation = getimagesize($temporaryPath);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (
        !is_string($mimeType)
        || !isset($extensions[$mimeType])
        || $imageInformation === false
        || ($imageInformation['mime'] ?? null) !== $mimeType
    ) {
        throw new InvalidArgumentException('Choose a valid JPEG, PNG or WebP image.');
    }

    $directory = dirname(__DIR__) . '/images/products';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The product image directory could not be created.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
    if (!move_uploaded_file($temporaryPath, $directory . '/' . $filename)) {
        throw new RuntimeException('The uploaded product image could not be saved.');
    }

    return 'images/products/' . $filename;
}

function removeStoredProductImage(?string $imagePath): void
{
    if (
        $imagePath === null
        || preg_match('~\Aimages/products/[a-f0-9]{32}\.(?:jpg|png|webp)\z~', $imagePath) !== 1
    ) {
        return;
    }

    $absolutePath = dirname(__DIR__) . '/' . $imagePath;
    if (is_file($absolutePath) && !unlink($absolutePath)) {
        error_log('AgroNest temporary product image cleanup failed.');
    }
}
