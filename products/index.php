<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/product_image.php';
require_login();

$q = input('q', 'GET');
$categoryId = (int) ($_GET['category'] ?? 0);
$status = $_GET['status'] ?? '';
if (!in_array($status, ['ok', 'low', 'out'], true)) {
    $status = '';
}

$where = [];
$types = '';
$params = [];

if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
    $types .= 'ss';
    $params[] = like_pattern($q);
    $params[] = like_pattern($q);
}
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

$total = (int) db_val($conn, "SELECT COUNT(*) FROM products p $whereSql", $types, $params);
$pg = paginate($total, ITEMS_PER_PAGE, (int) ($_GET['page'] ?? 1));

$products = db_all(
    $conn,
    "SELECT p.id, p.sku, p.name, p.image, p.cost_price, p.selling_price, p.quantity, p.min_stock,
            c.name AS category_name
     FROM products p
     JOIN categories c ON c.id = p.category_id
     $whereSql
     ORDER BY p.name ASC
     LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$pg['per_page'], $pg['offset']])
);
$categories = db_all($conn, 'SELECT id, name FROM categories ORDER BY name');
$filtered = ($q !== '' || $categoryId > 0 || $status !== '');

$pageTitle = 'Products';
$activeNav = 'products';
$pageActions = '<a href="' . e(url('products/create.php')) . '" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add product</a>';
include __DIR__ . '/../includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="q" class="form-label">Search</label>
                <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                    placeholder="Product name or code">
            </div>
            <div class="col-md-3">
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="0">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= selected($categoryId, $c['id']) ?>><?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Stock status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <option value="ok" <?= selected($status, 'ok') ?>>In stock</option>
                    <option value="low" <?= selected($status, 'low') ?>>Low stock</option>
                    <option value="out" <?= selected($status, 'out') ?>>Out of stock</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                <?php if ($filtered): ?><a href="<?= e(url('products/index.php')) ?>"
                        class="btn btn-light border">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <?php if (!$products): ?>
        <?php if ($filtered): ?>
            <?= empty_state('search', 'No products match your filters', 'Try a different search or reset the filters.') ?>
        <?php else: ?>
            <?= empty_state(
                'box-seam',
                'No products yet',
                'Add your first product to start tracking stock.',
                '<a href="' . e(url('products/create.php')) . '" class="btn btn-primary btn-sm">Add product</a>'
            ) ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Stock</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <?php $thumb = product_image_url($p['image']); ?>
                        <tr>
                            <td class="text-nowrap"><?= e($p['sku']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($thumb !== ''): ?>
                                        <img src="<?= e($thumb) ?>" alt="" loading="lazy" class="rounded border flex-shrink-0"
                                            style="width:40px;height:40px;object-fit:cover;">
                                    <?php else: ?>
                                        <span
                                            class="rounded border bg-light text-muted d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                            style="width:40px;height:40px;"><i class="bi bi-image"></i></span>
                                    <?php endif; ?>
                                    <a href="<?= e(url('products/view.php?id=' . $p['id'])) ?>"><?= e($p['name']) ?></a>
                                </div>
                            </td>
                            <td><?= e($p['category_name']) ?></td>
                            <td class="text-end"><?= e(money($p['cost_price'])) ?></td>
                            <td class="text-end"><?= e(money($p['selling_price'])) ?></td>
                            <td class="text-end"><?= e(fmt_qty($p['quantity'])) ?> <span class="text-muted small">/ min
                                    <?= (int) $p['min_stock'] ?></span></td>
                            <td><?= status_badge((float) $p['quantity'], (int) $p['min_stock']) ?></td>
                            <td class="text-end text-nowrap">
                                <a href="<?= e(url('products/view.php?id=' . $p['id'])) ?>" class="btn btn-light btn-sm border"
                                    title="View"><i class="bi bi-eye"></i></a>
                                <a href="<?= e(url('products/edit.php?id=' . $p['id'])) ?>" class="btn btn-light btn-sm border"
                                    title="Edit"><i class="bi bi-pencil"></i></a>
                                <?php if (is_admin()): ?>
                                    <form method="post" action="<?= e(url('products/delete.php')) ?>" class="d-inline js-confirm"
                                        data-confirm="Delete product &quot;<?= e($p['name']) ?>&quot;? This cannot be undone.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit" class="btn btn-light btn-sm border text-danger" title="Delete"><i
                                                class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
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