<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

// All numbers come from live database queries.
$stats = db_row($conn, 'SELECT COUNT(*) AS total_products,
                               COALESCE(SUM(quantity), 0) AS total_units,
                               COALESCE(SUM(quantity > 0 AND quantity <= min_stock), 0) AS low_stock,
                               COALESCE(SUM(quantity = 0), 0) AS out_of_stock
                        FROM products');
$totalCategories = (int) db_val($conn, 'SELECT COUNT(*) FROM categories');

$monthStart = date('Y-m-01') . ' 00:00:00';
$todayStart = date('Y-m-d') . ' 00:00:00';
$tomorrowStart = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
$salesToday = db_row($conn, "SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total
                             FROM sales WHERE status = 'completed' AND created_at >= ? AND created_at < ?", 'ss', [$todayStart, $tomorrowStart]);
$salesMonth = (float) db_val($conn, "SELECT COALESCE(SUM(total_amount), 0)
                                     FROM sales WHERE status = 'completed' AND created_at >= ? AND created_at < ?", 'ss', [$monthStart, $tomorrowStart]);
$bestSeller = db_row($conn, "SELECT p.name, SUM(si.quantity) AS units
                             FROM sale_items si
                             JOIN sales s ON s.id = si.sale_id
                             JOIN products p ON p.id = si.product_id
                             WHERE s.status = 'completed' AND s.created_at >= ? AND s.created_at < ?
                             GROUP BY p.id, p.name
                             ORDER BY units DESC, p.name ASC LIMIT 1", 'ss', [$monthStart, $tomorrowStart]);
$salesCards = [
    ['Today\'s sales', money($salesToday['total']), 'cash-coin', ''],
    ['Transactions today', number_format((int) $salesToday['cnt']), 'receipt', ''],
    ['Monthly sales', money($salesMonth), 'calendar-month', ''],
    ['Best-selling product (this month)', $bestSeller ? $bestSeller['name'] : 'No sales yet', 'trophy', $bestSeller ? (int) $bestSeller['units'] . ' unit(s) sold' : ''],
];

$attention = db_all($conn, 'SELECT p.id, p.sku, p.name, p.quantity, p.min_stock, c.name AS category_name
                            FROM products p JOIN categories c ON c.id = p.category_id
                            WHERE p.quantity <= p.min_stock
                            ORDER BY p.quantity ASC, p.name ASC LIMIT 8');

$recent = db_all($conn, 'SELECT t.transaction_type, t.quantity, t.reference_no, t.transaction_date,
                                p.name AS product_name, u.full_name AS user_name
                         FROM stock_transactions t
                         JOIN products p ON p.id = t.product_id
                         LEFT JOIN users u ON u.id = t.created_by
                         ORDER BY t.transaction_date DESC, t.id DESC LIMIT 8');

$chartRows = db_all($conn, 'SELECT c.name, COALESCE(SUM(p.quantity), 0) AS units
                            FROM categories c LEFT JOIN products p ON p.category_id = c.id
                            GROUP BY c.id, c.name ORDER BY units DESC, c.name LIMIT 8');
$chartLabels = array_column($chartRows, 'name');
$chartValues = array_map('intval', array_column($chartRows, 'units'));

$cards = [
    ['Total products', (int) $stats['total_products'], 'box-seam', ''],
    ['Total stock (units)', (int) $stats['total_units'], 'stack', ''],
    ['Low stock', (int) $stats['low_stock'], 'exclamation-triangle', $stats['low_stock'] > 0 ? 'text-warning-emphasis' : ''],
    ['Out of stock', (int) $stats['out_of_stock'], 'x-octagon', $stats['out_of_stock'] > 0 ? 'text-danger' : ''],
    ['Categories', $totalCategories, 'tags', ''],
];

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$useChart = true;
include __DIR__ . '/includes/header.php';
?>

<link href="<?= e(url('assets/css/sales.css')) ?>" rel="stylesheet">

<div class="row row-cols-2 row-cols-xl-4 g-3 mb-3">
    <?php foreach ($salesCards as $i => [$label, $value, $icon, $sub]): ?>
        <div class="col">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="stat-icon"><i class="bi bi-<?= e($icon) ?>"></i></div>
                    <div class="stat-value<?= $i === 3 ? ' stat-value-text' : '' ?>"><?= e($value) ?></div>
                    <div class="stat-label"><?= e($label) ?><?= $sub !== '' ? ' · ' . e($sub) : '' ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
    <?php foreach ($cards as [$label, $value, $icon, $valueClass]): ?>
        <div class="col">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="stat-icon"><i class="bi bi-<?= e($icon) ?>"></i></div>
                    <div class="stat-value <?= e($valueClass) ?>"><?= number_format($value) ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Products needing attention</span>
                <a href="<?= e(url('products/index.php?status=low')) ?>" class="small">View all low stock</a>
            </div>
            <?php if (!$attention): ?>
                <?= empty_state('check2-circle', 'All stocked up', 'No product is at or below its minimum stock level.') ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Product</th><th>Category</th><th class="text-end">Stock</th><th class="text-end">Minimum</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($attention as $p): ?>
                            <tr>
                                <td><a href="<?= e(url('products/view.php?id=' . $p['id'])) ?>"><?= e($p['name']) ?></a><div class="small text-muted"><?= e($p['sku']) ?></div></td>
                                <td><?= e($p['category_name']) ?></td>
                                <td class="text-end"><?= (int) $p['quantity'] ?></td>
                                <td class="text-end"><?= (int) $p['min_stock'] ?></td>
                                <td><?= status_badge((int) $p['quantity'], (int) $p['min_stock']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Stock by category (units)</div>
            <div class="card-body">
                <?php if (!$chartRows || array_sum($chartValues) === 0): ?>
                    <?= empty_state('bar-chart', 'Nothing to chart yet', 'Add products and record stock to see this chart.') ?>
                <?php else: ?>
                    <div class="chart-wrap">
                        <canvas id="stockChart"
                                data-labels="<?= e(json_encode($chartLabels)) ?>"
                                data-values="<?= e(json_encode($chartValues)) ?>"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Recent stock movements</span>
        <a href="<?= e(url('stock/history.php')) ?>" class="small">View full history</a>
    </div>
    <?php if (!$recent): ?>
        <?= empty_state('clock-history', 'No transactions yet', 'Stock-in and stock-out records will appear here.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Date</th><th>Product</th><th>Type</th><th class="text-end">Qty</th><th>Reference</th><th>By</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $t): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(format_datetime($t['transaction_date'])) ?></td>
                        <td><?= e($t['product_name']) ?></td>
                        <td><?= type_badge($t['transaction_type']) ?></td>
                        <td class="text-end"><?= signed_quantity($t['transaction_type'], $t['quantity']) ?></td>
                        <td><?= e($t['reference_no']) ?></td>
                        <td><?= e($t['user_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
