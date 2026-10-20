<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/product_image.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$product = db_row($conn, 'SELECT * FROM products WHERE id = ?', 'i', [$id]);
if (!$product) {
    flash('danger', 'Product not found.');
    redirect('products/index.php');
}

$categories = db_all($conn, 'SELECT id, name FROM categories ORDER BY name');
$suppliers = db_all($conn, 'SELECT id, name FROM suppliers ORDER BY name');
$isEdit = true;
$errors = [];
$units = product_units($conn, $id);
$oldImage = $product['image'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['sku', 'name', 'category_id', 'supplier_id', 'description', 'cost_price', 'selling_price', 'min_stock', 'base_unit', 'base_allow_decimal', 'multi_unit'] as $field) {
        $product[$field] = input($field);
    }
    $product['sku'] = strtoupper($product['sku']);
    $units = units_from_post();
    $errors = validate_product($conn, $product, $id, false) + validate_units($product, $units) + validate_product_image();

    if (!$errors) {
        $supplierId = $product['supplier_id'] === '' ? null : (int) $product['supplier_id'];
        $newImage = null;
        try {
            $imageName = $oldImage;
            if (!empty($_POST['remove_image'])) {
                $imageName = null;
            }
            $newImage = store_product_image();
            if ($newImage !== null) {
                $imageName = $newImage;
            }

            mysqli_begin_transaction($conn);
            db_exec(
                $conn,
                'UPDATE products SET sku = ?, name = ?, category_id = ?, supplier_id = ?, description = ?,
                                     cost_price = ?, selling_price = ?, min_stock = ?,
                                     base_unit = ?, base_allow_decimal = ?, multi_unit = ?, image = ?
                 WHERE id = ?',
                'ssiisssisiisi',
                [
                    $product['sku'],
                    $product['name'],
                    (int) $product['category_id'],
                    $supplierId,
                    $product['description'],
                    number_format((float) $product['cost_price'], 2, '.', ''),
                    number_format((float) $product['selling_price'], 2, '.', ''),
                    (int) $product['min_stock'],
                    $product['base_unit'],
                    $product['base_allow_decimal'] === '1' ? 1 : 0,
                    $product['multi_unit'] === '1' ? 1 : 0,
                    $imageName,
                    $id,
                ]
            );
            if ($product['multi_unit'] === '1') {
                save_product_units($conn, $id, $units);
            }
            mysqli_commit($conn);

            if ($oldImage && $oldImage !== $imageName) {
                delete_product_image($oldImage);
            }
            log_activity($conn, 'product_updated', "Updated product #$id {$product['sku']} - {$product['name']}");
            flash('success', 'Product "' . $product['name'] . '" was updated.');
            redirect('products/view.php?id=' . $id);
        } catch (Exception $ex) {
            mysqli_rollback($conn);
            delete_product_image($newImage);
            if ($ex instanceof mysqli_sql_exception && $ex->getCode() === 1062) {
                $errors['sku'] = 'This SKU / product code is already used by another product.';
            } else {
                error_log('Update product failed: ' . $ex->getMessage());
                $errors['general'] = 'The product could not be saved. Please try again.';
            }
        }
    }
}

$pageTitle = 'Edit product';
$activeNav = 'products';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/_form.php';
include __DIR__ . '/../includes/footer.php';