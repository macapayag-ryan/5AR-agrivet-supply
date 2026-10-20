<?php
// Stock Out: outgoing inventory (sales, releases, damaged goods...).
require_once __DIR__ . '/../includes/auth.php';
require_login();
$type = 'out';
require __DIR__ . '/_stock_form.php';
