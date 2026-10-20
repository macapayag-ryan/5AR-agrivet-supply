<?php require_once __DIR__ . '/brand.php'; ?>
<aside class="sidebar no-print" id="sidebar">
    <a class="sidebar-brand" href="<?= e(url('dashboard.php')) ?>" title="<?= e(BRAND_NAME) ?>"
        aria-label="<?= e(BRAND_NAME) ?> – home">
        <?= brand_mark() ?>
        <span class="brand-text">
            <span class="brand-5ar"><?= e(BRAND_SHORT) ?></span>
        </span>
    </a>
    <nav class="sidebar-nav">
        <?= nav_link('dashboard.php', 'speedometer2', 'Dashboard', 'dashboard', $activeNav) ?>

        <div class="nav-heading">Sales</div>
        <?= nav_link('sales/pos.php', 'cart-check', 'Point of Sale', 'pos', $activeNav) ?>
        <?= nav_link('sales/history.php', 'receipt', 'Sales History', 'sales_history', $activeNav) ?>
        <?= nav_link('sales/reports.php', 'graph-up-arrow', 'Sales Reports', 'sales_reports', $activeNav) ?>

        <div class="nav-heading">Inventory</div>
        <?= nav_link('products/index.php', 'box-seam', 'Products', 'products', $activeNav) ?>
        <?= nav_link('categories/index.php', 'tags', 'Categories', 'categories', $activeNav) ?>
        <?= nav_link('suppliers/index.php', 'truck', 'Suppliers', 'suppliers', $activeNav) ?>

        <div class="nav-heading">Stock</div>
        <?= nav_link('stock/stock_in.php', 'box-arrow-in-down', 'Stock In', 'stock_in', $activeNav) ?>
        <?= nav_link('stock/stock_out.php', 'box-arrow-up', 'Stock Out', 'stock_out', $activeNav) ?>
        <?= nav_link('stock/history.php', 'clock-history', 'History', 'history', $activeNav) ?>

        <div class="nav-heading">Reports</div>
        <?= nav_link('reports/index.php', 'file-earmark-bar-graph', 'Reports', 'reports', $activeNav) ?>

        <?php if (is_admin()): ?>
            <div class="nav-heading">Administration</div>
            <?= nav_link('users/index.php', 'people', 'Users', 'users', $activeNav) ?>
            <?= nav_link('users/logs.php', 'journal-text', 'Activity Log', 'logs', $activeNav) ?>
        <?php endif; ?>
    </nav>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>