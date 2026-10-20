<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$sale = $id > 0 ? db_row(
    $conn,
    'SELECT s.*, u.full_name AS cashier, c.full_name AS canceller
     FROM sales s
     LEFT JOIN users u ON u.id = s.created_by
     LEFT JOIN users c ON c.id = s.cancelled_by
     WHERE s.id = ?',
    'i',
    [$id]
) : null;

if (!$sale) {
    flash('danger', 'That receipt was not found.');
    redirect('sales/history.php');
}

$items = db_all(
    $conn,
    'SELECT product_name, quantity, unit_name, unit_qty, unit_price, line_total FROM sale_items WHERE sale_id = ? ORDER BY id',
    'i',
    [$id]
);
$void = ($sale['status'] === 'cancelled');
$isGcash = (($sale['payment_method'] ?? 'cash') === 'gcash');

$pageTitle = 'Receipt ' . $sale['receipt_number'];
$activeNav = 'sales_history';
$pageActions = '<button type="button" class="btn btn-primary btn-sm" data-print><i class="bi bi-printer me-1"></i>Print receipt</button>'
    . ' <a href="' . e(url('sales/history.php')) . '" class="btn btn-light border btn-sm">Sales history</a>'
    . ' <a href="' . e(url('sales/pos.php')) . '" class="btn btn-light border btn-sm"><i class="bi bi-cart-plus me-1"></i>New sale</a>';
include __DIR__ . '/../includes/header.php';
?>
<link href="<?= e(url('assets/css/sales.css')) ?>" rel="stylesheet">

<div class="receipt-wrap mx-auto">
    <div class="card receipt">
        <div class="card-body">
            <div class="text-center mb-3">
                <h2 class="receipt-store"><?= e(APP_NAME) ?></h2>
                <div class="text-muted">Sales receipt</div>
            </div>

            <?php if ($void): ?>
                <div class="receipt-void mb-3">CANCELLED</div>
            <?php endif; ?>

            <dl class="detail-list mb-3" style="grid-template-columns: 90px 1fr;">
                <dt>Receipt no.</dt>
                <dd><?= e($sale['receipt_number']) ?></dd>
                <dt>Date</dt>
                <dd><?= e(format_datetime($sale['created_at'])) ?></dd>
                <dt>Cashier</dt>
                <dd><?= e($sale['cashier'] ?? '—') ?></dd>
                <dt>Payment</dt>
                <dd><?= $isGcash ? 'GCash' : 'Cash' ?></dd>
                <?php if ($isGcash && !empty($sale['payment_reference'])): ?>
                    <dt>GCash ref.</dt>
                    <dd><?= e($sale['payment_reference']) ?></dd>
                <?php endif; ?>
            </dl>

            <table class="table table-borderless receipt-lines mb-2">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= e($it['product_name']) ?></td>
                            <td class="text-end"><?= $it['unit_name'] !== null
                                ? e(fmt_qty($it['unit_qty']) . ' ' . $it['unit_name'])
                                : e(fmt_qty($it['quantity'])) ?></td>
                            <td class="text-end"><?= e(money($it['unit_price'])) ?></td>
                            <td class="text-end"><?= e(money($it['line_total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <table class="table table-borderless receipt-totals mb-0">
                <tr>
                    <td>Total</td>
                    <td class="text-end fw-semibold"><?= e(money($sale['total_amount'])) ?></td>
                </tr>
                <tr>
                    <td>Amount paid</td>
                    <td class="text-end"><?= e(money($sale['amount_paid'])) ?></td>
                </tr>
                <tr>
                    <td>Change</td>
                    <td class="text-end"><?= e(money($sale['change_amount'])) ?></td>
                </tr>
            </table>

            <?php if ($void): ?>
                <div class="small text-muted mt-3">
                    Cancelled <?= e(format_datetime($sale['cancelled_at'])) ?> by <?= e($sale['canceller'] ?? '—') ?>.
                    Reason: <?= e($sale['cancel_reason'] ?? '') ?>
                </div>
            <?php else: ?>
                <div class="text-center text-muted mt-3">Thank you!</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (is_admin() && !$void): ?>
        <div class="card mt-3 no-print">
            <div class="card-header">Cancel this sale</div>
            <div class="card-body">
                <p class="small text-muted">The sale stays in the records as cancelled and the sold items are returned to
                    stock.</p>
                <form method="post" action="<?= e(url('sales/cancel_sale.php')) ?>" class="js-confirm"
                    data-confirm="Cancel this sale and return the items to stock? This cannot be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="sale_id" value="<?= (int) $sale['id'] ?>">
                    <label for="reason" class="form-label">Reason *</label>
                    <input type="text" class="form-control mb-2" id="reason" name="reason" maxlength="255" required>
                    <button type="submit" class="btn btn-outline-danger btn-sm">Cancel sale</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>