# Inventory Management System

A small-business inventory system built with **PHP (procedural, MySQLi prepared statements)**, **MySQL**, **HTML5/CSS3**, **JavaScript**, **Bootstrap 5**, **Bootstrap Icons** and **Chart.js**. It runs locally on **XAMPP**.

## 1. Install XAMPP

1. Download XAMPP (PHP 8.x version) from <https://www.apachefriends.org> and install it (default folder `C:\xampp`).
2. Open the **XAMPP Control Panel**.

## 2. Start Apache and MySQL

1. In the Control Panel, click **Start** next to **Apache** and **MySQL**. Both should turn green.
2. If Apache will not start, another program is probably using port 80 (Skype, IIS, another web server). Stop that program or change Apache's port in **Config > httpd.conf**.

## 3. Copy the project

Copy the whole `inventory_system` folder into XAMPP's web folder:

```
C:\xampp\htdocs\inventory_system
```

The folder name matters: the app expects the address `http://localhost/inventory_system`. If you use a different folder name, change `BASE_URL` in `config/database.php`.

## 4. Create the database

1. Open <http://localhost/phpmyadmin>.
2. Click the **Import** tab, choose `inventory_system.sql` from the project folder and click **Import**.
   The file creates the `inventory_system` database, all tables, relationships, and sample data.
3. (Optional) Open `config/database.php` and check the settings. XAMPP's defaults are already filled in: user `root`, empty password.

## 5. Open the system

Go to <http://localhost/inventory_system> and log in:

| Role          | Username | Password    |
|---------------|----------|-------------|
| Administrator | `admin`  | `Admin@123` |
| Staff         | `staff`  | `Staff@123` |

**Change these passwords right away** (Users > edit). To start with an empty system, delete the "OPTIONAL SAMPLE DATA" section at the bottom of the SQL file before importing, then create your own administrator by inserting one row into `users` with a hash made by `password_hash()`.

> The pages load Bootstrap, Bootstrap Icons and Chart.js from a CDN, so the computer needs an internet connection the first time (the browser caches the files). To work fully offline, download those files into `assets/` and change the `<link>`/`<script>` addresses in `includes/header.php`, `includes/footer.php` and `login.php`.

## What each role can do

| Action                                            | Staff | Administrator |
|---------------------------------------------------|:-----:|:-------------:|
| Dashboard, view products, categories, suppliers   | yes   | yes           |
| Add / edit products, categories, suppliers        | yes   | yes           |
| Stock in, stock out, history, reports + CSV       | yes   | yes           |
| **Delete** products, categories, suppliers        | no    | yes           |
| Manage users, view the activity log               | no    | yes           |

## Features

- **Login**: `password_hash()` / `password_verify()`, session regeneration after login, 30-minute idle timeout, 5 failed attempts = 1-minute lock, POST + CSRF logout. Disabled users are blocked on their next request.
- **Dashboard**: product count, total units, low-stock, out-of-stock, categories, items needing attention, recent movements and a stock-by-category chart. Every figure is a live query.
- **Products**: add, view, edit, delete (with confirmation), search by name/code, filter by category and stock status, pagination. SKUs are unique and stored in capitals.
- **Categories / Suppliers**: add, edit, delete when safe. Duplicate names are rejected, product counts are shown.
- **Stock in / out**: the product row is locked, the quantity is updated and the history row is written inside **one database transaction**. Stock-out can never go below zero.
- **History**: read-only audit trail with search and filters (product, type, dates). The application has no screen or query that edits or deletes transactions.
- **Low stock**: a product is *low* when `0 < quantity <= minimum stock` and *out* when `quantity = 0`. Always calculated from the database, never hard-coded.
- **Reports**: inventory report and stock in/out report, filters, print layout, and CSV export (opens in Excel).
- **Users and activity log**: administrator and staff roles; logins, changes, stock movements, and exports are logged.

## Design decisions worth knowing

- **Stock changes only through transactions.** The product edit form shows the quantity read-only. A new product's opening stock is saved as a stock-in record so the history always adds up to the current quantity.
- **Correcting a mistake:** record an opposite transaction (for example a stock-out with the reason "Other" and an explanation in the remarks). The wrong entry stays in the history.
- **Products with history cannot be deleted**, and suppliers linked to products or deliveries cannot be deleted. Users are disabled, not deleted. This keeps the audit trail intact.
- **Prices** use `DECIMAL(10,2)`; timestamps use `DATETIME`. The currency symbol (`₱`) and timezone (`Asia/Manila`) are set in `config/database.php`.

## Security checklist

Prepared statements for all queries with user input, `htmlspecialchars()` on all output (`e()` helper), CSRF tokens on every POST form, server-side role checks (`require_login()` / `require_admin()`), server-side validation of quantities and prices, HttpOnly + SameSite session cookies, credentials in `config/database.php`, generic error messages (details go to the PHP error log), and `.htaccess` files denying direct access to `config/` and `includes/`.

For a real deployment, also: serve over HTTPS, set a password for the MySQL `root` user (update `DB_PASS`), create a dedicated MySQL user for this app, and turn off `display_errors` in `php.ini`.

## Project structure

```text
inventory_system/
├── config/database.php         Database + app settings
├── includes/                   auth.php, functions.php, header/sidebar/footer
├── assets/css/style.css        All custom styling
├── assets/js/script.js         Validation, confirm dialog, chart, small helpers
├── products/                   index, create, edit, view, delete, _form
├── categories/index.php
├── suppliers/index.php
├── stock/                      stock_in, stock_out, history, _stock_form (shared)
├── reports/index.php
├── users/                      index (users), logs (activity log)
├── dashboard.php  login.php  logout.php  index.php
├── inventory_system.sql        Database, tables and sample data
└── README.md
```

## Troubleshooting

| Problem | Fix |
|---|---|
| "Unable to connect to the database" | MySQL is not started, or the settings in `config/database.php` are wrong. |
| Page is unstyled or icons are missing | No internet for the CDN files (see the note in section 5). |
| Links lead to "Not Found" | The folder is not named `inventory_system` in `htdocs`, or `BASE_URL` does not match. |
| "Invalid or expired form token" | The page was open too long or cookies are blocked. Refresh and try again. |
| Cannot log in with the sample accounts | The SQL file was not imported fully. Re-import it (it recreates the tables). |
