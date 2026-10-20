<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/product_image.php';
require_login();

$categories = db_all($conn, 'SELECT id, name FROM categories ORDER BY name');
$suppliers = db_all($conn, 'SELECT id, name FROM suppliers ORDER BY name');
$isEdit = false;
$errors = [];
$product = [
    'sku' => '',
    'name' => '',
    'category_id' => '',
    'supplier_id' => '',
    'description' => '',
    'cost_price' => '0.00',
    'selling_price' => '0.00',
    'quantity' => '0',
    'min_stock' => '10',
    'base_unit' => 'pc',
    'base_allow_decimal' => '',
    'multi_unit' => '',
];
$units = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (array_keys($product) as $field) {
        $product[$field] = input($field);
    }
    $product['sku'] = strtoupper($product['sku']);
    $units = units_from_post();
    $errors = validate_product($conn, $product, null, true) + validate_units($product, $units) + validate_product_image();

    if (!$errors) {
        $supplierId = $product['supplier_id'] === '' ? null : (int) $product['supplier_id'];
        $openingQty = (int) $product['quantity'];
        $imageName = null;
        try {
            $imageName = store_product_image();
            mysqli_begin_transaction($conn);
            db_exec(
                $conn,
                'INSERT INTO products (sku, name, category_id, supplier_id, description, cost_price, selling_price, quantity, min_stock, base_unit, base_allow_decimal, multi_unit, image)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)',
                'ssiisssisiis',
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
                ]
            );
            $newId = mysqli_insert_id($conn);
            if ($product['multi_unit'] === '1') {
                save_product_units($conn, $newId, $units);
            }

            if ($openingQty > 0) {
                record_stock_transaction($conn, [
                    'product_id' => $newId,
                    'type' => 'in',
                    'quantity' => $openingQty,
                    'transaction_date' => date('Y-m-d H:i:s'),
                    'reference_no' => generate_reference('OPEN'),
                    'supplier_id' => $supplierId,
                    'reason' => 'Opening stock',
                    'remarks' => 'Recorded when the product was created.',
                    'user_id' => $_SESSION['user_id'],
                ], false);
            }
            mysqli_commit($conn);

            log_activity($conn, 'product_created', "Created product {$product['sku']} - {$product['name']}");
            flash('success', 'Product "' . $product['name'] . '" was added.');
            redirect('products/view.php?id=' . $newId);
        } catch (Exception $ex) {
            mysqli_rollback($conn);
            delete_product_image($imageName);
            if ($ex instanceof mysqli_sql_exception && $ex->getCode() === 1062) {
                $errors['sku'] = 'This SKU / product code is already used by another product.';
            } else {
                error_log('Create product failed: ' . $ex->getMessage());
                $errors['general'] = 'The product could not be saved. Please try again.';
            }
        }
    }
}

$pageTitle = 'Add product';
$activeNav = 'products';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/_form.php';
include __DIR__ . '/../includes/footer.php';