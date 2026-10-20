<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/product_image.php';
require_login();

$rows = db_all(
    $conn,
    'SELECT p.id, p.sku, p.name, p.image, c.name AS category_name, p.selling_price, p.quantity, p.min_stock,
            p.base_unit, p.base_allow_decimal, p.multi_unit
     FROM products p
     JOIN categories c ON c.id = p.category_id
     ORDER BY p.name ASC'
);

$unitsByProduct = [];
foreach (db_all($conn, 'SELECT id, product_id, unit_name, conversion_qty, selling_price, allow_decimal FROM product_units ORDER BY sort_order, id') as $u) {
    $unitsByProduct[(int) $u['product_id']][] = $u;
}

$products = [];
foreach ($rows as $r) {
    $products[] = [
        'id' => (int) $r['id'],
        'sku' => $r['sku'],
        'name' => $r['name'],
        'category' => $r['category_name'],
        'image' => product_image_url($r['image']),
        'price' => (int) round((float) $r['selling_price'] * 100),
        'stock' => (float) $r['quantity'],
        'min' => (int) $r['min_stock'],
        'unit' => $r['base_unit'],
        'dec' => (int) $r['base_allow_decimal'] === 1,
        'units' => (int) $r['multi_unit'] === 1 ? pos_units_payload($unitsByProduct[(int) $r['id']] ?? []) : [],
    ];
}
$productsJson = json_encode(
    $products,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);

$pageTitle = 'Point of sale';
$activeNav = 'pos';
$pageActions = '<a href="' . e(url('sales/history.php')) . '" class="btn btn-light border btn-sm"><i class="bi bi-receipt me-1"></i>Sales history</a>';
include __DIR__ . '/../includes/header.php';
?>
<link href="<?= e(url('assets/css/sales.css')) ?>" rel="stylesheet">

<?php if (!$products): ?>
    <div class="card"><?= empty_state(
        'box-seam',
        'No products to sell',
        'Add products and stock them in before making a sale.',
        '<a href="' . e(url('products/create.php')) . '" class="btn btn-primary btn-sm">Add product</a>'
    ) ?></div>
<?php else: ?>
    <div id="posApp" data-process-url="<?= e(url('sales/process_sale.php')) ?>" data-csrf="<?= e(csrf_token()) ?>"
        data-currency="<?= e(CURRENCY) ?>">
        <script type="application/json" id="posData"><?= $productsJson ?></script>

        <div class="row g-3">
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">
                        <label for="posSearch" class="visually-hidden">Search products</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-control" id="posSearch"
                                placeholder="Search by name, code or category (Enter adds the first match)"
                                autocomplete="off" autofocus>
                        </div>
                    </div>
                    <div class="pos-products" id="posProducts"></div>
                    <div class="card-footer small text-muted" id="posProductsNote"></div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="card pos-sticky">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Current sale</span>
                        <button type="button" class="btn btn-light btn-sm border" id="posClear">Clear</button>
                    </div>

                    <div class="pos-cart" id="posCart"></div>

                    <div class="pos-totals">
                        <div class="pos-total-line text-muted small"><span>Items</span><span id="posUnits">0</span></div>
                        <div class="pos-total-line text-muted"><span>Subtotal</span><span id="posSubtotal"></span></div>
                        <div class="pos-total-line"><span class="fw-semibold">Total</span><span class="pos-grand"
                                id="posTotal"></span></div>
                    </div>

                    <div class="card-body">
                        <div id="posAlert" class="alert alert-danger py-2 d-none" role="alert"></div>

                        <div class="form-label">Payment method</div>
                        <div class="btn-group w-100 mb-3" role="group" aria-label="Payment method">
                            <input type="radio" class="btn-check" name="posMethod" id="posMethodCash" value="cash"
                                autocomplete="off" checked>
                            <label class="btn btn-outline-primary" for="posMethodCash"><i
                                    class="bi bi-cash-coin me-1"></i>Cash</label>
                            <input type="radio" class="btn-check" name="posMethod" id="posMethodGcash" value="gcash"
                                autocomplete="off">
                            <label class="btn btn-outline-primary" for="posMethodGcash"><i
                                    class="bi bi-phone me-1"></i>GCash</label>
                        </div>

                        <div id="posCashBox">
                            <label for="posPaid" class="form-label">Amount paid</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= e(CURRENCY) ?></span>
                                <input type="text" inputmode="decimal" class="form-control" id="posPaid" placeholder="0.00"
                                    autocomplete="off">
                                <button type="button" class="btn btn-light border" id="posExact">Exact</button>
                            </div>
                        </div>

                        <div id="posGcashBox" class="d-none">
                            <label for="posRef" class="form-label">GCash reference number</label>
                            <input type="text" class="form-control" id="posRef" maxlength="30"
                                placeholder="e.g. 1234567890123" autocomplete="off">
                            <div class="form-text">Check that the payment arrived in the store's GCash app, then enter its
                                reference number.</div>
                        </div>

                        <div class="d-flex justify-content-between mt-2">
                            <span class="small" id="posPayHint">&nbsp;</span>
                            <span class="pos-change" id="posChange"></span>
                        </div>

                        <button type="button" class="btn btn-primary w-100 mt-3" id="posComplete" disabled>
                            <i class="bi bi-check2-circle me-1"></i>Complete sale
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="posConfirm" tabindex="-1" aria-labelledby="posConfirmTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-6" id="posConfirmTitle">Complete this sale?</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="pos-total-line"><span>Items</span><span id="cfUnits"></span></div>
                        <div class="pos-total-line"><span>Method</span><span id="cfMethod"></span></div>
                        <div class="pos-total-line"><span>Total</span><strong id="cfTotal"></strong></div>
                        <div class="pos-total-line"><span>Paid</span><span id="cfPaid"></span></div>
                        <div class="pos-total-line"><span>Change</span><strong id="cfChange"></strong></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Back</button>
                        <button type="button" class="btn btn-primary btn-sm" id="cfYes">
                            <span class="spinner-border spinner-border-sm me-1 d-none" id="cfSpinner"
                                aria-hidden="true"></span>Confirm sale
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="posDone" tabindex="-1" aria-labelledby="posDoneTitle" aria-hidden="true"
            data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-6" id="posDoneTitle"><i
                                class="bi bi-check-circle-fill text-success me-1"></i>Sale recorded</h2>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2 text-muted small">Receipt <span id="doneReceipt"></span></div>
                        <div class="pos-total-line"><span>Method</span><span id="doneMethod"></span></div>
                        <div class="pos-total-line"><span>Total</span><strong id="doneTotal"></strong></div>
                        <div class="pos-total-line"><span>Paid</span><span id="donePaid"></span></div>
                        <div class="pos-total-line"><span>Change</span><strong id="doneChange"></strong></div>
                    </div>
                    <div class="modal-footer">
                        <a href="#" target="_blank" rel="noopener" class="btn btn-light border btn-sm"
                            id="doneReceiptLink"><i class="bi bi-printer me-1"></i>Receipt</a>
                        <button type="button" class="btn btn-primary btn-sm" id="doneNew">New sale</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= e(url('assets/js/pos.js')) ?>" defer></script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>