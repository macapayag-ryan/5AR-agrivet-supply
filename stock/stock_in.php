<?php
// Stock In: incoming inventory (purchases, deliveries, returns).
require_once __DIR__ . '/../includes/auth.php';
require_login();
$type = 'in';
require __DIR__ . '/_stock_form.php';
