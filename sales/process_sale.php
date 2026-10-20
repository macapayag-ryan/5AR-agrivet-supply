<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function to_cents($value): int
{
    return (int) round(((float) $value) * 100);
}

function from_cents(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

function fresh_products(mysqli $conn, array $ids): array
{
    if (!$ids) {
        return [];
    }
    $ids = array_values($ids);
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_all(
        $conn,
        "SELECT id, selling_price, quantity, base_allow_decimal, multi_unit FROM products WHERE id IN ($marks)",
        str_repeat('i', count($ids)),
        $ids
    );
    $unitRows = db_all(
        $conn,
        "SELECT id, product_id, unit_name, conversion_qty, selling_price, allow_decimal
         FROM product_units WHERE product_id IN ($marks) ORDER BY sort_order, id",
        str_repeat('i', count($ids)),
        $ids
    );
    $unitsBy = [];
    foreach ($unitRows as $u) {
        $unitsBy[(int) $u['product_id']][] = $u;
    }
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int) $r['id'],
            'price' => to_cents($r['selling_price']),
            'quantity' => (float) $r['quantity'],
            'dec' => (int) $r['base_allow_decimal'] === 1,
            'units' => (int) $r['multi_unit'] === 1 ? pos_units_payload($unitsBy[(int) $r['id']] ?? []) : [],
        ];
    }
    return $out;
}

function fail(string $message, int $status = 422, ?array $products = null): void
{
    $body = ['ok' => false, 'message' => $message];
    if ($products !== null) {
        $body['products'] = $products;
    }
    json_out($body, $status);
}

function success(mysqli $conn, array $sale, bool $duplicate): void
{
    $soldIds = array_values(array_unique(array_map('intval', array_column(
        db_all($conn, 'SELECT product_id FROM sale_items WHERE sale_id = ?', 'i', [(int) $sale['id']]),
        'product_id'
    ))));
    json_out([
        'ok' => true,
        'duplicate' => $duplicate,
        'receipt_number' => $sale['receipt_number'],
        'receipt_url' => url('sales/receipt.php?id=' . (int) $sale['id']),
        'method' => $sale['payment_method'] ?? 'cash',
        'total' => to_cents($sale['total_amount']),
        'paid' => to_cents($sale['amount_paid']),
        'change' => to_cents($sale['change_amount']),
        'products' => fresh_products($conn, $soldIds),
    ]);
}

if (!is_logged_in()) {
    fail('You are logged out. Reload the page and log in again.', 401);
}
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Invalid request.', 405);
}

$csrf = $_POST['csrf_token'] ?? '';
if (!is_string($csrf) || !hash_equals(csrf_token(), $csrf)) {
    fail('Your form token expired. Reload the page and try again.', 403);
}

$token = input('checkout_token');
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    fail('Invalid checkout token. Reload the page and try again.', 400);
}

$existing = db_row(
    $conn,
    'SELECT id, receipt_number, total_amount, amount_paid, change_amount, payment_method FROM sales WHERE checkout_token = ?',
    's',
    [$token]
);
if ($existing) {
    success($conn, $existing, true);
}

$cartRaw = json_decode(input('cart'), true);
if (!is_array($cartRaw) || !$cartRaw || count($cartRaw) > 100) {
    fail('The cart is empty or invalid.', 400);
}

// Cart lines: product id + selling unit (0 = base unit) + quantity (up to 3 decimals).
// Quantities are handled as integer thousandths so there are no rounding surprises.
$items = [];   // [productId][unitId] => quantity in thousandths
foreach ($cartRaw as $line) {
    if (!is_array($line)) {
        fail('The cart is invalid.', 400);
    }
    $id = filter_var($line['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $unitId = filter_var($line['unit'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $qtyText = (isset($line['qty']) && is_scalar($line['qty'])) ? trim((string) $line['qty']) : '';
    if ($id === false || $unitId === false || !preg_match('/^\d{1,7}(\.\d{1,3})?$/', $qtyText)) {
        fail('The cart contains an invalid product or quantity.', 400);
    }
    $milli = to_scaled($qtyText, 3);
    if ($milli < 1) {
        fail('The cart contains an invalid product or quantity.', 400);
    }
    $items[$id][$unitId] = ($items[$id][$unitId] ?? 0) + $milli;
    if ($items[$id][$unitId] > 1000000000) {
        fail('The cart contains an invalid product or quantity.', 400);
    }
}
ksort($items);
$ids = array_keys($items);

$paidText = input('amount_paid');
if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $paidText)) {
    fail('Enter a valid amount paid.', 400);
}
$paidCents = to_cents($paidText);

