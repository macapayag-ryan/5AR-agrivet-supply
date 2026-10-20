<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();   // only administrators can delete products

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('products/index.php');
}
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$product = db_row($conn, 'SELECT sku, name FROM products WHERE id = ?', 'i', [$id]);
if (!$product) {
    flash('danger', 'Product not found.');
    redirect('products/index.php');
}

// Products with stock history cannot be deleted, so the audit trail stays complete.
$historyCount = (int) db_val($conn, 'SELECT COUNT(*) FROM stock_transactions WHERE product_id = ?', 'i', [$id]);
if ($historyCount > 0) {
    flash('warning', 'Cannot delete "' . $product['name'] . '" because it has ' . $historyCount . ' stock transaction(s) in the history. Set its stock to zero instead if you no longer sell it.');
    redirect('products/index.php');
}

try {
    db_exec($conn, 'DELETE FROM products WHERE id = ?', 'i', [$id]);
    log_activity($conn, 'product_deleted', "Deleted product #$id {$product['sku']} - {$product['name']}");
    flash('success', 'Product "' . $product['name'] . '" was deleted.');
} catch (mysqli_sql_exception $ex) {
    error_log('Delete product failed: ' . $ex->getMessage());
    flash('danger', 'The product could not be deleted.');
}
redirect('products/index.php');
