<?php
/**
 * reports/index.php
 * Inventory report and stock transaction report with filters,
 * a print-friendly layout and CSV export (opens in Excel).
 */
require_once __DIR__ . '/../includes/auth.php';
require_login();

$report = (($_GET['report'] ?? '') === 'transactions') ? 'transactions' : 'inventory';
$categoryId = (int) ($_GET['category'] ?? 0);
$productId = (int) ($_GET['product'] ?? 0);
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$from = input('from', 'GET');
$to = input('to', 'GET');
if (!in_array($status, ['ok', 'low', 'out'], true)) {
    $status = '';
}
if (!in_array($type, ['in', 'out'], true)) {
    $type = '';
}
if ($from !== '' && !valid_date($from)) {
    $from = '';
}
if ($to !== '' && !valid_date($to)) {
    $to = '';
}
$export = (($_GET['export'] ?? '') === 'csv');

$categories = db_all($conn, 'SELECT id, name FROM categories ORDER BY name');
$productList = db_all($conn, 'SELECT id, name FROM products ORDER BY name');

/*
 * Build $headers (column titles), $rows (plain values), $numeric (right-aligned
 * column indexes), $footer (optional totals row) and $summary (text).
 * The same data is used for the screen table, the print view and the CSV file.
 */
$headers = [];
$rows = [];
$numeric = [];
$footer = null;
$summary = '';
$title = '';

if ($report === 'inventory') {
    $title = 'Inventory report';
    $where = [];
    $types = '';
    $params = [];
    if ($categoryId > 0) {
        $where[] = 'p.category_id = ?';
        $types .= 'i';
        $params[] = $categoryId;
    }
    if ($status === 'out') {
        $where[] = 'p.quantity = 0';
    } elseif ($status === 'low') {
        $where[] = 'p.quantity > 0 AND p.quantity <= p.min_stock';
    } elseif ($status === 'ok') {
        $where[] = 'p.quantity > p.min_stock';
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $data = db_all(
        $conn,
        "SELECT p.sku, p.name, c.name AS category_name, s.name AS supplier_name,
                p.quantity, p.min_stock, p.cost_price, p.selling_price
         FROM products p
         JOIN categories c ON c.id = p.category_id
         LEFT JOIN suppliers s ON s.id = p.supplier_id
         $whereSql
         ORDER BY c.name, p.name",
        $types,
        $params
    );

    $headers = ['Code', 'Product', 'Category', 'Supplier', 'Stock', 'Minimum', 'Status', 'Cost price', 'Stock value'];
    $numeric = [4, 5, 7, 8];
    $totalUnits = 0;
    $totalValue = 0.0;
    foreach ($data as $r) {
        $value = $r['quantity'] * $r['cost_price'];
        $totalUnits += (int) $r['quantity'];
        $totalValue += $value;
        $rows[] = [
            $r['sku'], $r['name'], $r['category_name'], $r['supplier_name'] ?? '',
            (int) $r['quantity'], (int) $r['min_stock'],
            stock_status_label(stock_status((int) $r['quantity'], (int) $r['min_stock'])),
            number_format($r['cost_price'], 2), number_format($value, 2),
        ];
    }
    $footer = ['', 'Total (' . count($rows) . ' products)', '', '', $totalUnits, '', '', '', number_format($totalValue, 2)];
    $summary = count($rows) . ' product(s), ' . number_format($totalUnits) . ' unit(s) in stock, total value at cost ' . money($totalValue);
} else {
    $title = 'Stock transactions report';
    $where = [];
    $types = '';
    $params = [];
    if ($from !== '') {
        $where[] = 't.transaction_date >= ?';
        $types .= 's';
        $params[] = $from . ' 00:00:00';
    }
    if ($to !== '') {
        $where[] = 't.transaction_date <= ?';
        $types .= 's';
        $params[] = $to . ' 23:59:59';
    }
    if ($categoryId > 0) {
        $where[] = 'p.category_id = ?';
        $types .= 'i';
        $params[] = $categoryId;
    }
    if ($productId > 0) {
        $where[] = 't.product_id = ?';
        $types .= 'i';
        $params[] = $productId;
    }
    if ($type !== '') {
        $where[] = 't.transaction_type = ?';
        $types .= 's';
        $params[] = $type;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $data = db_all(
        $conn,
        "SELECT t.transaction_date, t.transaction_type, t.quantity, t.reference_no, t.reason, t.remarks,
                p.sku, p.name AS product_name, c.name AS category_name, u.full_name AS user_name
         FROM stock_transactions t
         JOIN products p ON p.id = t.product_id
         JOIN categories c ON c.id = p.category_id
         LEFT JOIN users u ON u.id = t.created_by
         $whereSql
         ORDER BY t.transaction_date DESC, t.id DESC
         LIMIT 5000",
        $types,
        $params
    );

    $headers = ['Date', 'Code', 'Product', 'Category', 'Type', 'Quantity', 'Reference', 'Reason', 'By'];
    $numeric = [5];
    $totalIn = 0;
    $totalOut = 0;
    foreach ($data as $r) {
        if ($r['transaction_type'] === 'in') {
            $totalIn += (int) $r['quantity'];
        } else {
            $totalOut += (int) $r['quantity'];
        }
        $rows[] = [
            format_datetime($r['transaction_date']), $r['sku'], $r['product_name'], $r['category_name'],
            $r['transaction_type'] === 'in' ? 'Stock in' : 'Stock out', (int) $r['quantity'],
            $r['reference_no'], $r['reason'], $r['user_name'] ?? '',
        ];
    }
    $summary = count($rows) . ' transaction(s): ' . number_format($totalIn) . ' unit(s) in, ' . number_format($totalOut) . ' unit(s) out';
}

/* ---- CSV export ---- */
if ($export) {
    log_activity($conn, 'report_exported', "$title exported as CSV");
    $safe = function ($v) {
        // Stop spreadsheet formulas ("=", "+", "-", "@") typed into text fields from running.
        $v = (string) $v;
        return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false && !is_numeric($v)) ? "'" . $v : $v;
    };
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $report . '_report_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                       // UTF-8 BOM so Excel shows symbols correctly
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        fputcsv($out, array_map($safe, $r));
    }
    if ($footer) {
        fputcsv($out, array_map($safe, $footer));
    }
    fclose($out);
    exit;
}

