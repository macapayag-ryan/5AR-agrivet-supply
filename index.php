<?php
// Entry point: send visitors to the dashboard (or the login page).
require_once __DIR__ . '/includes/auth.php';
redirect(is_logged_in() ? 'dashboard.php' : 'login.php');


?>