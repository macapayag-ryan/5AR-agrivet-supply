<?php
/**
 * stock/_stock_form.php
 * Shared page for Stock In and Stock Out. The wrapper pages set $type ('in' or 'out').
 */
$isIn = ($type === 'in');
$reasons = $isIn
    ? ['Purchase', 'Delivery', 'Customer return', 'Other']
    : ['Sale', 'Release / issue', 'Damaged or expired', 'Internal use', 'Other'];

$products = db_all($conn, 'SELECT id, sku, name, quantity FROM products ORDER BY name');
$suppliers = $isIn ? db_all($conn, 'SELECT id, name FROM suppliers ORDER BY name') : [];
$validProducts = array_column($products, 'quantity', 'id');

$form = [
    'product_id'       => (string) (int) ($_GET['product_id'] ?? 0) ?: '',
    'quantity'         => '',
    'transaction_date' => date('Y-m-d\TH:i'),
    'reference_no'     => '',
    'supplier_id'      => '',
    'reason'           => $reasons[0],
    'remarks'          => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (array_keys($form) as $field) {
        $form[$field] = input($field);
    }

    // ---- Server-side validation ----
    if (!ctype_digit($form['product_id']) || !isset($validProducts[(int) $form['product_id']])) {
        $errors['product_id'] = 'Please choose a product.';
    }

    $qty = filter_var($form['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
    if ($qty === false) {
        $errors['quantity'] = 'Quantity must be a whole number from 1 to 1,000,000.';
    } elseif (!$isIn && !isset($errors['product_id']) && $qty > (int) $validProducts[(int) $form['product_id']]) {
        $errors['quantity'] = 'Not enough stock. Only ' . (int) $validProducts[(int) $form['product_id']] . ' unit(s) available.';
    }

    $date = DateTime::createFromFormat('Y-m-d\TH:i', $form['transaction_date']);
    if (!$date || $date->format('Y-m-d\TH:i') !== $form['transaction_date']) {
        $errors['transaction_date'] = 'Enter a valid date and time.';
    } elseif ($date > new DateTime('+5 minutes')) {
        $errors['transaction_date'] = 'The date cannot be in the future.';
    }

    if (mb_strlen($form['reference_no']) > 50) {
        $errors['reference_no'] = 'Reference number must be 50 characters or fewer.';
    }
    if (!in_array($form['reason'], $reasons, true)) {
        $errors['reason'] = 'Please choose a reason.';
    }
    if (mb_strlen($form['remarks']) > 500) {
        $errors['remarks'] = 'Remarks must be 500 characters or fewer.';
    } elseif ($form['reason'] === 'Other' && $form['remarks'] === '') {
        $errors['remarks'] = 'Please explain in the remarks when the reason is "Other".';
    }

    $supplierId = null;
    if ($isIn && $form['supplier_id'] !== '') {
        if (ctype_digit($form['supplier_id']) && db_val($conn, 'SELECT id FROM suppliers WHERE id = ?', 'i', [(int) $form['supplier_id']])) {
            $supplierId = (int) $form['supplier_id'];
        } else {
            $errors['supplier_id'] = 'Selected supplier does not exist.';
        }
    }

    if (!$errors) {
        $reference = $form['reference_no'] !== '' ? $form['reference_no'] : generate_reference($isIn ? 'IN' : 'OUT');
        try {
            record_stock_transaction($conn, [
                'product_id'       => (int) $form['product_id'],
                'type'             => $type,
                'quantity'         => $qty,
                'transaction_date' => $date->format('Y-m-d H:i:s'),
                'reference_no'     => $reference,
                'supplier_id'      => $supplierId,
                'reason'           => $form['reason'],
                'remarks'          => $form['remarks'],
                'user_id'          => $_SESSION['user_id'],
            ]);
            log_activity($conn, $isIn ? 'stock_in' : 'stock_out', "Product #{$form['product_id']}, quantity $qty, reference $reference");
            flash('success', ($isIn ? 'Stock in' : 'Stock out') . " recorded ($qty unit(s), reference $reference).");
            redirect('stock/' . ($isIn ? 'stock_in.php' : 'stock_out.php'));
        } catch (RuntimeException $ex) {
            $errors['quantity'] = $ex->getMessage();    // e.g. someone else just used the stock
        } catch (Throwable $ex) {
            error_log('Stock transaction failed: ' . $ex->getMessage());
            $errors['general'] = 'The transaction could not be saved and no stock was changed. Please try again.';
        }
    }
}

$recent = db_all(
    $conn,
    'SELECT t.*, p.name AS product_name, u.full_name AS user_name
     FROM stock_transactions t
     JOIN products p ON p.id = t.product_id
     LEFT JOIN users u ON u.id = t.created_by
     WHERE t.transaction_type = ?
     ORDER BY t.transaction_date DESC, t.id DESC LIMIT 5',
    's',
    [$type]
);

$pageTitle = $isIn ? 'Stock in' : 'Stock out';
$activeNav = $isIn ? 'stock_in' : 'stock_out';
include __DIR__ . '/../includes/header.php';
?>

<?php if (!$products): ?>
    <div class="card"><?= empty_state('box-seam', 'No products to update', 'Add a product first, then you can record stock movements.',
        '<a href="' . e(url('products/create.php')) . '" class="btn btn-primary btn-sm">Add product</a>') ?></div>
<?php else: ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><?= $isIn ? 'Record incoming stock' : 'Record outgoing stock' ?></div>
            <div class="card-body">
                <?php if (isset($errors['general'])): ?><div class="alert alert-danger"><?= e($errors['general']) ?></div><?php endif; ?>
                <form method="post" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="product_id" class="form-label">Product *</label>
                            <select class="form-select<?= field_class($errors, 'product_id') ?>" id="product_id" name="product_id" required data-stock-select="<?= $isIn ? 'in' : 'out' ?>">
                                <option value="">Choose a product</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>" data-stock="<?= (int) $p['quantity'] ?>"<?= selected($form['product_id'], $p['id']) ?>>
                                        <?= e($p['name']) ?> (<?= e($p['sku']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'product_id') ?: '<div class="invalid-feedback">Choose a product.</div>' ?>
                            <div class="form-text" id="stockHint">&nbsp;</div>
                        </div>
                        <div class="col-md-6">
                            <label for="quantity" class="form-label"><?= $isIn ? 'Quantity received' : 'Quantity released' ?> *</label>
                            <input type="number" class="form-control<?= field_class($errors, 'quantity') ?>" id="quantity" name="quantity" value="<?= e($form['quantity']) ?>" min="1" max="1000000" step="1" required>
                            <?= field_error($errors, 'quantity') ?: '<div class="invalid-feedback" id="quantityFeedback">Enter a whole number of 1 or more.</div>' ?>
                        </div>
                        <div class="col-md-6">
                            <label for="transaction_date" class="form-label">Date and time *</label>
                            <input type="datetime-local" class="form-control<?= field_class($errors, 'transaction_date') ?>" id="transaction_date" name="transaction_date" value="<?= e($form['transaction_date']) ?>" required>
                            <?= field_error($errors, 'transaction_date') ?: '<div class="invalid-feedback">Enter the date and time.</div>' ?>
                        </div>
                        <div class="col-md-6">
                            <label for="reason" class="form-label">Reason *</label>
                            <select class="form-select<?= field_class($errors, 'reason') ?>" id="reason" name="reason" required>
                                <?php foreach ($reasons as $r): ?>
                                    <option value="<?= e($r) ?>"<?= selected($form['reason'], $r) ?>><?= e($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'reason') ?>
                        </div>
                        <div class="col-md-6">
                            <label for="reference_no" class="form-label">Reference number</label>
                            <input type="text" class="form-control<?= field_class($errors, 'reference_no') ?>" id="reference_no" name="reference_no" value="<?= e($form['reference_no']) ?>" maxlength="50" placeholder="<?= $isIn ? 'e.g. delivery receipt no.' : 'e.g. sales invoice no.' ?>">
                            <?= field_error($errors, 'reference_no') ?>
                            <div class="form-text">Leave blank to generate one automatically.</div>
                        </div>
                        <?php if ($isIn): ?>
                        <div class="col-12">
                            <label for="supplier_id" class="form-label">Supplier</label>
                            <select class="form-select<?= field_class($errors, 'supplier_id') ?>" id="supplier_id" name="supplier_id">
                                <option value="">None / not applicable</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>"<?= selected($form['supplier_id'], $s['id']) ?>><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'supplier_id') ?>
                        </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control<?= field_class($errors, 'remarks') ?>" id="remarks" name="remarks" rows="2" maxlength="500"><?= e($form['remarks']) ?></textarea>
                            <?= field_error($errors, 'remarks') ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary"><?= $isIn ? 'Record stock in' : 'Record stock out' ?></button>
                        <a href="<?= e(url('stock/history.php')) ?>" class="btn btn-light border">View history</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Latest <?= $isIn ? 'stock-in' : 'stock-out' ?> records</div>
            <?php if (!$recent): ?>
                <?= empty_state('clock-history', 'Nothing recorded yet', 'Records you save will appear here.') ?>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recent as $t): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold"><?= e($t['product_name']) ?></span>
                                <span><?= signed_quantity($t['transaction_type'], $t['quantity']) ?></span>
                            </div>
                            <div class="small text-muted"><?= e(format_datetime($t['transaction_date'])) ?> · <?= e($t['reference_no']) ?> · <?= e($t['user_name'] ?? '—') ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