// Text describing the filters, shown at the top of the printed report.
$filterText = [];
if ($categoryId > 0) {
    foreach ($categories as $c) {
        if ((int) $c['id'] === $categoryId) {
            $filterText[] = 'Category: ' . $c['name'];
        }
    }
}
if ($report === 'inventory' && $status !== '') {
    $filterText[] = 'Status: ' . stock_status_label($status);
}
if ($report === 'transactions') {
    if ($productId > 0) {
        foreach ($productList as $p) {
            if ((int) $p['id'] === $productId) {
                $filterText[] = 'Product: ' . $p['name'];
            }
        }
    }
    if ($type !== '') {
        $filterText[] = 'Type: ' . ($type === 'in' ? 'Stock in' : 'Stock out');
    }
    if ($from !== '' || $to !== '') {
        $filterText[] = 'Period: ' . ($from !== '' ? format_date($from) : 'start') . ' to ' . ($to !== '' ? format_date($to) : 'today');
    }
}

$exportQuery = http_build_query(array_merge($_GET, ['export' => 'csv']));

$pageTitle = 'Reports';
$activeNav = 'reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="reportType" class="form-label">Report</label>
                <select class="form-select" id="reportType" name="report">
                    <option value="inventory"<?= selected($report, 'inventory') ?>>Inventory (current stock)</option>
                    <option value="transactions"<?= selected($report, 'transactions') ?>>Stock in / stock out</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="0">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"<?= selected($categoryId, $c['id']) ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 only-inventory">
                <label for="status" class="form-label">Stock status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <option value="ok"<?= selected($status, 'ok') ?>>In stock</option>
                    <option value="low"<?= selected($status, 'low') ?>>Low stock</option>
                    <option value="out"<?= selected($status, 'out') ?>>Out of stock</option>
                </select>
            </div>
            <div class="col-md-3 only-transactions">
                <label for="product" class="form-label">Product</label>
                <select class="form-select" id="product" name="product">
                    <option value="0">All products</option>
                    <?php foreach ($productList as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"<?= selected($productId, $p['id']) ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 only-transactions">
                <label for="type" class="form-label">Transaction type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Stock in and out</option>
                    <option value="in"<?= selected($type, 'in') ?>>Stock in only</option>
                    <option value="out"<?= selected($type, 'out') ?>>Stock out only</option>
                </select>
            </div>
            <div class="col-md-3 col-6 only-transactions">
                <label for="from" class="form-label">From</label>
                <input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>">
            </div>
            <div class="col-md-3 col-6 only-transactions">
                <label for="to" class="form-label">To</label>
                <input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>">
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">Generate report</button>
                <a href="<?= e(url('reports/index.php')) ?>" class="btn btn-light border">Reset</a>
                <span class="ms-md-auto d-flex gap-2">
                    <a href="?<?= e($exportQuery) ?>" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i>Export CSV</a>
                    <button type="button" class="btn btn-outline-secondary" data-print><i class="bi bi-printer me-1"></i>Print</button>
                </span>
            </div>
        </form>
    </div>
</div>

<div class="card report-sheet">
    <div class="card-body pb-0">
        <h2 class="h5 mb-1"><?= e($title) ?></h2>
        <div class="text-muted small">Generated <?= e(date('M d, Y h:i A')) ?> by <?= e($_SESSION['full_name']) ?><?= $filterText ? ' · ' . e(implode(' · ', $filterText)) : '' ?></div>
        <div class="mt-2 mb-3"><?= e($summary) ?></div>
    </div>

    <?php if (!$rows): ?>
        <?= empty_state('file-earmark-bar-graph', 'No data for this report', 'Change the filters or record some stock movements, then generate it again.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 report-table">
                <thead>
                    <tr><?php foreach ($headers as $i => $h): ?><th<?= in_array($i, $numeric, true) ? ' class="text-end"' : '' ?>><?= e($h) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr><?php foreach ($r as $i => $cell): ?><td<?= in_array($i, $numeric, true) ? ' class="text-end"' : '' ?>><?= e($cell) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
                </tbody>
                <?php if ($footer): ?>
                <tfoot>
                    <tr class="fw-semibold"><?php foreach ($footer as $i => $cell): ?><td<?= in_array($i, $numeric, true) ? ' class="text-end"' : '' ?>><?= e($cell) ?></td><?php endforeach; ?></tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php if ($report === 'transactions' && count($rows) >= 5000): ?>
            <div class="card-footer small text-muted">Only the 5,000 most recent matching records are shown. Narrow the date range for a complete report.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
