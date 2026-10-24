# Inventory Management System with Point of Sale (POS) for 5AR Agrivet

A web-based inventory and point-of-sale system for 5AR Agrivet, a business that sells chicken feeds and other agrivet supplies. It replaces paper-and-pen sales recording and lets the owner review sales and stock through a web browser, including when he is away from the store.

Built with **PHP** (procedural, MySQLi prepared statements), **MySQL**, **HTML5/CSS3**, **JavaScript**, **Bootstrap 5**, **Bootstrap Icons** and **Chart.js**. Developed locally on **XAMPP** and deployed online on **InfinityFree**.

> **Status:** All features below are implemented. Formal testing (62 test cases, including remote access), and the user evaluation, are still to be completed. See the research paper, Appendices A and C.

## Features

- **Login and roles:** hashed passwords (`password_hash()`), administrator and staff roles, 30-minute idle timeout, 5 failed logins lock login for 1 minute, CSRF tokens on forms, disabled users blocked on their next request.
- **Dashboard:** total products, total units, low-stock and out-of-stock counts, categories, products needing attention, recent stock movements, stock-by-category chart.
- **Products:** add, view, edit, delete; SKU (unique, capitals), category, supplier, cost and selling price, minimum stock, base unit, optional image (JPG, PNG, WEBP); search, filters, pagination.
- **Selling units:** stock is counted in one base unit; a product can also be sold by another unit (for example sack or kilo) with a conversion quantity and price.
- **Categories and suppliers:** add, edit, delete when not in use; duplicate names rejected.
- **Stock in / stock out:** done inside one database transaction; stock cannot go below zero; the stock history is read-only.
- **Point of Sale:** product search, cart, unit and quantity, stock check, **cash or GCash** payment (GCash needs a unique reference number), automatic total and change, receipt number, on-screen receipt, duplicate-submission protection.
- **Sales history:** search and filter by receipt, payment method, status and date.
- **Sale cancellation (administrator only):** reason required; items return to stock through a stock-in record; the sale stays in history as cancelled.
- **Reports:** sales totals (today, this week, this month, date range), best sellers, profit by product, 12-month summary; inventory report and stock transactions report with CSV export.
- **Users and activity log (administrator only):** manage accounts and roles; log of logins, changes, stock movements, sales, cancellations and exports, with IP address.
- **Remote monitoring:** the owner logs in with his administrator account through a browser and opens the dashboard, sales history, sales reports and inventory reports from outside the store. This uses the same pages as the store and needs an internet connection.

## What each role can do

| Action | Staff | Administrator |
|---|:-:|:-:|
| Log in, dashboard | yes | yes |
| Use the POS, sales history, receipts, sales reports | yes | yes |
| View, add, edit products, categories, suppliers | yes | yes |
| Stock in, stock out, stock history | yes | yes |
| Inventory reports and CSV export | yes | yes |
| Delete products, categories, suppliers | no | yes |
| **Cancel a completed sale** | no | yes |
| Manage users, view the activity log | no | yes |

## Database

Database name: `inventory_system`. Nine tables: `users`, `categories`, `suppliers`, `products`, `product_units`, `stock_transactions`, `sales`, `sale_items`, `activity_logs`. Prices use `DECIMAL`; stock quantities use three decimal places so products sold by weight can be handled. Full structure and data dictionary: research paper, Section 4.5.

## Local setup (XAMPP)

1. Install XAMPP (PHP 8.x) and start **Apache** and **MySQL**.
2. Copy the project folder to `C:\xampp\htdocs\inventory_system`. If you use another folder name, change `BASE_URL` in `config/database.php`.
3. Open `http://localhost/phpmyadmin`, click **Import**, and import `inventory_system.sql`.
4. Check `config/database.php` (XAMPP defaults: user `root`, empty password).
5. Open `http://localhost/inventory_system` and log in with the account created by the SQL file.

**Accounts:** the SQL file may create sample administrator and staff users. Change their passwords immediately, or remove them, and do not publish any passwords in documents or in this README. To start empty, remove the sample-data section of the SQL file before importing and create your own administrator with a hash made by `password_hash()`.

## Online deployment (InfinityFree)

1. Create the hosting account and a MySQL database in its control panel.
2. Import `inventory_system.sql` using the host's phpMyAdmin.
3. Upload the project files (including the product images folder) by FTP or the file manager.
4. In `config/database.php`, set the **host's** database host, name, user and password, and set `BASE_URL` to the site address. Keep these credentials out of the repository and the paper.
5. Open the site and confirm the browser shows a secure (HTTPS) connection on the login page.

Deployed address: `https://5ar-agri-sup-inventory-system.infinityfreeapp.com/login.php`

**Limits to know:** the system works only with an internet connection and depends on the host. InfinityFree's free plan has usage limits (reported by its support forum as 50,000 hits per day) and no uptime guarantee. Check the host's current terms. The host does not necessarily keep backups for you.

## Remote monitoring (owner)

1. Connect the phone or computer to the internet.
2. Open the system's web address and log in with the owner's administrator account.
3. Check the **Dashboard**, **Sales History**, **Sales Reports**, and **Reports** (inventory report, CSV export).
4. Log out when finished. Do not use public or shared computers.

The owner sees only what staff have entered, so staff must record each sale and stock movement on time. There are no SMS or email alerts and no offline mode. Remote access has been deployed but is **not yet tested** from a phone on another connection (test cases TC-57 to TC-62).

## Security checklist

Prepared statements for queries with user input; output escaped with `htmlspecialchars()`; CSRF token on every POST form; server-side role checks; server-side validation of quantities and prices; HttpOnly and SameSite session cookies; generic error messages; `.htaccess` files denying direct access to `config/` and `includes/`.

For real use: use HTTPS, a strong unique administrator password, a dedicated database user, and `display_errors` turned off. Review the activity log (it records IP addresses) for logins you do not recognize.

## Limitations

- Requires internet; no offline mode.
- Receipts are on-screen only; printing was not tested.
- No barcode scanning, online payment gateways, or automatic purchase orders.
- Payment methods: cash and GCash only. GCash is recorded manually with its reference number.
- One store and one database; no multiple branches.
- A product can appear only once per sale (selling the same product in two units in one sale is not supported).
- Stock mistakes are corrected with an opposite transaction, not by editing history.

## Project structure

```text
inventory_system/
├── config/database.php        Database and app settings
├── includes/                  Authentication, helper functions, layout files
├── assets/css, assets/js      Styles; script.js, pos.js, sales-reports.js
├── products/                  List, create, edit, view, delete
├── categories/  suppliers/    Management pages
├── stock/                     Stock in, stock out, history
├── sales/                     POS, receipt, sales history, sales reports
├── reports/                   Inventory and stock transactions reports
├── users/                     Users and activity log (administrator only)
├── dashboard.php  login.php  logout.php  index.php
├── inventory_system.sql       Database structure (and optional sample data)
└── README.md
```

(Folder names follow the page addresses seen in the deployed system. Adjust this list if your actual layout differs.)

## Troubleshooting

| Problem | Fix |
|---|---|
| "Unable to connect to the database" | MySQL is not running, or the settings in `config/database.php` are wrong. |
| Page unstyled or icons missing | No internet for the CDN files (Bootstrap, Bootstrap Icons, Chart.js). |
| Links lead to "Not Found" | Folder name or `BASE_URL` does not match. |
| "Invalid or expired form token" | Page open too long or cookies blocked. Refresh and retry. |
| Site will not open online | Check your internet connection; the host may be unavailable or over its usage limit. |