$expected = filter_var(input('expected_total'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($expected === false) {
    fail('Invalid total. Reload the page and try again.', 400);
}

$method = input('payment_method');
if ($method === '') {
    $method = 'cash';
}
if (!in_array($method, ['cash', 'gcash'], true)) {
    fail('Choose a valid payment method.', 400);
}
$reference = null;
if ($method === 'gcash') {
    $reference = strtoupper((string) preg_replace('/\s+/', '', input('payment_reference')));
    if (!preg_match('/^[A-Z0-9]{6,30}$/', $reference)) {
        fail('Enter a valid GCash reference number (6 to 30 letters or numbers).', 400);
    }
}

mysqli_begin_transaction($conn);
try {
    $lines = [];
    $totalCents = 0;

    foreach ($items as $productId => $unitQtys) {
        $p = db_row(
            $conn,
            'SELECT id, name, selling_price, quantity, base_unit, base_allow_decimal, multi_unit FROM products WHERE id = ? FOR UPDATE',
            'i',
            [$productId]
        );
        if (!$p) {
            throw new RuntimeException('A product in the cart no longer exists. Remove it and try again.');
        }
        $multi = ((int) $p['multi_unit'] === 1);
        $unitRows = [];
        if ($multi) {
            foreach (product_units($conn, (int) $p['id']) as $u) {
                $unitRows[(int) $u['id']] = $u;
            }
        }
        $showUnit = $multi || ((int) $p['base_allow_decimal'] === 1);
        $stockMilli = to_scaled($p['quantity'], 3);
        $usedMilli = 0;
        ksort($unitQtys);

        foreach ($unitQtys as $unitId => $milli) {
            if ($unitId === 0) {
                $unitName = $p['base_unit'];
                $conv4 = 10000;                                   // 1.0000
                $unitCents = to_cents($p['selling_price']);
                $allowDec = ((int) $p['base_allow_decimal'] === 1);
            } else {
                if (!isset($unitRows[$unitId])) {
                    throw new RuntimeException('A selling unit of ' . $p['name'] . ' was changed or removed. The cart was updated; please check it and try again.');
                }
                $u = $unitRows[$unitId];
                $unitName = $u['unit_name'];
                $conv4 = to_scaled($u['conversion_qty'], 4);
                $unitCents = to_cents($u['selling_price']);
                $allowDec = ((int) $u['allow_decimal'] === 1);
            }
            if (!$allowDec && $milli % 1000 !== 0) {
                throw new RuntimeException($p['name'] . ' can only be sold in whole numbers of ' . $unitName . '.');
            }
            $product = $unitCents * $milli;
            if (is_float($product) || $product > 4000000000000000000) {
                throw new RuntimeException('The quantity is too large for ' . $p['name'] . '.');
            }
            // base quantity to deduct = quantity x conversion (rounded to 3 decimals, half up)
            $baseMilli = intdiv(2 * $milli * $conv4 + 10000, 20000);
            if ($baseMilli < 1) {
                throw new RuntimeException('The quantity of ' . $p['name'] . ' is too small to sell.');
            }
            $lineCents = intdiv(2 * $product + 1000, 2000);       // price x quantity, half up
            $usedMilli += $baseMilli;
            $totalCents += $lineCents;
            $lines[] = [
                'product_id' => (int) $p['id'],
                'name' => $p['name'],
                'base_qty' => scaled_str($baseMilli, 3),
                'unit_name' => $showUnit ? $unitName : null,
                'unit_qty' => scaled_str($milli, 3),
                'unit_conv' => scaled_str($conv4, 4),
                'unit' => $unitCents,
                'line' => $lineCents,
                'remarks' => $showUnit ? 'POS sale: ' . fmt_qty($milli / 1000) . ' ' . $unitName : 'POS sale',
            ];
        }

        if ($usedMilli > $stockMilli) {
            throw new RuntimeException('Not enough stock for ' . $p['name'] . '. Only ' . fmt_qty($stockMilli / 1000) . ' ' . $p['base_unit'] . ' available.');
        }
    }

    if ($totalCents > 999999999999) {
        throw new RuntimeException('The total is too large for one sale.');
    }
    if ($totalCents !== $expected) {
        throw new RuntimeException('Prices changed since this sale was started. The cart was updated; check the new total and try again.');
    }
    if ($method === 'gcash') {
        $paidCents = $totalCents;
        if ((int) db_val($conn, 'SELECT COUNT(*) FROM sales WHERE payment_reference = ?', 's', [$reference]) > 0) {
            throw new RuntimeException('This GCash reference number was already used for another sale.');
        }
    }
    if ($paidCents < $totalCents) {
        throw new RuntimeException('The amount paid is less than the total.');
    }
    $changeCents = $paidCents - $totalCents;

    $timestamp = time();
    $now = date('Y-m-d H:i:s', $timestamp);

    db_exec(
        $conn,
        "INSERT INTO sales (receipt_number, checkout_token, total_amount, amount_paid, change_amount, payment_method, payment_reference, status, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?)",
        'sssssssis',
        ['TMP-' . substr($token, 0, 16), $token, from_cents($totalCents), from_cents($paidCents), from_cents($changeCents), $method, $reference, $_SESSION['user_id'], $now]
    );
    $saleId = (int) mysqli_insert_id($conn);
    $receipt = 'RCP-' . date('Ymd', $timestamp) . '-' . str_pad((string) $saleId, 5, '0', STR_PAD_LEFT);
    db_exec($conn, 'UPDATE sales SET receipt_number = ? WHERE id = ?', 'si', [$receipt, $saleId]);

    foreach ($lines as $l) {
        db_exec(
            $conn,
            'INSERT INTO sale_items (sale_id, product_id, product_name, quantity, unit_price, line_total, unit_name, unit_qty, unit_conversion)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'iisssssss',
            [
                $saleId,
                $l['product_id'],
                $l['name'],
                $l['base_qty'],
                from_cents($l['unit']),
                from_cents($l['line']),
                $l['unit_name'],
                $l['unit_qty'],
                $l['unit_conv']
            ]
        );
        record_stock_transaction($conn, [
            'product_id' => $l['product_id'],
            'type' => 'out',
            'quantity' => $l['base_qty'],
            'transaction_date' => $now,
            'reference_no' => $receipt,
            'supplier_id' => null,
            'reason' => 'Sale',
            'remarks' => $l['remarks'],
            'user_id' => $_SESSION['user_id'],
        ], false);
    }

    mysqli_commit($conn);
} catch (mysqli_sql_exception $ex) {
    mysqli_rollback($conn);
    if ((int) $ex->getCode() === 1062) {
        $again = db_row(
            $conn,
            'SELECT id, receipt_number, total_amount, amount_paid, change_amount, payment_method FROM sales WHERE checkout_token = ?',
            's',
            [$token]
        );
        if ($again) {
            success($conn, $again, true);
        }
        if (strpos($ex->getMessage(), 'uq_sales_payment_reference') !== false) {
            fail('This GCash reference number was already used for another sale.', 422, fresh_products($conn, $ids));
        }
    }
    error_log('POS checkout failed: ' . $ex->getMessage());
    fail('The sale could not be saved and no stock was changed. Please try again.', 500);
} catch (RuntimeException $ex) {
    mysqli_rollback($conn);
    fail($ex->getMessage(), 422, fresh_products($conn, $ids));
} catch (Throwable $ex) {
    mysqli_rollback($conn);
    error_log('POS checkout failed: ' . $ex->getMessage());
    fail('The sale could not be saved and no stock was changed. Please try again.', 500);
}

log_activity($conn, 'sale_completed', 'Receipt ' . $receipt . ', total ' . from_cents($totalCents) . ', ' . $method);

success($conn, [
    'id' => $saleId,
    'receipt_number' => $receipt,
    'total_amount' => from_cents($totalCents),
    'amount_paid' => from_cents($paidCents),
    'change_amount' => from_cents($changeCents),
    'payment_method' => $method,
], false);