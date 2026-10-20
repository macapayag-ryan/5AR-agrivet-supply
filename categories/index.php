<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$form = ['id' => 0, 'name' => '', 'description' => ''];
$errors = [];

// Edit mode: ?edit=ID loads the category into the form.
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $row = db_row($conn, 'SELECT id, name, description FROM categories WHERE id = ?', 'i', [$editId]);
    if (!$row) {
        flash('danger', 'Category not found.');
        redirect('categories/index.php');
    }
    $form = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = input('action');

    if ($action === 'delete') {
        if (!is_admin()) {
            flash('danger', 'Only administrators can delete categories.');
            redirect('categories/index.php');
        }
        $id = (int) input('id');
        $cat = db_row($conn, 'SELECT name FROM categories WHERE id = ?', 'i', [$id]);
        if (!$cat) {
            flash('danger', 'Category not found.');
        } elseif ((int) db_val($conn, 'SELECT COUNT(*) FROM products WHERE category_id = ?', 'i', [$id]) > 0) {
            flash('warning', 'Cannot delete "' . $cat['name'] . '" because products still use it. Move or delete those products first.');
        } else {
            db_exec($conn, 'DELETE FROM categories WHERE id = ?', 'i', [$id]);
            log_activity($conn, 'category_deleted', "Deleted category #$id {$cat['name']}");
            flash('success', 'Category "' . $cat['name'] . '" was deleted.');
        }
        redirect('categories/index.php');
    }

    if ($action === 'save') {
        $form = ['id' => (int) input('id'), 'name' => input('name'), 'description' => input('description')];

        if ($form['name'] === '') {
            $errors['name'] = 'Category name is required.';
        } elseif (mb_strlen($form['name']) > 100) {
            $errors['name'] = 'Category name must be 100 characters or fewer.';
        } elseif (db_val($conn, 'SELECT id FROM categories WHERE name = ? AND id <> ?', 'si', [$form['name'], $form['id']])) {
            $errors['name'] = 'A category with this name already exists.';
        }
        if (mb_strlen($form['description']) > 255) {
            $errors['description'] = 'Description must be 255 characters or fewer.';
        }

        if (!$errors) {
            try {
                if ($form['id'] > 0) {
                    db_exec($conn, 'UPDATE categories SET name = ?, description = ? WHERE id = ?', 'ssi', [$form['name'], $form['description'], $form['id']]);
                    log_activity($conn, 'category_updated', "Updated category #{$form['id']} {$form['name']}");
                    flash('success', 'Category updated.');
                } else {
                    db_exec($conn, 'INSERT INTO categories (name, description) VALUES (?, ?)', 'ss', [$form['name'], $form['description']]);
                    log_activity($conn, 'category_created', "Created category {$form['name']}");
                    flash('success', 'Category added.');
                }
                redirect('categories/index.php');
            } catch (mysqli_sql_exception $ex) {
                if ($ex->getCode() === 1062) {
                    $errors['name'] = 'A category with this name already exists.';
                } else {
                    error_log('Save category failed: ' . $ex->getMessage());
                    $errors['name'] = 'The category could not be saved. Please try again.';
                }
            }
        }
    }
}

$categories = db_all($conn, 'SELECT c.id, c.name, c.description, COUNT(p.id) AS product_count
                             FROM categories c LEFT JOIN products p ON p.category_id = c.id
                             GROUP BY c.id, c.name, c.description ORDER BY c.name');
$isEditing = $form['id'] > 0;

$pageTitle = 'Categories';
$activeNav = 'categories';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8 order-2 order-lg-1">
        <div class="card">
            <?php if (!$categories): ?>
                <?= empty_state('tags', 'No categories yet', 'Add a category using the form to start organising your products.') ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Name</th><th>Description</th><th class="text-end">Products</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($c['name']) ?></td>
                                <td class="text-muted"><?= e($c['description'] ?? '') ?></td>
                                <td class="text-end">
                                    <a href="<?= e(url('products/index.php?category=' . $c['id'])) ?>"><?= (int) $c['product_count'] ?></a>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="?edit=<?= (int) $c['id'] ?>" class="btn btn-light btn-sm border" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <?php if (is_admin()): ?>
                                        <?php if ((int) $c['product_count'] === 0): ?>
                                            <form method="post" class="d-inline js-confirm" data-confirm="Delete category &quot;<?= e($c['name']) ?>&quot;?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                                <button type="submit" class="btn btn-light btn-sm border text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-light btn-sm border" disabled title="In use by products"><i class="bi bi-trash"></i></button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4 order-1 order-lg-2">
        <div class="card">
            <div class="card-header"><?= $isEditing ? 'Edit category' : 'Add category' ?></div>
            <div class="card-body">
                <form method="post" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name *</label>
                        <input type="text" class="form-control<?= field_class($errors, 'name') ?>" id="name" name="name" value="<?= e($form['name']) ?>" maxlength="100" required>
                        <?= field_error($errors, 'name') ?: '<div class="invalid-feedback">Enter a category name.</div>' ?>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control<?= field_class($errors, 'description') ?>" id="description" name="description" rows="3" maxlength="255"><?= e($form['description'] ?? '') ?></textarea>
                        <?= field_error($errors, 'description') ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><?= $isEditing ? 'Save changes' : 'Add category' ?></button>
                        <?php if ($isEditing): ?><a href="<?= e(url('categories/index.php')) ?>" class="btn btn-light border">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
