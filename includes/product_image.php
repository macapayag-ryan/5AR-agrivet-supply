<?php
const PRODUCT_IMAGE_MAX_BYTES = 2097152;
const PRODUCT_IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function product_image_dir(): string
{
    return __DIR__ . '/../uploads/products/';
}

function product_image_url(?string $name): string
{
    if ($name === null || $name === '') {
        return '';
    }
    return url('uploads/products/' . rawurlencode($name));
}

function product_image_uploaded(): bool
{
    return isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;
}

function validate_product_image(): array
{
    if (!product_image_uploaded()) {
        return [];
    }
    $file = $_FILES['image'];
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['image' => 'The image is too large. Maximum size is 2 MB.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['image' => 'The image could not be uploaded. Please try again.'];
    }
    if ($file['size'] > PRODUCT_IMAGE_MAX_BYTES) {
        return ['image' => 'The image is too large. Maximum size is 2 MB.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(PRODUCT_IMAGE_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return ['image' => 'Only JPG, PNG or WEBP images are allowed.'];
    }
    return [];
}

function store_product_image(): ?string
{
    if (!product_image_uploaded() || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $tmp = $_FILES['image']['tmp_name'];
    $ext = PRODUCT_IMAGE_TYPES[(new finfo(FILEINFO_MIME_TYPE))->file($tmp)] ?? null;
    if ($ext === null) {
        return null;
    }
    $dir = product_image_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Could not create the image folder.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . $name)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }
    return $name;
}

function delete_product_image(?string $name): void
{
    if ($name === null || $name === '') {
        return;
    }
    $path = product_image_dir() . basename($name);
    if (is_file($path)) {
        @unlink($path);
    }
}