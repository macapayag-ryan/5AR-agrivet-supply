<?php
/**
 * products/_form.php - shared form for create.php and edit.php.
 * Expects: $product, $errors, $isEdit, $categories, $suppliers
 */
$currentImage = product_image_url($product['image'] ?? '');
?>
<form method="post" class="needs-validation" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header">Product details</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="sku" class="form-label">SKU / product code *</label>
                    <input type="text" class="form-control<?= field_class($errors, 'sku') ?>" id="sku" name="sku"
                        value="<?= e($product['sku']) ?>" maxlength="50" required>
                    <?= field_error($errors, 'sku') ?: '<div class="invalid-feedback">Enter a product code.</div>' ?>
                </div>
                <div class="col-md-8">
                    <label for="name" class="form-label">Product name *</label>
                    <input type="text" class="form-control<?= field_class($errors, 'name') ?>" id="name" name="name"
                        value="<?= e($product['name']) ?>" maxlength="150" required>
                    <?= field_error($errors, 'name') ?: '<div class="invalid-feedback">Enter a product name.</div>' ?>
                </div>
                <div class="col-md-6">
                    <label for="category_id" class="form-label">Category *</label>
                    <select class="form-select<?= field_class($errors, 'category_id') ?>" id="category_id"
                        name="category_id" required>
                        <option value="">Choose a category</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= selected($product['category_id'], $c['id']) ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'category_id') ?: '<div class="invalid-feedback">Choose a category.</div>' ?>
                    <?php if (!$categories): ?>
                        <div class="form-text text-danger">No categories yet. <a
                                href="<?= e(url('categories/index.php')) ?>">Add one first</a>.</div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="supplier_id" class="form-label">Supplier</label>
                    <select class="form-select<?= field_class($errors, 'supplier_id') ?>" id="supplier_id"
                        name="supplier_id">
                        <option value="">None</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= selected($product['supplier_id'], $s['id']) ?>>
                                <?= e($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'supplier_id') ?>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control<?= field_class($errors, 'description') ?>" id="description"
                        name="description" rows="3" maxlength="1000"><?= e($product['description']) ?></textarea>
                    <?= field_error($errors, 'description') ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Product image</div>
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <img id="imagePreview" src="<?= e($currentImage) ?>" alt="Product image preview"
                        class="rounded border"
                        style="width:120px;height:120px;object-fit:cover;<?= $currentImage === '' ? 'display:none;' : '' ?>">
                    <div id="imageEmpty"
                        class="rounded border bg-light text-muted d-flex align-items-center justify-content-center"
                        style="width:120px;height:120px;<?= $currentImage !== '' ? 'display:none !important;' : '' ?>">
                        <i class="bi bi-image fs-1"></i>
                    </div>
                </div>
                <div class="flex-grow-1" style="min-width:240px;">
                    <label for="image" class="form-label">Upload image</label>
                    <input type="file" class="form-control<?= field_class($errors, 'image') ?>" id="image" name="image"
                        accept="image/jpeg,image/png,image/webp">
                    <?= field_error($errors, 'image') ?>
                    <div class="form-text">JPG, PNG or WEBP, up to 2
                        MB.<?= $isEdit && $currentImage !== '' ? ' Choosing a new file replaces the current image.' : '' ?>
                    </div>
                    <?php if ($isEdit && $currentImage !== ''): ?>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                            <label class="form-check-label" for="remove_image">Remove current image</label>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Pricing and stock</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="cost_price" class="form-label">Cost price (<?= e(CURRENCY) ?>) *</label>
                    <input type="number" class="form-control<?= field_class($errors, 'cost_price') ?>" id="cost_price"
                        name="cost_price" value="<?= e($product['cost_price']) ?>" min="0" step="0.01" required>
                    <?= field_error($errors, 'cost_price') ?>
                </div>
                <div class="col-md-3">
                    <label for="selling_price" class="form-label">Selling price (<?= e(CURRENCY) ?>) *</label>
                    <input type="number" class="form-control<?= field_class($errors, 'selling_price') ?>"
                        id="selling_price" name="selling_price" value="<?= e($product['selling_price']) ?>" min="0"
                        step="0.01" required>
                    <?= field_error($errors, 'selling_price') ?>
                </div>
                <div class="col-md-3">
                    <?php if ($isEdit): ?>
                        <label for="quantity" class="form-label">Current stock</label>
                        <input type="text" class="form-control" id="quantity"
                            value="<?= e(fmt_qty($product['quantity'])) ?>" disabled>
                        <div class="form-text">Change stock using Stock In / Stock Out so it is recorded in the history.
                        </div>
                    <?php else: ?>
                        <label for="quantity" class="form-label">Opening stock *</label>
                        <input type="number" class="form-control<?= field_class($errors, 'quantity') ?>" id="quantity"
                            name="quantity" value="<?= e($product['quantity']) ?>" min="0" step="1" required>
                        <?= field_error($errors, 'quantity') ?>
                        <div class="form-text">Saved as a stock-in record if above 0.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label for="min_stock" class="form-label">Minimum stock level *</label>
                    <input type="number" class="form-control<?= field_class($errors, 'min_stock') ?>" id="min_stock"
                        name="min_stock" value="<?= e($product['min_stock']) ?>" min="0" step="1" required>
                    <?= field_error($errors, 'min_stock') ?>
                    <div class="form-text">Low-stock alert at or below this.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Selling units</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="base_unit" class="form-label">Base unit (stock is counted in this) *</label>
                    <input type="text" class="form-control<?= field_class($errors, 'base_unit') ?>" id="base_unit"
                        name="base_unit" value="<?= e($product['base_unit']) ?>" maxlength="30"
                        placeholder="e.g. pc, kilo, sack" required>
                    <?= field_error($errors, 'base_unit') ?>
                    <div class="form-text">Your stock and the selling price above are per base unit.</div>
                </div>
                <div class="col-md-8 d-flex flex-column justify-content-center gap-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="base_allow_decimal"
                            name="base_allow_decimal" value="1" <?= (string) $product['base_allow_decimal'] === '1' ? ' checked' : '' ?>>
                        <label class="form-check-label" for="base_allow_decimal">Allow decimal quantities for the base
                            unit (e.g. 2.5 kilos)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="multi_unit" name="multi_unit" value="1"
                            <?= (string) $product['multi_unit'] === '1' ? ' checked' : '' ?>>
                        <label class="form-check-label" for="multi_unit">Sell in multiple units (sack, box, pack,
                            ...)</label>
                    </div>
                </div>
            </div>

            <div id="unitsBox" class="mt-3<?= (string) $product['multi_unit'] === '1' ? '' : ' d-none' ?>">
                <?php if (isset($errors['units'])): ?>
                    <div class="alert alert-danger py-2"><?= e($errors['units']) ?></div><?php endif; ?>
                <div class="form-text mb-2">
                    Conversion = how many <strong>base units</strong> are inside 1 of that unit.
                    Example: base unit <em>kilo</em>, unit <em>sack</em>, conversion <em>50</em>.
                    Each unit has its own selling price.
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2">
                        <thead>
                            <tr>
                                <th>Unit name</th>
                                <th>Conversion (base units)</th>
                                <th>Selling price (<?= e(CURRENCY) ?>)</th>
                                <th class="text-center">Decimals</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="unitsBody">
                            <?php foreach (($units ?? []) as $i => $u): ?>
                                <tr>
                                    <td><input type="hidden" name="units[<?= (int) $i ?>][id]" value="<?= e($u['id']) ?>">
                                        <input type="text" class="form-control form-control-sm"
                                            name="units[<?= (int) $i ?>][unit_name]" value="<?= e($u['unit_name']) ?>"
                                            maxlength="30" placeholder="sack">
                                    </td>
                                    <td><input type="number" class="form-control form-control-sm"
                                            name="units[<?= (int) $i ?>][conversion_qty]"
                                            value="<?= e($u['conversion_qty'] !== '' ? fmt_qty($u['conversion_qty']) : '') ?>"
                                            min="0.0001" max="100000" step="any" placeholder="50"></td>
                                    <td><input type="number" class="form-control form-control-sm"
                                            name="units[<?= (int) $i ?>][selling_price]"
                                            value="<?= e($u['selling_price']) ?>" min="0" step="0.01" placeholder="0.00">
                                    </td>
                                    <td class="text-center"><input class="form-check-input" type="checkbox"
                                            name="units[<?= (int) $i ?>][allow_decimal]" value="1" <?= (string) $u['allow_decimal'] === '1' ? ' checked' : '' ?>></td>
                                    <td class="text-end"><button type="button"
                                            class="btn btn-light btn-sm border js-unit-remove" aria-label="Remove unit"><i
                                                class="bi bi-x-lg"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-light btn-sm border" id="unitAdd"><i
                        class="bi bi-plus-lg me-1"></i>Add unit</button>
            </div>
        </div>
    </div>

    <template id="unitRowTpl">
        <tr>
            <td><input type="hidden" name="units[__N__][id]" value="">
                <input type="text" class="form-control form-control-sm" name="units[__N__][unit_name]" maxlength="30"
                    placeholder="sack">
            </td>
            <td><input type="number" class="form-control form-control-sm" name="units[__N__][conversion_qty]"
                    min="0.0001" max="100000" step="any" placeholder="50"></td>
            <td><input type="number" class="form-control form-control-sm" name="units[__N__][selling_price]" min="0"
                    step="0.01" placeholder="0.00"></td>
            <td class="text-center"><input class="form-check-input" type="checkbox" name="units[__N__][allow_decimal]"
                    value="1"></td>
            <td class="text-end"><button type="button" class="btn btn-light btn-sm border js-unit-remove"
                    aria-label="Remove unit"><i class="bi bi-x-lg"></i></button></td>
        </tr>
    </template>
    <script>
        (function () {
            var multi = document.getElementById('multi_unit');
            var box = document.getElementById('unitsBox');
            var body = document.getElementById('unitsBody');
            var tpl = document.getElementById('unitRowTpl');
            var counter = body.rows.length + 1000;
            multi.addEventListener('change', function () {
                box.classList.toggle('d-none', !multi.checked);
                if (multi.checked && body.rows.length === 0) { addRow(); }
            });
            document.getElementById('unitAdd').addEventListener('click', addRow);
            body.addEventListener('click', function (e) {
                var btn = e.target.closest('.js-unit-remove');
                if (btn) { btn.closest('tr').remove(); }
            });
            function addRow() {
                var html = tpl.innerHTML.replace(/__N__/g, String(counter++));
                body.insertAdjacentHTML('beforeend', html);
            }
        })();
    </script>
    <script>
        (function () {
            var input = document.getElementById('image');
            var preview = document.getElementById('imagePreview');
            var empty = document.getElementById('imageEmpty');
            var remove = document.getElementById('remove_image');
            var original = preview.getAttribute('src');
            function show(src) {
                if (src) {
                    preview.src = src;
                    preview.style.display = '';
                    empty.style.setProperty('display', 'none', 'important');
                } else {
                    preview.removeAttribute('src');
                    preview.style.display = 'none';
                    empty.style.removeProperty('display');
                }
            }
            input.addEventListener('change', function () {
                var file = input.files[0];
                if (file) {
                    if (remove) { remove.checked = false; }
                    show(URL.createObjectURL(file));
                } else {
                    show(remove && remove.checked ? '' : original);
                }
            });
            if (remove) {
                remove.addEventListener('change', function () {
                    if (remove.checked) {
                        input.value = '';
                        show('');
                    } else {
                        show(original);
                    }
                });
            }
        })();
    </script>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Save product' ?></button>
        <a href="<?= e(url('products/index.php')) ?>" class="btn btn-light border">Cancel</a>
    </div>
</form>