<?php
// Activity log viewer (administrators only, read-only).
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$q = input('q', 'GET');
$where = '';
$types = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE (l.action LIKE ? OR l.details LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)';
    $types = 'ssss';
    $params = array_fill(0, 4, like_pattern($q));
}

$total = (int) db_val($conn, "SELECT COUNT(*) FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id $where", $types, $params);
$pg = paginate($total, 20, (int) ($_GET['page'] ?? 1));
$logs = db_all(
    $conn,
    "SELECT l.*, u.full_name, u.username
     FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
     $where
     ORDER BY l.created_at DESC, l.id DESC
     LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$pg['per_page'], $pg['offset']])
);

$pageTitle = 'Activity log';
$activeNav = 'logs';
include __DIR__ . '/../includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label for="q" class="form-label">Search</label>
                <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Action, details or user">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($q !== ''): ?><a href="<?= e(url('users/logs.php')) ?>" class="btn btn-light border">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <?php if (!$logs): ?>
        <?= empty_state('journal-text', 'No activity found', $q !== '' ? 'Try a different search.' : 'Important actions will be recorded here.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Date</th><th>User</th><th>Action</th><th>Details</th><th>IP address</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(format_datetime($l['created_at'])) ?></td>
                        <td><?= e($l['full_name'] ?? 'System / unknown') ?><div class="small text-muted"><?= e($l['username'] ?? '') ?></div></td>
                        <td><span class="badge text-bg-light border"><?= e($l['action']) ?></span></td>
                        <td><?= e($l['details']) ?></td>
                        <td class="text-muted"><?= e($l['ip_address'] ?? '') ?></td>
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
