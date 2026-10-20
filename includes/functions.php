<?php
/**
 * includes/functions.php
 * Shared helpers: escaping, CSRF, flash messages, database shortcuts
 * (MySQLi prepared statements), pagination, validation and stock logic.
 */

/* ------------------------------------------------------------------
 * mbstring fallbacks (XAMPP enables mbstring; these only matter if it is off)
 * ---------------------------------------------------------------- */
if (!function_exists('mb_strlen')) {
    function mb_strlen($text, $encoding = null)
    {
        return preg_match_all('/./us', (string) $text, $m);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr($text, $start, $length = null, $encoding = null)
    {
        $chars = preg_split('//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode('', array_slice($chars, $start, $length));
    }
}

/* ------------------------------------------------------------------
 * Output and navigation helpers
 * ---------------------------------------------------------------- */

/** Escape a value for safe output in HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Build a URL inside the project. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path)
{
    header('Location: ' . url($path));
    exit;
}

/** Read a trimmed string from POST (default) or GET. */
function input(string $key, string $method = 'POST'): string
{
    $source = ($method === 'GET') ? $_GET : $_POST;
    $value = $source[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function money($amount): string
{
    return CURRENCY . number_format((float) $amount, 2);
}

function format_datetime(?string $value): string
{
    return $value ? date('M d, Y h:i A', strtotime($value)) : '—';
}

function format_date(?string $value): string
{
    return $value ? date('M d, Y', strtotime($value)) : '—';
}

/** Build a LIKE pattern, escaping % and _ typed by the user. */
function like_pattern(string $text): string
{
    return '%' . addcslashes($text, '%_\\') . '%';
}

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function selected($a, $b): string
{
    return ((string) $a === (string) $b) ? ' selected' : '';
}

function generate_reference(string $prefix): string
{
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

/** Sidebar link. */
function nav_link(string $href, string $icon, string $label, string $key, string $active): string
{
    $class = 'nav-link-item' . ($key === $active ? ' active' : '');
    return '<a class="' . $class . '" href="' . e(url($href)) . '"><i class="bi bi-' . $icon . '"></i><span>' . e($label) . '</span></a>';
}

/* ------------------------------------------------------------------
 * Flash messages and form feedback
 * ---------------------------------------------------------------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_render(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $icons = [
        'success' => 'check-circle-fill',
        'danger'  => 'exclamation-triangle-fill',
        'warning' => 'exclamation-circle-fill',
        'info'    => 'info-circle-fill',
    ];
    $html = '';
    foreach ($_SESSION['flash'] as $item) {
        $type = isset($icons[$item['type']]) ? $item['type'] : 'info';
        $html .= '<div class="alert alert-' . $type . ' alert-dismissible fade show d-flex align-items-center no-print" role="alert">'
            . '<i class="bi bi-' . $icons[$type] . ' me-2"></i><div>' . e($item['message']) . '</div>'
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

function field_class(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
}

function empty_state(string $icon, string $title, string $text = '', string $actionHtml = ''): string
{
    return '<div class="empty-state"><i class="bi bi-' . e($icon) . '"></i><h2>' . e($title) . '</h2>'
        . ($text !== '' ? '<p>' . e($text) . '</p>' : '') . $actionHtml . '</div>';
}

/* ------------------------------------------------------------------
 * CSRF protection
 * ---------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Stop the request if the submitted CSRF token is missing or wrong. */
function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Invalid or expired form token. Go back, refresh the page and try again.');
    }
}

/* ------------------------------------------------------------------
 * Database helpers (procedural MySQLi + prepared statements)
 * ---------------------------------------------------------------- */

/** Prepare, bind and execute a statement. Returns the statement. */
function db_run(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

/** SELECT: all rows as an array of associative arrays. */
function db_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = db_run($conn, $sql, $types, $params);
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}

/** SELECT: first row or null. */
function db_row(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_all($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

/** SELECT: first column of the first row, or null. */
function db_val(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    $row = db_row($conn, $sql, $types, $params);
    return $row ? reset($row) : null;
}

/** INSERT / UPDATE / DELETE: returns the number of affected rows. */
function db_exec(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $stmt = db_run($conn, $sql, $types, $params);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $affected;
}

/* ------------------------------------------------------------------
 * Pagination
 * ---------------------------------------------------------------- */

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    return [
        'total'    => $total,
        'per_page' => $perPage,
        'pages'    => $pages,
        'page'     => $page,
        'offset'   => ($page - 1) * $perPage,
    ];
}

function page_summary(array $p): string
{
    if ($p['total'] === 0) {
        return 'No records';
    }
    $from = $p['offset'] + 1;
    $to = min($p['offset'] + $p['per_page'], $p['total']);
    return "Showing {$from}–{$to} of {$p['total']}";
}

/** Render Bootstrap pagination links, keeping the current filters. */
function render_pagination(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $query = $_GET;
    $link = function (int $n) use ($query): string {
        $query['page'] = $n;
        return '?' . http_build_query($query);
    };
    $html = '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';
    $html .= '<li class="page-item' . ($p['page'] <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . e($link($p['page'] - 1)) . '">Previous</a></li>';
    for ($i = max(1, $p['page'] - 2); $i <= min($p['pages'], $p['page'] + 2); $i++) {
        $html .= '<li class="page-item' . ($i === $p['page'] ? ' active' : '') . '"><a class="page-link" href="' . e($link($i)) . '">' . $i . '</a></li>';
    }
    $html .= '<li class="page-item' . ($p['page'] >= $p['pages'] ? ' disabled' : '') . '"><a class="page-link" href="' . e($link($p['page'] + 1)) . '">Next</a></li>';
    return $html . '</ul></nav>';
}

/* ------------------------------------------------------------------
 * Activity log
 * ---------------------------------------------------------------- */

function log_activity(mysqli $conn, string $action, string $details = ''): void
{
    try {
        db_exec(
            $conn,
            'INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)',
            'isss',
            [$_SESSION['user_id'] ?? null, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]
        );
    } catch (Throwable $ex) {
        error_log('Activity log failed: ' . $ex->getMessage());
    }
}

/* ------------------------------------------------------------------
 * Stock status helpers (always computed from the database values)
 * ---------------------------------------------------------------- */

function stock_status($quantity, $minStock): string
{
    if ($quantity <= 0) {
        return 'out';
    }
    return ($quantity <= $minStock) ? 'low' : 'ok';
}

function stock_status_label(string $status): string
{
    return ['out' => 'Out of stock', 'low' => 'Low stock', 'ok' => 'In stock'][$status] ?? '';
}

function status_badge($quantity, $minStock): string
{
    $status = stock_status($quantity, $minStock);
    $class = ['out' => 'text-bg-danger', 'low' => 'text-bg-warning', 'ok' => 'text-bg-success'][$status];
    return '<span class="badge ' . $class . '">' . stock_status_label($status) . '</span>';
}

function type_badge(string $type): string
{
    return $type === 'in'
        ? '<span class="badge text-bg-success">Stock in</span>'
        : '<span class="badge text-bg-secondary">Stock out</span>';
}

function signed_quantity(string $type, $quantity): string
{
    return $type === 'in'
        ? '<span class="qty-in">+' . fmt_qty($quantity) . '</span>'
        : '<span class="qty-out">−' . fmt_qty($quantity) . '</span>';
}

/* ------------------------------------------------------------------
 * Product validation
 * ---------------------------------------------------------------- */

/**
 * Validate product form data. Returns an array of errors (empty = valid).
 * $id is the product being edited (null when creating).
 */
function validate_product(mysqli $conn, array $d, ?int $id, bool $isNew): array
{
    $errors = [];

    if ($d['name'] === '') {
        $errors['name'] = 'Product name is required.';
    } elseif (mb_strlen($d['name']) > 150) {
        $errors['name'] = 'Product name must be 150 characters or fewer.';
    }

    if ($d['sku'] === '') {
        $errors['sku'] = 'SKU / product code is required.';
    } elseif (!preg_match('/^[A-Za-z0-9._\-]{2,50}$/', $d['sku'])) {
        $errors['sku'] = 'Use 2–50 letters, numbers, dots, dashes or underscores (no spaces).';
    } elseif (db_val($conn, 'SELECT id FROM products WHERE sku = ? AND id <> ?', 'si', [$d['sku'], $id ?? 0])) {
        $errors['sku'] = 'This SKU / product code is already used by another product.';
    }

    if (!ctype_digit($d['category_id']) || !db_val($conn, 'SELECT id FROM categories WHERE id = ?', 'i', [(int) $d['category_id']])) {
        $errors['category_id'] = 'Please choose a category.';
    }

    if ($d['supplier_id'] !== '' && (!ctype_digit($d['supplier_id']) || !db_val($conn, 'SELECT id FROM suppliers WHERE id = ?', 'i', [(int) $d['supplier_id']]))) {
        $errors['supplier_id'] = 'Selected supplier does not exist.';
    }

    foreach (['cost_price' => 'Cost price', 'selling_price' => 'Selling price'] as $field => $label) {
        if (!is_numeric($d[$field]) || (float) $d[$field] < 0 || (float) $d[$field] > 99999999.99) {
            $errors[$field] = "$label must be a number from 0 to 99,999,999.99.";
        }
    }

    if (filter_var($d['min_stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]) === false) {
        $errors['min_stock'] = 'Minimum stock level must be a whole number (0 or more).';
    }

    if ($isNew && filter_var($d['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]) === false) {
        $errors['quantity'] = 'Opening stock must be a whole number (0 or more).';
    }

    if (mb_strlen($d['description']) > 1000) {
        $errors['description'] = 'Description must be 1000 characters or fewer.';
    }

    return $errors;
}

/* ------------------------------------------------------------------
 * Stock transactions
 * ---------------------------------------------------------------- */

/**
 * Record a stock-in or stock-out and update the product quantity.
 *
 * The product row is locked (SELECT ... FOR UPDATE), the quantity is changed
 * and the history row is inserted inside ONE database transaction, so the
 * stock level and the history can never disagree.
 *
 * $d keys: product_id, type ('in'|'out'), quantity, transaction_date,
 *          reference_no, supplier_id (or null), reason, remarks, user_id
 *
 * Throws RuntimeException with a user-friendly message for business-rule
 * problems (e.g. not enough stock). Other exceptions are database errors.
 *
 * Pass $manageTransaction = false when the caller already started a transaction.
 */
function record_stock_transaction(mysqli $conn, array $d, bool $manageTransaction = true): int
{
    if ($manageTransaction) {
        mysqli_begin_transaction($conn);
    }
    try {
        $product = db_row($conn, 'SELECT quantity FROM products WHERE id = ? FOR UPDATE', 'i', [$d['product_id']]);
        if (!$product) {
            throw new RuntimeException('Product not found.');
        }

        // Quantities may be decimal (e.g. 2.5 kg), so they are handled with 3 decimals.
        $before = round((float) $product['quantity'], 3);
        $qty = round((float) $d['quantity'], 3);
        $after = round(($d['type'] === 'in') ? $before + $qty : $before - $qty, 3);

        if ($after < 0) {
            throw new RuntimeException('Not enough stock. Only ' . fmt_qty($before) . ' unit(s) available.');
        }

        db_exec($conn, 'UPDATE products SET quantity = ? WHERE id = ?', 'si', [number_format($after, 3, '.', ''), $d['product_id']]);

        db_exec(
            $conn,
            'INSERT INTO stock_transactions
                (product_id, transaction_type, quantity, stock_before, stock_after, reference_no,
                 supplier_id, reason, remarks, transaction_date, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'isssssisssi',
            [
                $d['product_id'], $d['type'], number_format($qty, 3, '.', ''),
                number_format($before, 3, '.', ''), number_format($after, 3, '.', ''), $d['reference_no'],
                $d['supplier_id'], $d['reason'], $d['remarks'], $d['transaction_date'], $d['user_id'],
            ]
        );
        $transactionId = mysqli_insert_id($conn);

        if ($manageTransaction) {
            mysqli_commit($conn);
        }
        return $transactionId;
    } catch (Throwable $ex) {
        if ($manageTransaction) {
            mysqli_rollback($conn);
        }
        throw $ex;
    }
}


/* ------------------------------------------------------------------
 * Selling units (sack / kilo / piece ...) and decimal quantities
 * Stock is always kept in the product's BASE unit (products.quantity).
 * Each extra unit stores how many base units it contains (conversion_qty).
 * ---------------------------------------------------------------- */

/** Show a quantity without useless zeros: 12.500 -> 12.5, 3.000 -> 3 */
function fmt_qty($value): string
{
    $text = number_format((float) $value, 3, '.', '');
    return rtrim(rtrim($text, '0'), '.');
}

/** Decimal text/number -> integer scaled by 10^$decimals (12.5, 3 -> 12500). */
function to_scaled($value, int $decimals): int
{
    return (int) str_replace('.', '', number_format((float) $value, $decimals, '.', ''));
}

/** Integer scaled by 10^$decimals -> decimal text (12500, 3 -> '12.500'). */
function scaled_str(int $value, int $decimals): string
{
    $factor = (int) pow(10, $decimals);
    return intdiv($value, $factor) . '.' . str_pad((string) ($value % $factor), $decimals, '0', STR_PAD_LEFT);
}

/** Extra selling units of a product, ordered. */
function product_units(mysqli $conn, int $productId): array
{
    return db_all(
        $conn,
        'SELECT id, unit_name, conversion_qty, selling_price, allow_decimal
         FROM product_units WHERE product_id = ? ORDER BY sort_order, id',
        'i',
        [$productId]
    );
}

/** Shape of the unit list sent to the POS page (prices in cents). */
function pos_units_payload(array $rows): array
{
    $out = [];
    foreach ($rows as $u) {
        $out[] = [
            'id'    => (int) $u['id'],
            'name'  => $u['unit_name'],
            'conv'  => (float) $u['conversion_qty'],
            'price' => (int) round((float) $u['selling_price'] * 100),
            'dec'   => (int) $u['allow_decimal'] === 1,
        ];
    }
    return $out;
}

/** Read the "units" rows posted by the product form. */
function units_from_post(): array
{
    $raw = $_POST['units'] ?? [];
    $units = [];
    if (!is_array($raw)) {
        return $units;
    }
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $get = function (string $k) use ($row): string {
            return isset($row[$k]) && is_string($row[$k]) ? trim($row[$k]) : '';
        };
        $u = [
            'id'             => $get('id'),
            'unit_name'      => $get('unit_name'),
            'conversion_qty' => $get('conversion_qty'),
            'selling_price'  => $get('selling_price'),
            'allow_decimal'  => $get('allow_decimal') === '1' ? '1' : '0',
        ];
        if ($u['unit_name'] === '' && $u['conversion_qty'] === '' && $u['selling_price'] === '') {
            continue;   // completely empty row
        }
        $units[] = $u;
    }
    return $units;
}

/**
 * Validate the base-unit fields and the extra units of the product form.
 * Returns an array of errors keyed like the other form errors.
 */
function validate_units(array $product, array $units): array
{
    $errors = [];
    $base = trim($product['base_unit'] ?? '');
    if ($base === '' || mb_strlen($base) > 30) {
        $errors['base_unit'] = 'Base unit is required (30 characters or fewer), for example pc, kilo or sack.';
    }
    if (($product['multi_unit'] ?? '') !== '1') {
        return $errors;
    }
    if (!$units) {
        $errors['units'] = 'Add at least one extra selling unit, or untick "Sell in multiple units".';
        return $errors;
    }
    $seen = [mb_strtolower($base)];
    foreach ($units as $i => $u) {
        $n = $i + 1;
        $name = $u['unit_name'];
        if ($name === '' || mb_strlen($name) > 30) {
            $errors['units'] = "Unit $n: enter a unit name (30 characters or fewer).";
            break;
        }
        if (in_array(mb_strtolower($name), $seen, true)) {
            $errors['units'] = "Unit $n: the name \"$name\" is already used for this product (including the base unit).";
            break;
        }
        $seen[] = mb_strtolower($name);
        if (!preg_match('/^\d{1,6}(\.\d{1,4})?$/', $u['conversion_qty']) || (float) $u['conversion_qty'] <= 0 || (float) $u['conversion_qty'] > 100000) {
            $errors['units'] = "Unit $n: conversion must be a number above 0 (up to 4 decimals, max 100,000) = how many base units it contains.";
            break;
        }
        if (!is_numeric($u['selling_price']) || (float) $u['selling_price'] < 0 || (float) $u['selling_price'] > 99999999.99) {
            $errors['units'] = "Unit $n: selling price must be a number from 0 to 99,999,999.99.";
            break;
        }
    }
    return $errors;
}

/**
 * Save the extra units: update the rows that still exist, insert new rows,
 * delete rows that were removed. Past sales are not affected (they keep a copy
 * of the unit name and quantity). Call inside a transaction.
 */
function save_product_units(mysqli $conn, int $productId, array $units): void
{
    $existing = array_column(db_all($conn, 'SELECT id FROM product_units WHERE product_id = ?', 'i', [$productId]), 'id');
    $existing = array_map('intval', $existing);
    $keep = [];
    foreach ($units as $order => $u) {
        $name = $u['unit_name'];
        $conv = number_format((float) $u['conversion_qty'], 4, '.', '');
        $price = number_format((float) $u['selling_price'], 2, '.', '');
        $dec = (int) $u['allow_decimal'];
        $id = ctype_digit($u['id']) ? (int) $u['id'] : 0;
        if ($id > 0 && in_array($id, $existing, true)) {
            db_exec(
                $conn,
                'UPDATE product_units SET unit_name = ?, conversion_qty = ?, selling_price = ?, allow_decimal = ?, sort_order = ? WHERE id = ? AND product_id = ?',
                'sssiiii',
                [$name, $conv, $price, $dec, $order, $id, $productId]
            );
            $keep[] = $id;
        } else {
            db_exec(
                $conn,
                'INSERT INTO product_units (product_id, unit_name, conversion_qty, selling_price, allow_decimal, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
                'isssii',
                [$productId, $name, $conv, $price, $dec, $order]
            );
            $keep[] = (int) mysqli_insert_id($conn);
        }
    }
    foreach ($existing as $id) {
        if (!in_array($id, $keep, true)) {
            db_exec($conn, 'DELETE FROM product_units WHERE id = ? AND product_id = ?', 'ii', [$id, $productId]);
        }
    }
}
