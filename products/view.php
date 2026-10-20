<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$p = db_row(
    $conn,
    'SELECT p.*, c.name AS category_name, s.name AS supplier_name
     FROM products p
     JOIN categories c ON c.id = p.category_id
     LEFT JOIN suppliers s ON s.id = p.supplier_id
     WHERE p.id = ?',
    'i',
    [$id]
);
if (!$p) {
    flash('danger', 'Product not found.');
    redirect('products/index.php');
}

$history = db_all(
    $conn,
    'SELECT t.*, u.full_name AS user_name
     FROM stock_transactions t LEFT JOIN users u ON u.id = t.created_by
     WHERE t.product_id = ?
     ORDER BY t.transaction_date DESC, t.id DESC LIMIT 10',
    'i',
    [$id]
);

$pageTitle = 'Product details';
$activeNav = 'products';
$pageActions = '<a href="' . e(url('stock/stock_in.php?product_id=' . $id)) . '" class="btn btn-outline-success btn-sm"><i class="bi bi-box-arrow-in-down me-1"></i>Stock in</a> '
    . '<a href="' . e(url('stock/stock_out.php?product_id=' . $id)) . '" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-up me-1"></i>Stock out</a> '
    . '<a href="' . e(url('products/edit.php?id=' . $id)) . '" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><?= e($p['name']) ?></span>
                <?= status_badge((float) $p['quantity'], (int) $p['min_stock']) ?>
            </div>
            <div class="card-body">
                <dl class="detail-list">
                    <dt>SKU / code</dt><dd><?= e($p['sku']) ?></dd>
                    <dt>Category</dt><dd><?= e($p['category_name']) ?></dd>
                    <dt>Supplier</dt><dd><?= e($p['supplier_name'] ?? '—') ?></dd>
                    <dt>Cost price</dt><dd><?= e(money($p['cost_price'])) ?></dd>
                    <dt>Selling price</dt><dd><?= e(money($p['selling_price'])) ?></dd>
                    <dt>Date created</dt><dd><?= e(format_datetime($p['created_at'])) ?></dd>
                    <dt>Description</dt><dd><?= $p['description'] !== '' ? nl2br(e($p['description'])) : '—' ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Stock</div>
            <div class="card-body">
                <div class="stat-value"><?= e(fmt_qty($p['quantity'])) ?></div>
                <div class="stat-label mb-3">units on hand</div>
                <div class="small text-muted">Minimum level: <?= (int) $p['min_stock'] ?></div>
                <div class="small text-muted">Stock value (at cost): <?= e(money($p['quantity'] * $p['cost_price'])) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Recent stock movements</span>
        <a href="<?= e(url('stock/history.php?product=' . $id)) ?>" class="small">View all</a>
    </div>
    <?php if (!$history): ?>
        <?= empty_state('clock-history', 'No stock movements yet', 'Use Stock in to record the first delivery.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Date</th><th>Type</th><th class="text-end">Qty</th><th class="text-end">Balance</th><th>Reference</th><th>Reason</th><th>By</th></tr></thead>
                <tbody>
                <?php foreach ($history as $t): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(format_datetime($t['transaction_date'])) ?></td>
                        <td><?= type_badge($t['transaction_type']) ?></td>
                        <td class="text-end"><?= signed_quantity($t['transaction_type'], $t['quantity']) ?></td>
                        <td class="text-end"><?= (int) $t['stock_after'] ?></td>
                        <td><?= e($t['reference_no']) ?></td>
                        <td><?= e($t['reason']) ?></td>
                        <td><?= e($t['user_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<a href="<?= e(url('products/index.php')) ?>" class="btn btn-light border mt-3"><i class="bi bi-arrow-left me-1"></i>Back to products</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
