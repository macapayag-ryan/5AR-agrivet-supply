<?php
/**
 * includes/header.php
 * Opens the page layout. Set these variables BEFORE including it:
 *   $pageTitle  - heading and <title>
 *   $activeNav  - key of the sidebar link to highlight
 *   $pageActions (optional) - HTML for buttons shown beside the heading
 */
$pageTitle = $pageTitle ?? 'Page';
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="app-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="app-main">
        <header class="topbar no-print">
            <button type="button" class="btn btn-light btn-sm d-lg-none" id="sidebarToggle" aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="topbar-user ms-auto">
                <span class="me-2">
                    <i class="bi bi-person-circle me-1"></i><?= e($_SESSION['full_name'] ?? '') ?>
                    <span class="badge text-bg-light border ms-1"><?= is_admin() ? 'Administrator' : 'Staff' ?></span>
                </span>
                <form method="post" action="<?= e(url('logout.php')) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Log out</button>
                </form>
            </div>
        </header>

        <main class="content">
            <div class="page-header">
                <h1 class="page-title"><?= e($pageTitle) ?></h1>
                <div class="page-actions no-print"><?= $pageActions ?? '' ?></div>
            </div>
            <?= flash_render() ?>
