<?php
/**
 * includes/auth.php
 * Loads config + helpers, starts a secure session and provides login checks.
 * Every protected page calls require_login() (or require_admin()) first.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('INVSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

const SESSION_TIMEOUT = 1800; // 30 minutes of inactivity

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

function destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Block anonymous visitors. Also re-checks the account in the database so a
 * disabled user or a changed role takes effect immediately.
 */
function require_login(): void
{
    global $conn;
    header('Cache-Control: no-store, no-cache, must-revalidate');

    if (!is_logged_in()) {
        redirect('login.php');
    }

    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        destroy_session();
        redirect('login.php?timeout=1');
    }

    $user = db_row($conn, 'SELECT full_name, role, is_active FROM users WHERE id = ?', 'i', [$_SESSION['user_id']]);
    if (!$user || !$user['is_active']) {
        destroy_session();
        redirect('login.php');
    }

    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['last_activity'] = time();
}

/** Administrator-only pages and actions. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('danger', 'You do not have permission to do that.');
        redirect('dashboard.php');
    }
}
