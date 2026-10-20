<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

function period_totals(mysqli $conn, string $start, string $endExclusive): array
{
    $row = db_row(
        $conn,
        "SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total
         FROM sales
         WHERE status = 'completed' AND created_at >= ? AND created_at < ?",
        'ss',
        [$start, $endExclusive]
    );
    return ['count' => (int) $row['cnt'], 'total' => (float) $row['total']];
}

// Show quantities like 50 or 2.5 instead of 50.000 or 2.500
function qty_format($n): string
{
    $s = number_format((float) $n, 3, '.', ',');
    return rtrim(rtrim($s, '0'), '.');
}

// Display only: profit as a percentage of revenue.
function margin_format(float $profit, float $revenue): string
{
    return $revenue > 0 ? number_format($profit / $revenue * 100, 1) . '%' : '—';
}

$today = new DateTime('today');
$tomorrow = (clone $today)->modify('+1 day');
$weekStart = (clone $today)->modify('-' . ((int) $today->format('N') - 1) . ' day');
$monthStart = new DateTime($today->format('Y-m-01'));

$endOfToday = $tomorrow->format('Y-m-d') . ' 00:00:00';
$todayTotals = period_totals($conn, $today->format('Y-m-d') . ' 00:00:00', $endOfToday);
$weekTotals = period_totals($conn, $weekStart->format('Y-m-d') . ' 00:00:00', $endOfToday);
$monthTotals = period_totals($conn, $monthStart->format('Y-m-d') . ' 00:00:00', $endOfToday);

