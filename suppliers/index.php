<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$form = ['id' => 0, 'name' => '', 'contact_person' => '', 'phone' => '', 'email' => '', 'address' => ''];
$errors = [];

$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $row = db_row($conn, 'SELECT id, name, contact_person, phone, email, address FROM suppliers WHERE id = ?', 'i', [$editId]);
    if (!$row) {
        flash('danger', 'Supplier not found.');
        redirect('suppliers/index.php');
    }
    $form = array_map(fn($v) => $v ?? '', $row);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = input('action');

    if ($action === 'delete') {
        if (!is_admin()) {
            flash('danger', 'Only administrators can delete suppliers.');
            redirect('suppliers/index.php');
        }
        $id = (int) input('id');
        $sup = db_row($conn, 'SELECT name FROM suppliers WHERE id = ?', 'i', [$id]);
        if (!$sup) {
            flash('danger', 'Supplier not found.');
        } else {
            $products = (int) db_val($conn, 'SELECT COUNT(*) FROM products WHERE supplier_id = ?', 'i', [$id]);
            $history = (int) db_val($conn, 'SELECT COUNT(*) FROM stock_transactions WHERE supplier_id = ?', 'i', [$id]);
            if ($products > 0 || $history > 0) {
                flash('warning', 'Cannot delete "' . $sup['name'] . '" because it is linked to ' . $products . ' product(s) and ' . $history . ' stock record(s).');
            } else {
                db_exec($conn, 'DELETE FROM suppliers WHERE id = ?', 'i', [$id]);
                log_activity($conn, 'supplier_deleted', "Deleted supplier #$id {$sup['name']}");
                flash('success', 'Supplier "' . $sup['name'] . '" was deleted.');
            }
        }
        redirect('suppliers/index.php');
    }

    if ($action === 'save') {
        $form = [
            'id' => (int) input('id'), 'name' => input('name'), 'contact_person' => input('contact_person'),
            'phone' => input('phone'), 'email' => input('email'), 'address' => input('address'),
        ];

        if ($form['name'] === '') {
            $errors['name'] = 'Supplier name is required.';
        } elseif (mb_strlen($form['name']) > 120) {
            $errors['name'] = 'Supplier name must be 120 characters or fewer.';
        } elseif (db_val($conn, 'SELECT id FROM suppliers WHERE name = ? AND id <> ?', 'si', [$form['name'], $form['id']])) {
            $errors['name'] = 'A supplier with this name already exists.';
        }
        if (mb_strlen($form['contact_person']) > 100) {
            $errors['contact_person'] = 'Contact person must be 100 characters or fewer.';
        }
        if ($form['phone'] !== '' && !preg_match('/^[0-9+()\-\s]{5,30}$/', $form['phone'])) {
            $errors['phone'] = 'Use digits, spaces, +, - and parentheses only (5–30 characters).';
        }
        if ($form['email'] !== '' && (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 120)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (mb_strlen($form['address']) > 255) {
            $errors['address'] = 'Address must be 255 characters or fewer.';
        }

        if (!$errors) {
            try {
                $values = [$form['name'], $form['contact_person'], $form['phone'], $form['email'], $form['address']];
                if ($form['id'] > 0) {
                    db_exec($conn, 'UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE id = ?', 'sssssi', array_merge($values, [$form['id']]));
                    log_activity($conn, 'supplier_updated', "Updated supplier #{$form['id']} {$form['name']}");
                    flash('success', 'Supplier updated.');
                } else {
                    db_exec($conn, 'INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)', 'sssss', $values);
                    log_activity($conn, 'supplier_created', "Created supplier {$form['name']}");
                    flash('success', 'Supplier added.');
                }
                redirect('suppliers/index.php');
            } catch (mysqli_sql_exception $ex) {
                if ($ex->getCode() === 1062) {
                    $errors['name'] = 'A supplier with this name already exists.';
                } else {
                    error_log('Save supplier failed: ' . $ex->getMessage());
                    $errors['name'] = 'The supplier could not be saved. Please try again.';
                }
            }
        }
    }
}

$suppliers = db_all($conn, 'SELECT s.*, COUNT(p.id) AS product_count
                            FROM suppliers s LEFT JOIN products p ON p.supplier_id = s.id
                            GROUP BY s.id ORDER BY s.name');
$isEditing = $form['id'] > 0;

$pageTitle = 'Suppliers';
$activeNav = 'suppliers';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <div class="col-xl-8 order-2 order-xl-1">
        <div class="card">
            <?php if (!$suppliers): ?>
                <?= empty_state('truck', 'No suppliers yet', 'Add a supplier using the form so you can link it to products and deliveries.') ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Supplier</th><th>Contact</th><th>Phone / email</th><th class="text-end">Products</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td><span class="fw-semibold"><?= e($s['name']) ?></span><div class="small text-muted"><?= e($s['address'] ?? '') ?></div></td>
                                <td><?= e($s['contact_person'] ?: '—') ?></td>
                                <td><?= e($s['phone'] ?: '—') ?><div class="small text-muted"><?= e($s['email'] ?? '') ?></div></td>
                                <td class="text-end"><?= (int) $s['product_count'] ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="?edit=<?= (int) $s['id'] ?>" class="btn btn-light btn-sm border" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <?php if (is_admin()): ?>
                                        <form method="post" class="d-inline js-confirm" data-confirm="Delete supplier &quot;<?= e($s['name']) ?>&quot;?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                            <button type="submit" class="btn btn-light btn-sm border text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
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

    <div class="col-xl-4 order-1 order-xl-2">
        <div class="card">
            <div class="card-header"><?= $isEditing ? 'Edit supplier' : 'Add supplier' ?></div>
            <div class="card-body">
                <form method="post" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">Supplier name *</label>
                        <input type="text" class="form-control<?= field_class($errors, 'name') ?>" id="name" name="name" value="<?= e($form['name']) ?>" maxlength="120" required>
                        <?= field_error($errors, 'name') ?: '<div class="invalid-feedback">Enter a supplier name.</div>' ?>
                    </div>
                    <div class="mb-3">
                        <label for="contact_person" class="form-label">Contact person</label>
                        <input type="text" class="form-control<?= field_class($errors, 'contact_person') ?>" id="contact_person" name="contact_person" value="<?= e($form['contact_person']) ?>" maxlength="100">
                        <?= field_error($errors, 'contact_person') ?>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone number</label>
                        <input type="text" class="form-control<?= field_class($errors, 'phone') ?>" id="phone" name="phone" value="<?= e($form['phone']) ?>" maxlength="30">
                        <?= field_error($errors, 'phone') ?>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control<?= field_class($errors, 'email') ?>" id="email" name="email" value="<?= e($form['email']) ?>" maxlength="120">
                        <?= field_error($errors, 'email') ?: '<div class="invalid-feedback">Enter a valid email address.</div>' ?>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control<?= field_class($errors, 'address') ?>" id="address" name="address" rows="2" maxlength="255"><?= e($form['address']) ?></textarea>
                        <?= field_error($errors, 'address') ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><?= $isEditing ? 'Save changes' : 'Add supplier' ?></button>
                        <?php if ($isEditing): ?><a href="<?= e(url('suppliers/index.php')) ?>" class="btn btn-light border">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
