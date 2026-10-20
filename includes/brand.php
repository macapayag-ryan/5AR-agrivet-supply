<?php
/**
 * Store identity for 5AR AGRIVET SUPPLY.
 * Included by includes/sidebar.php and login.php so the name, tagline and logo
 * are defined in one place.
 */
if (!defined('BRAND_NAME')) {
    define('BRAND_NAME', '5AR AGRIVET SUPPLY');
    define('BRAND_SHORT', '5AR');
    define('BRAND_WORD', 'AGRIVET SUPPLY');
    define('BRAND_TAGLINE', 'Your trusted partner in poultry feeds and agricultural supplies.');
    define('BRAND_SUBTITLE', 'Inventory & Sales Management');
}

/**
 * Logo mark: a golden sprout (two leaves on a stem) inside a forest-green
 * rounded tile with a gold border. Pure inline SVG + CSS, no image file needed.
 * $size: '' (sidebar) or 'lg' (login page, dashboard banner).
 */
if (!function_exists('brand_mark')) {
    function brand_mark(string $size = ''): string
    {
        $class = 'brand-mark' . ($size === 'lg' ? ' brand-mark--lg' : '');
        return '<span class="' . $class . '" aria-hidden="true">'
            . '<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" focusable="false">'
            . '<path d="M24 41V22" fill="none" stroke="#E7B44A" stroke-width="3" stroke-linecap="round"/>'
            . '<path d="M24 29C14 29 9 22 9 12c10 0 15 6 15 17z" fill="#E7B44A"/>'
            . '<path d="M24 23c0-9 5-15 15-15 0 9-5 15-15 15z" fill="#F3CE7E"/>'
            . '<path d="M14 41h20" fill="none" stroke="#E7B44A" stroke-width="3" stroke-linecap="round"/>'
            . '</svg></span>';
    }
}