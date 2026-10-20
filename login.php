<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/brand.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

const MAX_LOGIN_ATTEMPTS = 5;
const LOGIN_LOCK_SECONDS = 60;

$error = '';
$username = '';

if (isset($_GET['timeout'])) {
    $error = 'Your session expired. Please log in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = input('username');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (time() < ($_SESSION['login_lock_until'] ?? 0)) {
        $error = 'Too many failed attempts. Please wait a minute and try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $user = db_row($conn, 'SELECT id, full_name, password_hash, role, is_active FROM users WHERE username = ?', 's', [$username]);

        // Verify against a dummy hash when the user does not exist, so the
        // response time does not reveal which usernames are valid.
        $hash = $user['password_hash'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $passwordOk = password_verify($password, $hash);

        if ($user && $user['is_active'] && $passwordOk) {
            session_regenerate_id(true);          // prevents session fixation
            unset($_SESSION['csrf_token'], $_SESSION['login_fails'], $_SESSION['login_lock_until']);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();

            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                db_exec($conn, 'UPDATE users SET password_hash = ? WHERE id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            db_exec($conn, 'UPDATE users SET last_login = NOW() WHERE id = ?', 'i', [$user['id']]);
            log_activity($conn, 'login', 'User logged in.');
            redirect('dashboard.php');
        }

        $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
        if ($_SESSION['login_fails'] >= MAX_LOGIN_ATTEMPTS) {
            $_SESSION['login_lock_until'] = time() + LOGIN_LOCK_SECONDS;
            $_SESSION['login_fails'] = 0;
        }
        log_activity($conn, 'login_failed', 'Failed login for username: ' . mb_substr($username, 0, 50));
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in | <?= e(BRAND_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>

<body class="login-page">
    <div class="login-box">
        <div class="login-lockup">
            <?= brand_mark('lg') ?>
            <div class="brand-5ar"><?= e(BRAND_SHORT) ?></div>
            <div class="brand-word"><?= e(BRAND_WORD) ?></div>
            <div class="login-subtitle">Inventory &amp; Sales Management System</div>
        </div>
        <div class="card">
            <div class="card-body">
                <h1 class="h5 mb-1">Welcome back</h1>
                <p class="text-muted small mb-3">Log in to manage stock and sales.</p>

                <?= flash_render() ?>
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger py-2" role="alert"><i
                            class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username"
                                value="<?= e($username) ?>" maxlength="50" autocomplete="username" required autofocus>
                            <div class="invalid-feedback">Enter your username.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password"
                                autocomplete="current-password" required>
                            <div class="invalid-feedback">Enter your password.</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Log
                        in</button>
                </form>
            </div>
        </div>
        <p class="text-center small mt-3 mb-0" style="color: var(--side-text);"><?= e(BRAND_TAGLINE) ?></p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e(url('assets/js/script.js')) ?>"></script>
</body>

</html>