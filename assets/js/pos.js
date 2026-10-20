document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('posApp');
    if (!app) {
        return;
    }

    const processUrl = app.dataset.processUrl;
    const csrf = app.dataset.csrf;
    const currency = app.dataset.currency;

    let products = JSON.parse(document.getElementById('posData').textContent);
    let byId = new Map(products.map((p) => [p.id, p]));
    const cart = new Map();   // key "productId:unitId" -> { id, unit, qty }; unit 0 = base unit
    let busy = false;
    let lastAddedId = null;
    let lastAddedKey = null;
    let token = newToken();

    const $ = (id) => document.getElementById(id);
    const searchEl = $('posSearch');
    const productsEl = $('posProducts');
    const noteEl = $('posProductsNote');
    const cartEl = $('posCart');
    const paidEl = $('posPaid');
    const refEl = $('posRef');
    const cashBox = $('posCashBox');
    const gcashBox = $('posGcashBox');
    const methodCash = $('posMethodCash');
    const methodGcash = $('posMethodGcash');
    const alertEl = $('posAlert');
    const completeBtn = $('posComplete');
    const confirmModal = new bootstrap.Modal($('posConfirm'));
    const doneModal = new bootstrap.Modal($('posDone'));

    function newToken() {
        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
    }

    function money(cents) {
        return currency + (cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function parsePaid(text) {
        const t = text.trim().replace(/,/g, '');
        if (!/^\d{1,8}(\.\d{0,2})?$/.test(t)) {
            return null;
        }
        return Math.round(parseFloat(t) * 100);
    }

    function parseRef(text) {
        const t = text.replace(/\s+/g, '');
        return /^[A-Za-z0-9]{6,30}$/.test(t) ? t.toUpperCase() : null;
    }

    function isGcash() {
        return methodGcash.checked;
    }

    function paidNow() {
        return isGcash() ? cartTotal() : parsePaid(paidEl.value);
    }

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function qtyFmt(n) {
        return String(Math.round(n * 1000) / 1000);
    }

    function showUnit(p) {
        return p.dec || p.units.length > 0;
    }

    function unitOf(p, unitId) {
        if (unitId === 0) {
            return { id: 0, name: p.unit, conv: 1, price: p.price, dec: p.dec };
        }
        return p.units.find((u) => u.id === unitId) || null;
    }

    function minQty(u) {
        return u.dec ? 0.001 : 1;
    }

    function lineCents(line) {
        const u = unitOf(byId.get(line.id), line.unit);
        return Math.round(u.price * Math.round(line.qty * 1000) / 1000);
    }

    function lineBase(line) {
        const u = unitOf(byId.get(line.id), line.unit);
        return Math.round(line.qty * u.conv * 1000) / 1000;
    }

    function baseInCart(id, exceptKey) {
        let total = 0;
        cart.forEach((line, key) => {
            if (line.id === id && key !== exceptKey) {
                total += lineBase(line);
            }
        });
        return Math.round(total * 1000) / 1000;
    }

    // Largest quantity this line may have, given stock and the other lines of the same product.
    function maxQtyFor(line) {
        const p = byId.get(line.id);
        const u = unitOf(p, line.unit);
        const avail = Math.round((p.stock - baseInCart(line.id, keyOf(line))) * 1000) / 1000;
        const raw = avail / u.conv;
        return u.dec ? Math.floor(raw * 1000 + 1e-6) / 1000 : Math.floor(raw + 1e-9);
    }

    function keyOf(line) {
        return line.id + ':' + line.unit;
    }

    function cartTotal() {
        let total = 0;
        cart.forEach((line) => { total += lineCents(line); });
        return total;
    }

    function cartUnits() {
        let units = 0;
        cart.forEach((line) => { units += line.qty; });
        return Math.round(units * 1000) / 1000;
    }

    function showAlert(message) {
        alertEl.textContent = message;
        alertEl.classList.remove('d-none');
    }

    function hideAlert() {
        alertEl.classList.add('d-none');
        alertEl.textContent = '';
    }

    function renderProducts() {
        const query = searchEl.value.trim().toLowerCase();
        const matches = products.filter((p) => query === ''
            || p.name.toLowerCase().includes(query)
            || p.sku.toLowerCase().includes(query)
            || p.category.toLowerCase().includes(query));
        const shown = matches.slice(0, 40);
        const scrollTop = productsEl.scrollTop;

        productsEl.replaceChildren();
        if (shown.length === 0) {
            const empty = el('div', 'text-center text-muted p-4', 'No products match your search.');
            productsEl.appendChild(empty);
        }
        shown.forEach((p) => {
            const inCart = baseInCart(p.id, null);
            const unitLabel = showUnit(p) ? ' ' + p.unit : '';
            const btn = el('button', 'pos-product' + (inCart > 0 ? ' in-cart' : '') + (p.id === lastAddedId ? ' pos-flash' : ''));
            btn.type = 'button';
            btn.disabled = p.stock <= 0;
            btn.dataset.id = String(p.id);

            const left = el('div', 'd-flex align-items-center gap-2');
            let thumb;
            if (p.image) {
                thumb = el('img', 'rounded border flex-shrink-0');
                thumb.src = p.image;
                thumb.alt = '';
                thumb.loading = 'lazy';
            } else {
                thumb = el('span', 'rounded border bg-light text-muted d-inline-flex align-items-center justify-content-center flex-shrink-0');
                thumb.appendChild(el('i', 'bi bi-image'));
            }
            thumb.style.width = '48px';
            thumb.style.height = '48px';
            thumb.style.objectFit = 'cover';
            left.appendChild(thumb);

            const info = el('div');
            info.appendChild(el('div', 'pos-product-name', p.name));
            info.appendChild(el('div', 'pos-product-meta', p.sku + ' \u00b7 ' + p.category));
            if (p.units.length > 0) {
                info.appendChild(el('div', 'pos-product-meta', 'Also: ' + p.units.map((u) => u.name).join(', ')));
            }
            left.appendChild(info);

            const right = el('div', 'pos-product-price', money(p.price) + (showUnit(p) ? ' / ' + p.unit : ''));
            const stockText = p.stock <= 0 ? 'Out of stock' : qtyFmt(p.stock) + unitLabel + ' in stock';
            const stock = el('div', 'pos-product-meta' + (p.stock > 0 && p.stock <= p.min ? ' text-warning-emphasis' : ''), stockText);
            right.appendChild(stock);
            if (inCart > 0) {
                const maxed = inCart >= p.stock;
                const badge = el('div', 'pos-incart' + (maxed ? ' maxed' : ''));
                badge.appendChild(el('i', 'bi bi-check-lg me-1'));
                badge.appendChild(document.createTextNode(maxed ? 'In cart: ' + qtyFmt(inCart) + unitLabel + ' (all stock)' : 'In cart: ' + qtyFmt(inCart) + unitLabel));
                right.appendChild(badge);
            }

            btn.appendChild(left);
            btn.appendChild(right);
            productsEl.appendChild(btn);
        });

        noteEl.textContent = matches.length > shown.length
            ? 'Showing the first ' + shown.length + ' of ' + matches.length + ' matches. Type more to narrow the list.'
            : matches.length + ' product(s)';
        productsEl.scrollTop = scrollTop;
    }

    function renderCart() {
        cartEl.replaceChildren();
        if (cart.size === 0) {
            cartEl.appendChild(el('div', 'text-center text-muted p-4', 'No items yet. Click a product to add it.'));
        }
        cart.forEach((line, key) => {
            const p = byId.get(line.id);
            const u = unitOf(p, line.unit);
            const row = el('div', 'pos-cart-row' + (key === lastAddedKey ? ' pos-cart-new' : ''));
            row.dataset.key = key;

            const top = el('div', 'pos-cart-top');
            const info = el('div');
            info.appendChild(el('div', 'fw-medium', p.name));
            info.appendChild(el('div', 'small text-muted', money(u.price) + (showUnit(p) ? ' per ' + u.name : ' each') + ' \u00b7 ' + qtyFmt(p.stock) + (showUnit(p) ? ' ' + p.unit : '') + ' in stock'));
            if (p.units.length > 0) {
                const sel = el('select', 'form-select form-select-sm mt-1');
                sel.dataset.action = 'unit';
                sel.setAttribute('aria-label', 'Selling unit of ' + p.name);
                [unitOf(p, 0)].concat(p.units).forEach((opt) => {
                    const o = el('option', '', opt.name + ' \u2013 ' + money(opt.price));
                    o.value = String(opt.id);
                    o.selected = opt.id === line.unit;
                    sel.appendChild(o);
                });
                info.appendChild(sel);
            }
            const remove = el('button', 'btn btn-light btn-sm border');
            remove.type = 'button';
            remove.dataset.action = 'remove';
            remove.setAttribute('aria-label', 'Remove ' + p.name);
            remove.appendChild(el('i', 'bi bi-x-lg'));
            top.appendChild(info);
            top.appendChild(remove);

            const bottom = el('div', 'pos-cart-bottom');
            const qtyBox = el('div', 'pos-qty');
            const minus = el('button', 'btn btn-light btn-sm border');
            minus.type = 'button';
            minus.dataset.action = 'minus';
            minus.setAttribute('aria-label', 'Decrease quantity');
            minus.appendChild(el('i', 'bi bi-dash'));
            const input = el('input', 'form-control form-control-sm mx-1');
            input.type = 'number';
            input.min = String(minQty(u));
            input.max = String(maxQtyFor(line));
            input.step = u.dec ? '0.001' : '1';
            input.value = qtyFmt(line.qty);
            if (u.dec) {
                input.style.width = '5.5rem';
            }
            input.dataset.action = 'qty';
            input.setAttribute('aria-label', 'Quantity of ' + p.name);
            const plus = el('button', 'btn btn-light btn-sm border');
            plus.type = 'button';
            plus.dataset.action = 'plus';
            plus.setAttribute('aria-label', 'Increase quantity');
            plus.appendChild(el('i', 'bi bi-plus'));
            qtyBox.appendChild(minus);
            qtyBox.appendChild(input);
            qtyBox.appendChild(plus);
            if (showUnit(p)) {
                qtyBox.appendChild(el('span', 'small text-muted ms-1', u.name));
            }

            bottom.appendChild(qtyBox);
            bottom.appendChild(el('strong', '', money(lineCents(line))));

            row.appendChild(top);
            row.appendChild(bottom);
            cartEl.appendChild(row);
            if (key === lastAddedKey) {
                row.scrollIntoView({ block: 'nearest' });
            }
        });
        renderProducts();
        renderTotals();
        lastAddedId = null;
        lastAddedKey = null;
    }

    function renderTotals() {
        const total = cartTotal();
        $('posUnits').textContent = qtyFmt(cartUnits());
        $('posSubtotal').textContent = money(total);
        $('posTotal').textContent = money(total);

        const hint = $('posPayHint');
        const change = $('posChange');
        hint.classList.remove('pos-short');
        change.textContent = '';

        let ready = false;
        if (cart.size === 0) {
            hint.textContent = '\u00a0';
        } else if (isGcash()) {
            if (parseRef(refEl.value) === null) {
                hint.textContent = refEl.value.trim() === '' ? 'Enter the GCash reference number.' : 'Reference must be 6 to 30 letters or numbers.';
            } else {
                hint.textContent = 'Exact amount by GCash';
                ready = true;
            }
        } else {
            const paid = parsePaid(paidEl.value);
            if (paid === null) {
                hint.textContent = paidEl.value.trim() === '' ? 'Enter the amount paid.' : 'Enter a valid amount.';
            } else if (paid < total) {
                hint.textContent = 'Short by ' + money(total - paid);
                hint.classList.add('pos-short');
            } else {
                hint.textContent = 'Change due';
                change.textContent = money(paid - total);
                ready = true;
            }
        }
        completeBtn.disabled = !ready || busy;
    }

    function applyMethod() {
        const gcash = isGcash();
        cashBox.classList.toggle('d-none', gcash);
        gcashBox.classList.toggle('d-none', !gcash);
        hideAlert();
        renderTotals();
        (gcash ? refEl : paidEl).focus();
    }

    function addToCart(id) {
        const p = byId.get(id);
        if (!p || p.stock <= 0) {
            return;
        }
        hideAlert();
        const key = id + ':0';
        const existing = cart.get(key);
        if (!existing) {
            cart.set(key, { id: id, unit: 0, qty: 0 });
        }
        lastAddedId = id;
        lastAddedKey = key;
        setQty(key, (existing ? existing.qty : 0) + 1, true);
    }

    function setQty(key, value, keepAlert) {
        const line = cart.get(key);
        const p = byId.get(line.id);
        const u = unitOf(p, line.unit);
        let qty = u.dec ? Math.round(parseFloat(value) * 1000) / 1000 : parseInt(value, 10);
        const min = minQty(u);
        const max = maxQtyFor(line);
        if (max < min) {
            cart.delete(key);
            showAlert('Not enough stock of ' + p.name + ' for that unit. Only ' + qtyFmt(p.stock) + ' ' + p.unit + ' in stock.');
            renderCart();
            return;
        }
        if (Number.isNaN(qty) || qty < min) {
            qty = min;
        }
        if (qty > max) {
            qty = max;
            showAlert('Only ' + qtyFmt(max) + ' ' + u.name + ' of ' + p.name + ' available in stock.');
        } else if (!keepAlert) {
            hideAlert();
        }
        line.qty = qty;
        renderCart();
    }

    function changeUnit(key, unitId) {
        const line = cart.get(key);
        const p = byId.get(line.id);
        if (!line || !unitOf(p, unitId)) {
            return;
        }
        hideAlert();
        const newKey = line.id + ':' + unitId;
        const target = cart.get(newKey);
        const entries = Array.from(cart.entries());
        cart.clear();
        entries.forEach(([k, l]) => {
            if (k === key) {
                if (!target) {
                    cart.set(newKey, { id: l.id, unit: unitId, qty: 1 });
                }
            } else if (k === newKey) {
                l.qty = l.qty + 1;
                cart.set(k, l);
            } else {
                cart.set(k, l);
            }
        });
        lastAddedKey = newKey;
        setQty(newKey, cart.get(newKey).qty, true);
    }

    function applyFreshProducts(list) {
        const fresh = new Map(list.map((r) => [r.id, r]));
        products.forEach((p) => {
            const r = fresh.get(p.id);
            if (r) {
                p.price = r.price;
                p.stock = r.quantity;
                p.dec = r.dec;
                p.units = r.units;
            }
        });
        Array.from(cart.keys()).forEach((key) => {
            const line = cart.get(key);
            const p = byId.get(line.id);
            const r = fresh.get(line.id);
            if ((r && r.quantity <= 0) || (r && !unitOf(p, line.unit))) {
                cart.delete(key);
                return;
            }
            if (r) {
                const u = unitOf(p, line.unit);
                const max = maxQtyFor(line);
                if (max < minQty(u)) {
                    cart.delete(key);
                } else if (line.qty > max) {
                    line.qty = max;
                } else if (!u.dec && line.qty % 1 !== 0) {
                    line.qty = Math.max(1, Math.floor(line.qty));
                }
            }
        });
    }

    function resetSale() {
        cart.clear();
        paidEl.value = '';
        refEl.value = '';
        hideAlert();
        token = newToken();
        renderProducts();
        renderCart();
    }

    async function submitSale() {
        if (busy || cart.size === 0) {
            return;
        }
        const paid = paidNow();
        const total = cartTotal();
        const ref = isGcash() ? parseRef(refEl.value) : '';
        if (paid === null || paid < total || ref === null) {
            return;
        }

        busy = true;
        $('cfYes').disabled = true;
        $('cfSpinner').classList.remove('d-none');
        completeBtn.disabled = true;

        const body = new URLSearchParams({
            csrf_token: csrf,
            checkout_token: token,
            cart: JSON.stringify(Array.from(cart.values(), (l) => ({ id: l.id, unit: l.unit, qty: qtyFmt(l.qty) }))),
            amount_paid: (paid / 100).toFixed(2),
            expected_total: String(total),
            payment_method: isGcash() ? 'gcash' : 'cash',
            payment_reference: ref
        });

        let data = null;
        let networkError = false;
        try {
            const res = await fetch(processUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            const text = await res.text();
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                data = { ok: false, message: 'Your session may have expired. Reload the page and log in again.' };
            }
        } catch (fetchError) {
            networkError = true;
        }

        busy = false;
        $('cfYes').disabled = false;
        $('cfSpinner').classList.add('d-none');
        confirmModal.hide();

        if (networkError) {
            showAlert('Could not reach the server. The sale may not have been recorded. Press Complete sale again; duplicate protection prevents it from being saved twice.');
            renderTotals();
            return;
        }

        if (Array.isArray(data.products)) {
            applyFreshProducts(data.products);
        }

        if (data.ok) {
            $('doneReceipt').textContent = data.receipt_number;
            $('doneMethod').textContent = data.method === 'gcash' ? 'GCash' : 'Cash';
            $('doneTotal').textContent = money(data.total);
            $('donePaid').textContent = money(data.paid);
            $('doneChange').textContent = money(data.change);
            $('doneReceiptLink').href = data.receipt_url;
            resetSale();
            doneModal.show();
            return;
        }

        showAlert(data.message || 'The sale could not be completed.');
        renderProducts();
        renderCart();
    }

    productsEl.addEventListener('click', (event) => {
        const btn = event.target.closest('.pos-product');
        if (btn && !btn.disabled) {
            addToCart(parseInt(btn.dataset.id, 10));
        }
    });

    cartEl.addEventListener('click', (event) => {
        const actionEl = event.target.closest('[data-action]');
        const row = event.target.closest('.pos-cart-row');
        if (!actionEl || !row || actionEl.dataset.action === 'unit' || actionEl.dataset.action === 'qty') {
            return;
        }
        const key = row.dataset.key;
        const line = cart.get(key);
        const action = actionEl.dataset.action;
        if (action === 'remove') {
            cart.delete(key);
            hideAlert();
            renderCart();
        } else if (action === 'plus') {
            lastAddedId = line.id;
            setQty(key, line.qty + 1);
        } else if (action === 'minus') {
            setQty(key, line.qty - 1);
        }
    });

    cartEl.addEventListener('change', (event) => {
        const row = event.target.closest('.pos-cart-row');
        if (!row) {
            return;
        }
        if (event.target.closest('select[data-action="unit"]')) {
            changeUnit(row.dataset.key, parseInt(event.target.value, 10));
            return;
        }
        const input = event.target.closest('input[data-action="qty"]');
        if (input) {
            setQty(row.dataset.key, input.value);
        }
    });

    searchEl.addEventListener('input', renderProducts);
    searchEl.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            searchEl.value = '';
            renderProducts();
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const query = searchEl.value.trim().toLowerCase();
            const exact = products.find((p) => p.sku.toLowerCase() === query && p.stock > 0);
            const first = exact || products.find((p) => p.stock > 0 && (query === '' ? false
                : p.name.toLowerCase().includes(query) || p.sku.toLowerCase().includes(query) || p.category.toLowerCase().includes(query)));
            if (first) {
                addToCart(first.id);
                searchEl.select();
            }
        }
    });

    methodCash.addEventListener('change', applyMethod);
    methodGcash.addEventListener('change', applyMethod);

    paidEl.addEventListener('input', renderTotals);
    paidEl.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            if (!completeBtn.disabled) {
                completeBtn.click();
            }
        }
    });

    refEl.addEventListener('input', renderTotals);
    refEl.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            if (!completeBtn.disabled) {
                completeBtn.click();
            }
        }
    });

    $('posExact').addEventListener('click', () => {
        paidEl.value = (cartTotal() / 100).toFixed(2);
        renderTotals();
        paidEl.focus();
    });

    $('posClear').addEventListener('click', () => {
        if (cart.size === 0 && paidEl.value === '' && refEl.value === '') {
            return;
        }
        cart.clear();
        paidEl.value = '';
        refEl.value = '';
        hideAlert();
        renderCart();
        searchEl.focus();
    });

    completeBtn.addEventListener('click', () => {
        const paid = paidNow();
        const total = cartTotal();
        const ref = isGcash() ? parseRef(refEl.value) : '';
        if (cart.size === 0 || paid === null || paid < total || ref === null) {
            return;
        }
        hideAlert();
        $('cfUnits').textContent = String(cartUnits());
        $('cfMethod').textContent = isGcash() ? 'GCash ' + ref : 'Cash';
        $('cfTotal').textContent = money(total);
        $('cfPaid').textContent = money(paid);
        $('cfChange').textContent = money(paid - total);
        confirmModal.show();
    });

    $('cfYes').addEventListener('click', submitSale);

    $('doneNew').addEventListener('click', () => {
        doneModal.hide();
        searchEl.value = '';
        renderProducts();
        searchEl.focus();
    });

    window.addEventListener('beforeunload', (event) => {
        if (cart.size > 0 && !busy) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    renderProducts();
    renderCart();
});