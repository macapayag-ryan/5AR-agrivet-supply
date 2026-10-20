<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$q = input('q', 'GET');
$status = $_GET['status'] ?? '';
$method = $_GET['method'] ?? '';
$from = input('from', 'GET');
$to = input('to', 'GET');
if (!in_array($status, ['completed', 'cancelled'], true)) {
    $status = '';
}
if (!in_array($method, ['cash', 'gcash'], true)) {
    $method = '';
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
    $where[] = '(s.receipt_number LIKE ? OR s.payment_reference LIKE ? OR EXISTS (SELECT 1 FROM sale_items x WHERE x.sale_id = s.id AND x.product_name LIKE ?))';
    $types .= 'sss';
    array_push($params, like_pattern($q), like_pattern($q), like_pattern($q));
}
if ($status !== '') {
    $where[] = 's.status = ?';
    $types .= 's';
    $params[] = $status;
}
if ($method !== '') {
    $where[] = 's.payment_method = ?';
    $types .= 's';
    $params[] = $method;
}
if ($from !== '') {
    $where[] = 's.created_at >= ?';
    $types .= 's';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 's.created_at <= ?';
    $types .= 's';
    $params[] = $to . ' 23:59:59';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) db_val($conn, "SELECT COUNT(*) FROM sales s $whereSql", $types, $params);
$pg = paginate($total, 15, (int) ($_GET['page'] ?? 1));

$rows = db_all(
    $conn,
    "SELECT s.id, s.receipt_number, s.total_amount, s.amount_paid, s.change_amount, s.payment_method, s.payment_reference, s.status, s.created_at,
            u.full_name AS cashier,
            (SELECT GROUP_CONCAT(CONCAT(si.product_name, ' x ', CASE WHEN si.unit_name IS NULL
                 THEN TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM si.quantity))
                 ELSE CONCAT(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM si.unit_qty)), ' ', si.unit_name) END) ORDER BY si.id SEPARATOR ', ')
             FROM sale_items si WHERE si.sale_id = s.id) AS items_summary
     FROM sales s
     LEFT JOIN users u ON u.id = s.created_by
     $whereSql
     ORDER BY s.created_at DESC, s.id DESC
     LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$pg['per_page'], $pg['offset']])
);
$filtered = ($q !== '' || $status !== '' || $method !== '' || $from !== '' || $to !== '');

$pageTitle = 'Sales history';
$activeNav = 'sales_history';
$pageActions = '<a href="' . e(url('sales/pos.php')) . '" class="btn btn-primary btn-sm"><i class="bi bi-cart-plus me-1"></i>New sale</a>';
include __DIR__ . '/../includes/header.php';
?>
<link href="<?= e(url('assets/css/sales.css')) ?>" rel="stylesheet">

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="q" class="form-label">Search</label>
                <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                    placeholder="Receipt, GCash ref. or product">
            </div>
            <div class="col-md-2">
                <label for="method" class="form-label">Payment</label>
                <select class="form-select" id="method" name="method">
                    <option value="">All</option>
                    <option value="cash" <?= selected($method, 'cash') ?>>Cash</option>
                    <option value="gcash" <?= selected($method, 'gcash') ?>>GCash</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <option value="completed" <?= selected($status, 'completed') ?>>Completed</option>
                    <option value="cancelled" <?= selected($status, 'cancelled') ?>>Cancelled</option>
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
            <div class="col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($filtered): ?><a href="<?= e(url('sales/history.php')) ?>"
                        class="btn btn-light border">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <?php if (!$rows): ?>
        <?= $filtered
            ? empty_state('search', 'No sales match your filters', 'Try a wider date range or reset the filters.')
            : empty_state(
                'receipt',
                'No sales recorded yet',
                'Completed sales from the point of sale will be listed here.',
                '<a href="' . e(url('sales/pos.php')) . '" class="btn btn-primary btn-sm">Make a sale</a>'
            ) ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Date</th>
                        <th>Products sold</th>
                        <th>Payment</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Change</th>
                        <th>Status</th>
                        <th>Cashier</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $s): ?>
                        <?php $void = ($s['status'] === 'cancelled'); ?>
                        <tr>
                            <td class="text-nowrap"><a
                                    href="<?= e(url('sales/receipt.php?id=' . $s['id'])) ?>"><?= e($s['receipt_number']) ?></a>
                            </td>
                            <td class="text-nowrap"><?= e(format_datetime($s['created_at'])) ?></td>
                            <td class="small"><?= e($s['items_summary'] ?? '') ?></td>
                            <td class="text-nowrap">
                                <?php if ($s['payment_method'] === 'gcash'): ?>
                                    <span class="badge text-bg-primary">GCash</span>
                                    <?php if (!empty($s['payment_reference'])): ?>
                                        <div class="small text-muted"><?= e($s['payment_reference']) ?></div><?php endif; ?>
                                <?php else: ?>
                                    <span class="badge text-bg-light border">Cash</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap<?= $void ? ' text-decoration-line-through text-muted' : '' ?>">
                                <?= e(money($s['total_amount'])) ?>
                            </td>
                            <td class="text-end text-nowrap<?= $void ? ' text-muted' : '' ?>"><?= e(money($s['amount_paid'])) ?>
                            </td>
                            <td class="text-end text-nowrap<?= $void ? ' text-muted' : '' ?>">
                                <?= e(money($s['change_amount'])) ?>
                            </td>
                            <td><?= $void ? '<span class="badge text-bg-secondary">Cancelled</span>' : '<span class="badge text-bg-success">Completed</span>' ?>
                            </td>
                            <td><?= e($s['cashier'] ?? '—') ?></td>
                            <td class="text-end text-nowrap"><a href="<?= e(url('sales/receipt.php?id=' . $s['id'])) ?>"
                                    class="btn btn-light btn-sm border"><i class="bi bi-printer me-1"></i>Receipt</a></td>
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