$from = input('from', 'GET');
$to = input('to', 'GET');
if (!valid_date($from)) {
    $from = $monthStart->format('Y-m-d');
}
if (!valid_date($to)) {
    $to = $today->format('Y-m-d');
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$clamped = false;
if ((new DateTime($from))->diff(new DateTime($to))->days > 365) {
    $from = (new DateTime($to))->modify('-365 day')->format('Y-m-d');
    $clamped = true;
}
$rangeStart = $from . ' 00:00:00';
$rangeEnd = (new DateTime($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

$rangeTotals = period_totals($conn, $rangeStart, $rangeEnd);
$rangeItems = (int) db_val(
    $conn,
    "SELECT COALESCE(SUM(si.quantity), 0)
     FROM sale_items si JOIN sales s ON s.id = si.sale_id
     WHERE s.status = 'completed' AND s.created_at >= ? AND s.created_at < ?",
    'ss',
    [$rangeStart, $rangeEnd]
);
$cancelled = db_row(
    $conn,
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total
     FROM sales WHERE status = 'cancelled' AND created_at >= ? AND created_at < ?",
    'ss',
    [$rangeStart, $rangeEnd]
);
$average = $rangeTotals['count'] > 0 ? $rangeTotals['total'] / $rangeTotals['count'] : 0;

$daily = db_all(
    $conn,
    "SELECT DATE(s.created_at) AS day, COUNT(*) AS transactions, SUM(s.total_amount) AS total,
            COALESCE(SUM((SELECT SUM(si.quantity) FROM sale_items si WHERE si.sale_id = s.id)), 0) AS items
     FROM sales s
     WHERE s.status = 'completed' AND s.created_at >= ? AND s.created_at < ?
     GROUP BY DATE(s.created_at)
     ORDER BY day DESC",
    'ss',
    [$rangeStart, $rangeEnd]
);

$best = db_all(
    $conn,
    "SELECT p.sku, p.name, SUM(si.quantity) AS units, SUM(si.line_total) AS revenue
     FROM sale_items si
     JOIN sales s ON s.id = si.sale_id
     JOIN products p ON p.id = si.product_id
     WHERE s.status = 'completed' AND s.created_at >= ? AND s.created_at < ?
     GROUP BY p.id, p.sku, p.name
     ORDER BY units DESC, revenue DESC, p.name ASC
     LIMIT 10",
    'ss',
    [$rangeStart, $rangeEnd]
);

// Profit by product (optionally filtered by product name, e.g. "rice").
// sale_items.quantity is stored in base units (kilos for rice), so
// quantity * cost_price is correct whether sold by the sack or by the kilo.
$productFilter = trim((string) input('product', 'GET'));
$like = '%' . $productFilter . '%';
$profit = db_all(
    $conn,
    "SELECT p.sku, p.name, p.base_unit,
            SUM(si.quantity) AS units,
            SUM(si.line_total) AS revenue,
            SUM(si.quantity * p.cost_price) AS cost,
            SUM(si.line_total - si.quantity * p.cost_price) AS profit
     FROM sale_items si
     JOIN sales s ON s.id = si.sale_id
     JOIN products p ON p.id = si.product_id
     WHERE s.status = 'completed'
       AND s.created_at >= ? AND s.created_at < ?
       AND p.name LIKE ?
     GROUP BY p.id, p.sku, p.name, p.base_unit
     ORDER BY profit DESC",
    'sss',
    [$rangeStart, $rangeEnd, $like]
);
$profitTotals = ['revenue' => 0.0, 'cost' => 0.0, 'profit' => 0.0];
foreach ($profit as $r) {
    $profitTotals['revenue'] += (float) $r['revenue'];
    $profitTotals['cost'] += (float) $r['cost'];
    $profitTotals['profit'] += (float) $r['profit'];
}

$monthlyStart = (new DateTime($today->format('Y-m-01')))->modify('-11 month')->format('Y-m-d') . ' 00:00:00';
$monthly = db_all(
    $conn,
    "SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS ym, COUNT(*) AS transactions, SUM(s.total_amount) AS total,
            COALESCE(SUM((SELECT SUM(si.quantity) FROM sale_items si WHERE si.sale_id = s.id)), 0) AS items
     FROM sales s
     WHERE s.status = 'completed' AND s.created_at >= ? AND s.created_at < ?
     GROUP BY DATE_FORMAT(s.created_at, '%Y-%m')
     ORDER BY ym DESC",
    'ss',
    [$monthlyStart, $endOfToday]
);

$dailyMap = [];
foreach ($daily as $d) {
    $dailyMap[$d['day']] = (float) $d['total'];
}
$chartLabels = [];
$chartValues = [];
for ($d = new DateTime($from); $d->format('Y-m-d') <= $to; $d->modify('+1 day')) {
    $chartLabels[] = $d->format('M d');
    $chartValues[] = $dailyMap[$d->format('Y-m-d')] ?? 0;
}

$periodCards = [
    ['Today', $todayTotals, 'calendar-day'],
    ['This week (Mon–Sun)', $weekTotals, 'calendar-week'],
    ['This month', $monthTotals, 'calendar-month'],
];
$rangeCards = [
    ['Sales in range', money($rangeTotals['total']), 'cash-stack'],
    ['Completed transactions', number_format($rangeTotals['count']), 'receipt'],
    ['Items sold', number_format($rangeItems), 'box-seam'],
    ['Average sale', money($average), 'graph-up'],
];
// Display only: icon colour for each range card above (same order).
$rangeTones = ['green', 'green', 'neutral', 'amber'];

// Display only: scale for the best-seller bars (the list is sorted by units, highest first).
$maxUnits = $best ? max(1.0, (float) $best[0]['units']) : 1.0;

$pageTitle = 'Sales reports';
$activeNav = 'sales_reports';
$useChart = true;
$pageActions = '<button type="button" class="btn btn-light border btn-sm" data-print><i class="bi bi-printer me-1"></i>Print</button>';
include __DIR__ . '/../includes/header.php';
?>
<link href="<?= e(url('assets/css/sales.css')) ?>" rel="stylesheet">

<p class="dash-subtitle mb-3">Track sales, best sellers and profit for your store.</p>

<div class="row row-cols-1 row-cols-md-3 g-3 mb-3">
    <?php foreach ($periodCards as [$label, $t, $icon]): ?>
        <div class="col">
            <div class="card card-outline stat-card stat-card--green h-100">
                <div class="card-body">
                    <div class="stat-icon stat-icon--green"><i class="bi bi-<?= e($icon) ?>"></i></div>
                    <div class="stat-value"><?= e(money($t['total'])) ?></div>
                    <div class="stat-label"><?= e($label) ?> · <?= number_format($t['count']) ?> transaction(s)</div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card card-outline mb-3 no-print">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3 col-6">
                <label for="from" class="form-label">From</label>
                <input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>"
                    max="<?= e($today->format('Y-m-d')) ?>">
            </div>
            <div class="col-md-3 col-6">
                <label for="to" class="form-label">To</label>
                <input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>">
            </div>
            <div class="col-md-3 col-12">
                <label for="product" class="form-label">Product (for profit)</label>
                <input type="text" class="form-control" id="product" name="product" placeholder="e.g. rice"
                    value="<?= e($productFilter) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
                <a href="<?= e(url('sales/reports.php')) ?>" class="btn btn-light border">This month</a>
            </div>
        </form>
        <?php if ($clamped): ?>
            <div class="small text-warning-emphasis mt-2">The range was limited to 366 days.</div>
        <?php endif; ?>
    </div>
</div>

<h2 class="fs-6 text-muted mb-2">Selected range: <?= e(format_date($from)) ?> to <?= e(format_date($to)) ?></h2>
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-2">
    <?php foreach ($rangeCards as $i => [$label, $value, $icon]): ?>
        <?php $tone = $rangeTones[$i] ?? 'green'; ?>
        <div class="col">
            <div class="card card-outline stat-card stat-card--<?= e($tone) ?> h-100">
                <div class="card-body">
                    <div class="stat-icon stat-icon--<?= e($tone) ?>"><i class="bi bi-<?= e($icon) ?>"></i></div>
                    <div class="stat-value"><?= e($value) ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<p class="small text-muted mb-4">
    Totals count completed sales only. Cancelled sales in this range: <?= (int) $cancelled['cnt'] ?>
    (<?= e(money($cancelled['total'])) ?>), not included above.
</p>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card card-outline h-100">
            <div class="card-header">Daily sales</div>
            <?php if (!$daily): ?>
                <?= empty_state('bar-chart', 'No sales in this range', 'Try a different date range.') ?>
            <?php else: ?>
                <div class="card-body pb-0">
                    <div class="chart-wrap">
                        <canvas id="salesChart" data-labels="<?= e(json_encode($chartLabels)) ?>"
                            data-values="<?= e(json_encode($chartValues)) ?>"></canvas>
                    </div>
                </div>
                <div class="table-responsive mt-3">
                    <table class="table table-hover align-middle report-table mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th class="text-end">Transactions</th>
                                <th class="text-end">Items sold</th>
                                <th class="text-end">Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daily as $d): ?>
                                <tr>
                                    <td><?= e(format_date($d['day'])) ?></td>
                                    <td class="text-end"><?= (int) $d['transactions'] ?></td>
                                    <td class="text-end"><?= (int) $d['items'] ?></td>
                                    <td class="text-end"><?= e(money($d['total'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td>Total</td>
                                <td class="text-end"><?= number_format($rangeTotals['count']) ?></td>
                                <td class="text-end"><?= number_format($rangeItems) ?></td>
                                <td class="text-end"><?= e(money($rangeTotals['total'])) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card card-outline h-100">
            <div class="card-header">Best-selling products (by quantity)</div>
            <?php if (!$best): ?>
                <?= empty_state('trophy', 'No product sales yet', 'Best sellers appear once sales are recorded in this range.') ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle report-table mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-end">Units</th>
                                <th class="text-end">Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($best as $b): ?>
                                <tr>
                                    <td><?= e($b['name']) ?>
                                        <div class="small text-muted"><?= e($b['sku']) ?></div>
                                        <div class="rank-bar" aria-hidden="true"><span
                                                style="width: <?= (int) round((float) $b['units'] / $maxUnits * 100) ?>%;"></span>
                                        </div>
                                    </td>
                                    <td class="text-end"><?= (int) $b['units'] ?></td>
                                    <td class="text-end"><?= e(money($b['revenue'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card card-outline mb-3">
    <div class="card-header">
        Profit by product<?= $productFilter !== '' ? ' · “' . e($productFilter) . '”' : '' ?>
    </div>
    <?php if (!$profit): ?>
        <?= empty_state('cash-coin', 'No matching sales', 'Try a different product name or date range.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle report-table mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-end">Qty sold</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Profit</th>
                        <th class="text-end">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profit as $r): ?>
                        <tr>
                            <td><?= e($r['name']) ?>
                                <div class="small text-muted"><?= e($r['sku']) ?></div>
                            </td>
                            <td class="text-end"><?= e(qty_format($r['units'])) ?>         <?= e($r['base_unit']) ?></td>
                            <td class="text-end"><?= e(money($r['revenue'])) ?></td>
                            <td class="text-end"><?= e(money($r['cost'])) ?></td>
                            <td class="text-end fw-semibold <?= (float) $r['profit'] < 0 ? 'profit-neg' : 'profit-pos' ?>">
                                <?= e(money($r['profit'])) ?></td>
                            <td class="text-end"><?= e(margin_format((float) $r['profit'], (float) $r['revenue'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td>Total</td>
                        <td></td>
                        <td class="text-end"><?= e(money($profitTotals['revenue'])) ?></td>
                        <td class="text-end"><?= e(money($profitTotals['cost'])) ?></td>
                        <td class="text-end <?= $profitTotals['profit'] < 0 ? 'profit-neg' : 'profit-pos' ?>">
                            <?= e(money($profitTotals['profit'])) ?></td>
                        <td class="text-end"><?= e(margin_format($profitTotals['profit'], $profitTotals['revenue'])) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer small text-muted">
            Cost uses each product's current cost price. Completed sales only.
        </div>
    <?php endif; ?>
</div>

<div class="card card-outline">
    <div class="card-header">Monthly summary (last 12 months)</div>
    <?php if (!$monthly): ?>
        <?= empty_state('calendar-month', 'No monthly data yet', 'Monthly totals appear once sales are recorded.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle report-table mb-0">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-end">Transactions</th>
                        <th class="text-end">Items sold</th>
                        <th class="text-end">Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly as $m): ?>
                        <tr>
                            <td><?= e(date('F Y', strtotime($m['ym'] . '-01'))) ?></td>
                            <td class="text-end"><?= (int) $m['transactions'] ?></td>
                            <td class="text-end"><?= (int) $m['items'] ?></td>
                            <td class="text-end"><?= e(money($m['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($daily): ?>
    <script src="<?= e(url('assets/js/sales-reports.js')) ?>" defer></script><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>