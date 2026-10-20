<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/brand.php';

// All numbers come from live database queries.
$stats = db_row($conn, 'SELECT COUNT(*) AS total_products,
                               COALESCE(SUM(quantity), 0) AS total_units,
                               COALESCE(SUM(quantity > 0 AND quantity <= min_stock), 0) AS low_stock,
                               COALESCE(SUM(quantity = 0), 0) AS out_of_stock
                        FROM products');
$totalCategories = (int) db_val($conn, 'SELECT COUNT(*) FROM categories');

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

// Display only: icon colour for each card above (same order as $cards).
$cardTones = ['green', 'green', 'amber', 'red', 'neutral'];

// Display only: inventory health, derived from the numbers already queried.
$healthTotal = (int) $stats['total_products'];
$healthLow = (int) $stats['low_stock'];
$healthOut = (int) $stats['out_of_stock'];
$healthOk = max(0, $healthTotal - $healthLow - $healthOut);
$healthParts = [
    ['ok', 'Well stocked', $healthOk],
    ['low', 'Low stock', $healthLow],
    ['out', 'Out of stock', $healthOut],
];

$pageTitle = 'Inventory Overview';
$activeNav = 'dashboard';
$useChart = true;
include __DIR__ . '/includes/header.php';
?>

<?php $firstName = trim(explode(' ', trim((string) ($_SESSION['full_name'] ?? '')))[0]); ?>
<section class="brand-banner" aria-label="<?= e(BRAND_NAME) ?>">
    <div class="brand-banner-id">
        <?= brand_mark('lg') ?>
        <div class="min-w-0">
            <h2 class="brand-banner-name"><span class="bn-5ar"><?= e(BRAND_SHORT) ?></span> <span
                    class="bn-word"><?= e(BRAND_WORD) ?></span></h2>
            <p class="brand-banner-tagline"><?= e(BRAND_TAGLINE) ?></p>
        </div>
    </div>
    <div class="brand-banner-meta">
        <div class="brand-banner-date">
            <?php if ($firstName !== ''): ?>Hi, <strong><?= e($firstName) ?></strong> &middot; <?php endif; ?><i
                class="bi bi-calendar3 me-1"></i><?= e(date('l, F j, Y')) ?>
        </div>
        <div class="brand-banner-actions no-print">
            <a href="<?= e(url('sales/pos.php')) ?>" class="btn btn-warning"><i class="bi bi-cart-check me-1"></i>New
                sale</a>
            <a href="<?= e(url('stock/stock_in.php')) ?>" class="btn btn-outline-light"><i
                    class="bi bi-box-arrow-in-down me-1"></i>Stock in</a>
            <a href="<?= e(url('products/index.php')) ?>" class="btn btn-outline-light"><i
                    class="bi bi-box-seam me-1"></i>Products</a>
        </div>
    </div>
</section>

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
    <?php foreach ($cards as $i => [$label, $value, $icon, $valueClass]): ?>
        <?php $tone = $cardTones[$i] ?? 'neutral'; ?>
        <div class="col">
            <div class="card card-outline stat-card stat-card--<?= e($tone) ?> h-100">
                <div class="card-body">
                    <div class="stat-icon stat-icon--<?= e($tone) ?>"><i class="bi bi-<?= e($icon) ?>"></i></div>
                    <div class="stat-value <?= e($valueClass) ?>"><?= number_format($value) ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card card-outline h-100">
            <div class="card-header">Stock by category (units)</div>
            <div class="card-body">
                <?php if (!$chartRows || array_sum($chartValues) === 0): ?>
                    <?= empty_state('bar-chart', 'Nothing to chart yet', 'Add products and record stock to see this chart.') ?>
                <?php else: ?>
                    <div class="chart-wrap chart-wrap--tall">
                        <canvas id="stockChart" data-labels="<?= e(json_encode($chartLabels)) ?>"
                            data-values="<?= e(json_encode($chartValues)) ?>"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card card-outline h-100">
            <div class="card-header">Stock status</div>
            <div class="card-body">
                <?php if ($healthTotal === 0): ?>
                    <?= empty_state('pie-chart', 'No products yet', 'Add products to see how your stock is doing.') ?>
                <?php else: ?>
                    <div class="status-panel">
                        <div class="donut-wrap">
                            <canvas id="statusChart" data-labels="<?= e(json_encode(array_column($healthParts, 1))) ?>"
                                data-values="<?= e(json_encode(array_map('intval', array_column($healthParts, 2)))) ?>"></canvas>
                            <div class="donut-center">
                                <strong><?= number_format($healthTotal) ?></strong><span>products</span></div>
                        </div>
                        <ul class="health-legend health-legend--stacked list-unstyled mb-0">
                            <?php foreach ($healthParts as [$key, $partLabel, $count]): ?>
                                <li>
                                    <span class="health-dot health-seg--<?= e($key) ?>"></span>
                                    <span class="health-name"><?= e($partLabel) ?></span>
                                    <strong><?= number_format($count) ?></strong>
                                    <span class="text-muted">(<?= (int) round($count / $healthTotal * 100) ?>%)</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Products needing attention</span>
        <a href="<?= e(url('products/index.php?status=low')) ?>" class="small">View all low stock</a>
    </div>
    <?php if (!$attention): ?>
        <?= empty_state('check2-circle', 'All stocked up', 'No product is at or below its minimum stock level.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-end">Stock</th>
                        <th class="text-end">Minimum</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attention as $p): ?>
                        <tr class="<?= (int) $p['quantity'] === 0 ? 'stock-row-out' : 'stock-row-low' ?>">
                            <td><a class="fw-medium"
                                    href="<?= e(url('products/view.php?id=' . $p['id'])) ?>"><?= e($p['name']) ?></a>
                                <div class="small text-muted"><?= e($p['sku']) ?></div>
                            </td>
                            <td><?= e($p['category_name']) ?></td>
                            <td class="text-end <?= (int) $p['quantity'] === 0 ? 'qty-out' : '' ?>"><?= (int) $p['quantity'] ?>
                            </td>
                            <td class="text-end"><?= (int) $p['min_stock'] ?></td>
                            <td><?= status_badge((int) $p['quantity'], (int) $p['min_stock']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card card-outline">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Recent stock movements</span>
        <a href="<?= e(url('stock/history.php')) ?>" class="small">View full history</a>
    </div>
    <?php if (!$recent): ?>
        <?= empty_state('clock-history', 'No transactions yet', 'Stock-in and stock-out records will appear here.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th class="text-end">Qty</th>
                        <th>Reference</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $t): ?>
                        <tr>
                            <td class="text-nowrap"><?= e(format_datetime($t['transaction_date'])) ?></td>
                            <td class="fw-medium"><?= e($t['product_name']) ?></td>
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