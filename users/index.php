<?php
// User management (administrators only).
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$form = ['id' => 0, 'full_name' => '', 'username' => '', 'role' => 'staff', 'is_active' => 1];
$errors = [];

$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $row = db_row($conn, 'SELECT id, full_name, username, role, is_active FROM users WHERE id = ?', 'i', [$editId]);
    if (!$row) {
        flash('danger', 'User not found.');
        redirect('users/index.php');
    }
    $form = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) input('id');
    $isSelf = ($id === (int) $_SESSION['user_id']);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    $form = [
        'id'        => $id,
        'full_name' => input('full_name'),
        'username'  => input('username'),
        'role'      => input('role'),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    // Administrators cannot lock themselves out or demote themselves.
    if ($isSelf) {
        $form['role'] = 'admin';
        $form['is_active'] = 1;
    }

    if ($form['full_name'] === '' || mb_strlen($form['full_name']) > 100) {
        $errors['full_name'] = 'Enter the full name (up to 100 characters).';
    }
    if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $form['username'])) {
        $errors['username'] = 'Use 3–30 letters, numbers, dots or underscores.';
    } elseif (db_val($conn, 'SELECT id FROM users WHERE username = ? AND id <> ?', 'si', [$form['username'], $id])) {
        $errors['username'] = 'This username is already taken.';
    }
    if (!in_array($form['role'], ['admin', 'staff'], true)) {
        $errors['role'] = 'Choose a role.';
    }
    if ($id === 0 && $password === '') {
        $errors['password'] = 'Enter a password for the new account.';
    } elseif ($password !== '' && (strlen($password) < 8 || strlen($password) > 72)) {
        $errors['password'] = 'Password must be 8 to 72 characters long.';
    }
    if ($id > 0 && !db_val($conn, 'SELECT id FROM users WHERE id = ?', 'i', [$id])) {
        $errors['username'] = 'This user no longer exists.';
    }

    if (!$errors) {
        try {
            if ($id > 0) {
                db_exec($conn, 'UPDATE users SET full_name = ?, username = ?, role = ?, is_active = ? WHERE id = ?', 'sssii',
                    [$form['full_name'], $form['username'], $form['role'], $form['is_active'], $id]);
                if ($password !== '') {
                    db_exec($conn, 'UPDATE users SET password_hash = ? WHERE id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT), $id]);
                }
                log_activity($conn, 'user_updated', "Updated user #$id {$form['username']} (role {$form['role']}, " . ($form['is_active'] ? 'active' : 'disabled') . ')' . ($password !== '' ? ', password changed' : ''));
                flash('success', 'User updated.');
            } else {
                db_exec($conn, 'INSERT INTO users (full_name, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)', 'ssssi',
                    [$form['full_name'], $form['username'], password_hash($password, PASSWORD_DEFAULT), $form['role'], $form['is_active']]);
                log_activity($conn, 'user_created', "Created user {$form['username']} (role {$form['role']})");
                flash('success', 'User added.');
            }
            redirect('users/index.php');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1062) {
                $errors['username'] = 'This username is already taken.';
            } else {
                error_log('Save user failed: ' . $ex->getMessage());
                $errors['username'] = 'The user could not be saved. Please try again.';
            }
        }
    }
}

$users = db_all($conn, 'SELECT id, full_name, username, role, is_active, last_login, created_at FROM users ORDER BY full_name');
$isEditing = $form['id'] > 0;
$editingSelf = $isEditing && (int) $form['id'] === (int) $_SESSION['user_id'];

$pageTitle = 'Users';
$activeNav = 'users';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <div class="col-xl-8 order-2 order-xl-1">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last login</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($u['full_name']) ?><?= (int) $u['id'] === (int) $_SESSION['user_id'] ? ' <span class="badge text-bg-light border">You</span>' : '' ?></td>
                            <td><?= e($u['username']) ?></td>
                            <td><?= $u['role'] === 'admin' ? 'Administrator' : 'Staff' ?></td>
                            <td><?= $u['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Disabled</span>' ?></td>
                            <td class="text-nowrap"><?= e($u['last_login'] ? format_datetime($u['last_login']) : 'Never') ?></td>
                            <td class="text-end"><a href="?edit=<?= (int) $u['id'] ?>" class="btn btn-light btn-sm border" title="Edit"><i class="bi bi-pencil"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">Accounts are disabled instead of deleted so the history keeps showing who made each change.</div>
        </div>
    </div>

    <div class="col-xl-4 order-1 order-xl-2">
        <div class="card">
            <div class="card-header"><?= $isEditing ? 'Edit user' : 'Add user' ?></div>
            <div class="card-body">
                <form method="post" class="needs-validation" novalidate autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full name *</label>
                        <input type="text" class="form-control<?= field_class($errors, 'full_name') ?>" id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" maxlength="100" required>
                        <?= field_error($errors, 'full_name') ?: '<div class="invalid-feedback">Enter the full name.</div>' ?>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control<?= field_class($errors, 'username') ?>" id="username" name="username" value="<?= e($form['username']) ?>" maxlength="30" pattern="[A-Za-z0-9_.]{3,30}" required autocomplete="off">
                        <?= field_error($errors, 'username') ?: '<div class="invalid-feedback">Use 3–30 letters, numbers, dots or underscores.</div>' ?>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role *</label>
                        <select class="form-select<?= field_class($errors, 'role') ?>" id="role" name="role" <?= $editingSelf ? 'disabled' : '' ?>>
                            <option value="staff"<?= selected($form['role'], 'staff') ?>>Staff</option>
                            <option value="admin"<?= selected($form['role'], 'admin') ?>>Administrator</option>
                        </select>
                        <?= field_error($errors, 'role') ?>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label"><?= $isEditing ? 'New password' : 'Password *' ?></label>
                        <input type="password" class="form-control<?= field_class($errors, 'password') ?>" id="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" <?= $isEditing ? '' : 'required' ?>>
                        <?= field_error($errors, 'password') ?: '<div class="invalid-feedback">Use at least 8 characters.</div>' ?>
                        <?php if ($isEditing): ?><div class="form-text">Leave blank to keep the current password.</div><?php endif; ?>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?> <?= $editingSelf ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="is_active">Account is active</label>
                    </div>
                    <?php if ($editingSelf): ?><p class="small text-muted">You cannot change your own role or disable your own account.</p><?php endif; ?>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><?= $isEditing ? 'Save changes' : 'Add user' ?></button>
                        <?php if ($isEditing): ?><a href="<?= e(url('users/index.php')) ?>" class="btn btn-light border">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
