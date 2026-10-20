<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('sales/history.php');
}
csrf_check();

$saleId = (int) input('sale_id');
$reason = input('reason');

if ($saleId <= 0) {
    flash('danger', 'Sale not found.');
    redirect('sales/history.php');
}
if ($reason === '' || mb_strlen($reason) > 255) {
    flash('danger', 'Enter a reason of 255 characters or fewer.');
    redirect('sales/receipt.php?id=' . $saleId);
}

mysqli_begin_transaction($conn);
try {
    $sale = db_row($conn, 'SELECT id, receipt_number, status, total_amount FROM sales WHERE id = ? FOR UPDATE', 'i', [$saleId]);
    if (!$sale) {
        throw new RuntimeException('Sale not found.');
    }
    if ($sale['status'] !== 'completed') {
        throw new RuntimeException('This sale is already cancelled.');
    }

    $now = date('Y-m-d H:i:s');
    db_exec(
        $conn,
        "UPDATE sales SET status = 'cancelled', cancelled_by = ?, cancelled_at = ?, cancel_reason = ? WHERE id = ? AND status = 'completed'",
        'issi',
        [$_SESSION['user_id'], $now, $reason, $saleId]
    );

    $items = db_all($conn, 'SELECT product_id, quantity FROM sale_items WHERE sale_id = ? ORDER BY product_id', 'i', [$saleId]);
    foreach ($items as $it) {
        record_stock_transaction($conn, [
            'product_id'       => (int) $it['product_id'],
            'type'             => 'in',
            'quantity'         => (float) $it['quantity'],   // base-unit quantity that was deducted
            'transaction_date' => $now,
            'reference_no'     => $sale['receipt_number'],
            'supplier_id'      => null,
            'reason'           => 'Sale cancelled',
            'remarks'          => $reason,
            'user_id'          => $_SESSION['user_id'],
        ], false);
    }

    mysqli_commit($conn);
} catch (RuntimeException $ex) {
    mysqli_rollback($conn);
    flash('warning', $ex->getMessage());
    redirect('sales/receipt.php?id=' . $saleId);
} catch (Throwable $ex) {
    mysqli_rollback($conn);
    error_log('Sale cancellation failed: ' . $ex->getMessage());
    flash('danger', 'The sale could not be cancelled and no stock was changed. Please try again.');
    redirect('sales/receipt.php?id=' . $saleId);
}

log_activity($conn, 'sale_cancelled', 'Receipt ' . $sale['receipt_number'] . ', total ' . $sale['total_amount']);
flash('success', 'Sale ' . $sale['receipt_number'] . ' was cancelled and its items were returned to stock.');
redirect('sales/receipt.php?id=' . $saleId);
