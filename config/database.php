<?php
/**
 * config/database.php
 * Database connection and basic application settings.
 * Change these values if your XAMPP setup is different.
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');            // XAMPP's default root password is empty
define('DB_NAME', 'inventory_system');

// URL path of the project, no trailing slash.
// If you put the folder in htdocs/inventory_system, keep it as is.
define('BASE_URL', '/inventory_system');

define('APP_NAME', 'Inventory System');
define('CURRENCY', '₱');
define('ITEMS_PER_PAGE', 10);

date_default_timezone_set('Asia/Manila');

// Make MySQLi throw exceptions so errors can be caught (and hidden from users).
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    mysqli_set_charset($conn, 'utf8mb4');
    // Keep MySQL's clock in the same timezone as PHP.
    mysqli_query($conn, "SET time_zone = '" . date('P') . "'");
} catch (mysqli_sql_exception $ex) {
    error_log('Database connection failed: ' . $ex->getMessage());
    http_response_code(500);
    exit('Unable to connect to the database. Check config/database.php and make sure MySQL is running.');
}
