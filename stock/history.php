<?php
// Inventory history: read-only audit trail. There is no edit or delete here on purpose.
require_once __DIR__ . '/../includes/auth.php';
require_login();

$q = input('q', 'GET');
$productId = (int) ($_GET['product'] ?? 0);
$type = $_GET['type'] ?? '';
$from = input('from', 'GET');
$to = input('to', 'GET');
if (!in_array($type, ['in', 'out'], true)) {
    $type = '';
}
if ($from !== '' && !valid_date($from)) {
    $from = '';
}
if ($to !== '' && !valid_date($to)) {
    $to = '';
}

$where = [];
$types = '';
$params = [];
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR t.reference_no LIKE ?)';
    $types .= 'sss';
    array_push($params, like_pattern($q), like_pattern($q), like_pattern($q));
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
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) db_val($conn, "SELECT COUNT(*) FROM stock_transactions t JOIN products p ON p.id = t.product_id $whereSql", $types, $params);
$pg = paginate($total, 15, (int) ($_GET['page'] ?? 1));

$rows = db_all(
    $conn,
    "SELECT t.*, p.name AS product_name, p.sku, u.full_name AS user_name
     FROM stock_transactions t
     JOIN products p ON p.id = t.product_id
     LEFT JOIN users u ON u.id = t.created_by
     $whereSql
     ORDER BY t.transaction_date DESC, t.id DESC
     LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$pg['per_page'], $pg['offset']])
);
$products = db_all($conn, 'SELECT id, name, sku FROM products ORDER BY name');
$filtered = ($q !== '' || $productId > 0 || $type !== '' || $from !== '' || $to !== '');

$pageTitle = 'Inventory history';
$activeNav = 'history';
include __DIR__ . '/../includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="q" class="form-label">Search</label>
                <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Product or reference">
            </div>
            <div class="col-md-3">
                <label for="product" class="form-label">Product</label>
                <select class="form-select" id="product" name="product">
                    <option value="0">All products</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"<?= selected($productId, $p['id']) ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All</option>
                    <option value="in"<?= selected($type, 'in') ?>>Stock in</option>
                    <option value="out"<?= selected($type, 'out') ?>>Stock out</option>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label for="from" class="form-label">From</label>
                <input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>">
            </div>
            <div class="col-md-2 col-6">
                <label for="to" class="form-label">To</label>
                <input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($filtered): ?><a href="<?= e(url('stock/history.php')) ?>" class="btn btn-light border">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <?php if (!$rows): ?>
        <?= $filtered
            ? empty_state('search', 'No transactions match your filters', 'Try a wider date range or reset the filters.')
            : empty_state('clock-history', 'No stock history yet', 'Stock-in and stock-out records will be listed here.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Date</th><th>Product</th><th>Type</th><th class="text-end">Qty</th><th class="text-end">Before → after</th><th>Reference</th><th>Reason</th><th>By</th><th>Remarks</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $t): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(format_datetime($t['transaction_date'])) ?></td>
                        <td><a href="<?= e(url('products/view.php?id=' . $t['product_id'])) ?>"><?= e($t['product_name']) ?></a><div class="small text-muted"><?= e($t['sku']) ?></div></td>
                        <td><?= type_badge($t['transaction_type']) ?></td>
                        <td class="text-end"><?= signed_quantity($t['transaction_type'], $t['quantity']) ?></td>
                        <td class="text-end text-nowrap text-muted"><?= (int) $t['stock_before'] ?> → <?= (int) $t['stock_after'] ?></td>
                        <td><?= e($t['reference_no']) ?></td>
                        <td><?= e($t['reason']) ?></td>
                        <td><?= e($t['user_name'] ?? '—') ?></td>
                        <td class="small text-muted"><?= e($t['remarks'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="text-muted small"><?= e(page_summary($pg)) ?></span>
            <?= render_pagination($pg) ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
