<?php
require_once __DIR__ . '/includes/auth.php';

// Logging out must be a POST request with a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
    csrf_check();
    log_activity($conn, 'logout', 'User logged out.');
    destroy_session();
    session_start();               // fresh session just to carry the message
    flash('success', 'You have been logged out.');
    redirect('login.php');
}
redirect(is_logged_in() ? 'dashboard.php' : 'login.php');
