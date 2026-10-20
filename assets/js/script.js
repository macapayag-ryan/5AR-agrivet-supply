/* Inventory System - interactive features and form validation */
document.addEventListener('DOMContentLoaded', function () {

    /* ---- Mobile sidebar ---- */
    var toggle = document.getElementById('sidebarToggle');
    var overlay = document.getElementById('sidebarOverlay');
    if (toggle) {
        toggle.addEventListener('click', function () { document.body.classList.toggle('sidebar-open'); });
    }
    if (overlay) {
        overlay.addEventListener('click', function () { document.body.classList.remove('sidebar-open'); });
    }

    /* ---- POS: type a quantity straight into the cart ---- */
    var posCart = document.getElementById('posCart');
    if (posCart) {
        // Selecting the number on focus means typing replaces it (no need to delete first)
        posCart.addEventListener('focusin', function (event) {
            if (event.target.matches('input[data-action="qty"]')) {
                event.target.select();
            }
        });
        // Enter confirms the quantity and returns to the search box for the next item
        posCart.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && event.target.matches('input[data-action="qty"]')) {
                event.preventDefault();
                event.target.blur();   // fires the existing change handler
                setTimeout(function () {
                    var search = document.getElementById('posSearch');
                    if (search) { search.focus(); }
                }, 0);
            }
        });
    }

    /* ---- Client-side validation (the server validates again) ---- */
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    /* ---- Confirmation dialog for destructive actions ---- */
    var modalEl = document.getElementById('confirmModal');
    if (modalEl && window.bootstrap) {
        var modal = new bootstrap.Modal(modalEl);
        var message = document.getElementById('confirmMessage');
        var pendingForm = null;

        document.querySelectorAll('form.js-confirm').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                pendingForm = form;
                message.textContent = form.getAttribute('data-confirm') || 'Are you sure?';
                modal.show();
            });
        });
        document.getElementById('confirmYes').addEventListener('click', function () {
            if (pendingForm) {
                modal.hide();
                pendingForm.submit();   // submit() does not trigger the submit event again
            }
        });
    }

    /* ---- Stock in / out: show current stock for the chosen product ---- */
    var productSelect = document.querySelector('[data-stock-select]');
    if (productSelect) {
        var hint = document.getElementById('stockHint');
        var qty = document.getElementById('quantity');
        var isOut = productSelect.getAttribute('data-stock-select') === 'out';
        var feedback = document.getElementById('quantityFeedback');

        var updateStock = function () {
            var option = productSelect.options[productSelect.selectedIndex];
            var stock = option && option.value ? parseInt(option.getAttribute('data-stock'), 10) : null;
            if (stock === null) {
                hint.textContent = '\u00a0';
                qty.removeAttribute('max');
                return;
            }
            hint.textContent = 'Current stock: ' + stock + ' unit(s)';
            if (isOut) {
                qty.max = stock;
                if (feedback) {
                    feedback.textContent = stock > 0
                        ? 'Enter a whole number from 1 to ' + stock + '.'
                        : 'This product has no stock to release.';
                }
            }
        };
        productSelect.addEventListener('change', updateStock);
        updateStock();
    }

    /* ---- Reports: show only the filters that apply to the chosen report ---- */
    var reportType = document.getElementById('reportType');
    if (reportType) {
        var toggleFilters = function () {
            var isTx = reportType.value === 'transactions';
            document.querySelectorAll('.only-inventory').forEach(function (el) { el.style.display = isTx ? 'none' : ''; });
            document.querySelectorAll('.only-transactions').forEach(function (el) { el.style.display = isTx ? '' : 'none'; });
        };
        reportType.addEventListener('change', toggleFilters);
        toggleFilters();
    }

    /* ---- Print button ---- */
    document.querySelectorAll('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });

    /* ---- Dashboard charts (brand greens) ---- */
    var canvas = document.getElementById('stockChart');
    if (canvas && window.Chart) {
        Chart.defaults.font.family = '"Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif';
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: JSON.parse(canvas.getAttribute('data-labels')),
                datasets: [{
                    label: 'Units in stock',
                    data: JSON.parse(canvas.getAttribute('data-values')),
                    backgroundColor: '#3d8b50',
                    hoverBackgroundColor: '#245c3a',
                    borderRadius: 6,
                    maxBarThickness: 38
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1a4529',
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: function (ctx) { return ctx.parsed.y.toLocaleString() + ' units in stock'; }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#26332b' }, border: { color: '#cfd8cd' } },
                    y: { beginAtZero: true, ticks: { precision: 0, color: '#758176' }, grid: { color: '#e4e9e2' }, border: { display: false } }
                }
            }
        });
    }

    var statusCanvas = document.getElementById('statusChart');
    if (statusCanvas && window.Chart) {
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: JSON.parse(statusCanvas.getAttribute('data-labels')),
                datasets: [{
                    data: JSON.parse(statusCanvas.getAttribute('data-values')),
                    backgroundColor: ['#3d8b50', '#e7b44a', '#a63d3d'],
                    borderColor: '#ffffff',
                    borderWidth: 3
                }]
            },
            options: {
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1a4529',
                        displayColors: false,
                        callbacks: {
                            label: function (ctx) { return ctx.label + ': ' + ctx.parsed.toLocaleString() + ' product(s)'; }
                        }
                    }
                }
            }
        });
    }

    /* ---- Fade out success messages after a few seconds ---- */
    document.querySelectorAll('.alert-success').forEach(function (alertEl) {
        setTimeout(function () {
            if (window.bootstrap && document.body.contains(alertEl)) {
                bootstrap.Alert.getOrCreateInstance(alertEl).close();
            }
        }, 6000);
    });
});