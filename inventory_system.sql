-- =====================================================================
-- Inventory Management System - database script
-- Import this file in phpMyAdmin (Import tab) or with the mysql client.
-- It creates the database, tables, relationships and sample data.
-- Sample logins:  admin / Admin@123   and   staff / Staff@123
-- (Change these passwords after your first login.)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS inventory_system
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventory_system;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS stock_transactions;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(100) NOT NULL,
    username      VARCHAR(30)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    last_login    DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- categories
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- suppliers
-- ---------------------------------------------------------------------
CREATE TABLE suppliers (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(120) NOT NULL,
    contact_person VARCHAR(100) NULL,
    phone          VARCHAR(30)  NULL,
    email          VARCHAR(120) NULL,
    address        VARCHAR(255) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_suppliers_name (name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- products  (quantity is only changed through stock_transactions)
-- ---------------------------------------------------------------------
CREATE TABLE products (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sku           VARCHAR(50)  NOT NULL,
    name          VARCHAR(150) NOT NULL,
    category_id   INT UNSIGNED NOT NULL,
    supplier_id   INT UNSIGNED NULL,
    description   TEXT NULL,
    cost_price    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantity      INT NOT NULL DEFAULT 0,
    min_stock     INT NOT NULL DEFAULT 10,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_name (name),
    KEY idx_products_category (category_id),
    KEY idx_products_supplier (supplier_id),
    CONSTRAINT chk_products_quantity CHECK (quantity >= 0),
    CONSTRAINT chk_products_min_stock CHECK (min_stock >= 0),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- stock_transactions  (audit trail: rows are only ever inserted)
-- ---------------------------------------------------------------------
CREATE TABLE stock_transactions (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id       INT UNSIGNED NOT NULL,
    transaction_type ENUM('in','out') NOT NULL,
    quantity         INT UNSIGNED NOT NULL,
    stock_before     INT NOT NULL,
    stock_after      INT NOT NULL,
    reference_no     VARCHAR(50)  NOT NULL,
    supplier_id      INT UNSIGNED NULL,
    reason           VARCHAR(50)  NOT NULL,
    remarks          VARCHAR(500) NULL,
    transaction_date DATETIME NOT NULL,
    created_by       INT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tx_product (product_id),
    KEY idx_tx_date (transaction_date),
    KEY idx_tx_type (transaction_type),
    KEY idx_tx_reference (reference_no),
    KEY idx_tx_supplier (supplier_id),
    KEY idx_tx_user (created_by),
    CONSTRAINT chk_tx_quantity CHECK (quantity > 0),
    CONSTRAINT fk_tx_product  FOREIGN KEY (product_id)  REFERENCES products (id)  ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tx_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tx_user     FOREIGN KEY (created_by)  REFERENCES users (id)     ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- activity_logs
-- ---------------------------------------------------------------------
CREATE TABLE activity_logs (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(50)  NOT NULL,
    details    VARCHAR(500) NOT NULL DEFAULT '',
    ip_address VARCHAR(45)  NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_logs_user (user_id),
    KEY idx_logs_created (created_at),
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- OPTIONAL SAMPLE DATA (delete everything below to start with an empty system,
-- but keep at least one administrator user so you can log in).
-- =====================================================================

INSERT INTO users (id, full_name, username, password_hash, role) VALUES
(1, 'System Administrator', 'admin', '$2y$10$CfCTeaeVvgEMHAXkKLUhi.6x6ssm//vz9/nUbFIaPi/m5xg1KGKSO', 'admin'),
(2, 'Store Staff',          'staff', '$2y$10$7C.nL.JaF7EpSpEIw/hxxOAycUTpqZ70g3LPkYF2oxAp12jkciHIi', 'staff');

INSERT INTO categories (id, name, description) VALUES
(1, 'Beverages',            'Bottled drinks, juices and instant drinks'),
(2, 'Snacks',               'Packed chips, biscuits and candy'),
(3, 'Office Supplies',      'Paper, pens and notebooks'),
(4, 'Cleaning Supplies',    'Soaps, detergents and disinfectants'),
(5, 'Electronic Accessories','Small gadgets and accessories');

INSERT INTO suppliers (id, name, contact_person, phone, email, address) VALUES
(1, 'Metro Wholesale Trading',  'Ramon Dela Cruz', '(033) 555-0101', 'sales@metrowholesale.example.com', 'Rizal Street, Iloilo City'),
(2, 'Island Paper and Office',  'Maria Santos',    '(033) 555-0102', 'orders@islandpaper.example.com',   'Diversion Road, Iloilo City'),
(3, 'BrightClean Distributors', 'Joel Mercado',    '(033) 555-0103', 'info@brightclean.example.com',     'Mandurriao, Iloilo City'),
(4, 'TechParts Supply Co.',     'Anna Villanueva', '(033) 555-0104', 'support@techparts.example.com',    'Jaro, Iloilo City');

-- Quantities below equal the result of the stock transactions that follow.
INSERT INTO products (id, sku, name, category_id, supplier_id, description, cost_price, selling_price, quantity, min_stock) VALUES
(1,  'BEV-001', 'Bottled Water 500ml',          1, 1, 'Purified drinking water, 500 ml bottle', 8.00,   12.00,  120, 48),
(2,  'BEV-002', 'Orange Juice 1L',              1, 1, 'Ready-to-drink orange juice',            55.00,  75.00,  18,  24),
(3,  'BEV-003', 'Instant Coffee 3-in-1 (30s)',  1, 1, 'Pack of 30 sachets',                     95.00,  130.00, 0,   10),
(4,  'SNK-001', 'Potato Chips 60g',             2, 1, 'Original flavor',                        18.00,  28.00,  64,  30),
(5,  'SNK-002', 'Chocolate Wafer Bar',          2, 1, 'Single bar',                             6.00,   10.00,  15,  40),
(6,  'OFF-001', 'Ballpen Blue (box of 12)',     3, 2, 'Medium point, blue ink',                 48.00,  70.00,  35,  10),
(7,  'OFF-002', 'Bond Paper A4 (ream)',         3, 2, '500 sheets, 70 gsm',                     210.00, 260.00, 8,   10),
(8,  'OFF-003', 'Spiral Notebook 80 leaves',    3, 2, NULL,                                     22.00,  35.00,  52,  20),
(9,  'CLN-001', 'Liquid Dish Soap 500ml',       4, 3, 'Lemon scent',                            38.00,  55.00,  27,  12),
(10, 'CLN-002', 'Bleach 1L',                    4, 3, 'Household bleach',                       32.00,  48.00,  0,   10),
(11, 'ELC-001', 'USB Flash Drive 32GB',         5, 4, 'USB 3.0',                                260.00, 350.00, 14,  5),
(12, 'ELC-002', 'Wired Earphones',              5, 4, '3.5 mm jack with microphone',            85.00,  130.00, 4,   8);

INSERT INTO stock_transactions
(product_id, transaction_type, quantity, stock_before, stock_after, reference_no, supplier_id, reason, remarks, transaction_date, created_by) VALUES
(1,  'in',  200, 0,   200, 'DR-10021',  1,    'Purchase', 'Opening delivery',        '2026-09-01 09:00:00', 1),
(2,  'in',  60,  0,   60,  'DR-10021',  1,    'Purchase', 'Opening delivery',        '2026-09-01 09:00:00', 1),
(3,  'in',  40,  0,   40,  'DR-10021',  1,    'Purchase', 'Opening delivery',        '2026-09-01 09:00:00', 1),
(4,  'in',  120, 0,   120, 'DR-10022',  1,    'Purchase', NULL,                      '2026-09-01 10:30:00', 1),
(5,  'in',  80,  0,   80,  'DR-10022',  1,    'Purchase', NULL,                      '2026-09-01 10:30:00', 1),
(6,  'in',  50,  0,   50,  'DR-20007',  2,    'Purchase', NULL,                      '2026-09-02 13:00:00', 1),
(7,  'in',  20,  0,   20,  'DR-20007',  2,    'Purchase', NULL,                      '2026-09-02 13:00:00', 1),
(8,  'in',  60,  0,   60,  'DR-20007',  2,    'Purchase', NULL,                      '2026-09-02 13:00:00', 1),
(9,  'in',  30,  0,   30,  'DR-30003',  3,    'Purchase', NULL,                      '2026-09-03 09:15:00', 1),
(10, 'in',  24,  0,   24,  'DR-30003',  3,    'Purchase', NULL,                      '2026-09-03 09:15:00', 1),
(11, 'in',  20,  0,   20,  'DR-40001',  4,    'Purchase', NULL,                      '2026-09-04 11:00:00', 1),
(12, 'in',  10,  0,   10,  'DR-40001',  4,    'Purchase', NULL,                      '2026-09-04 11:00:00', 1),
(1,  'out', 80,  200, 120, 'INV-0001',  NULL, 'Sale',     'Weekly sales',            '2026-09-15 14:30:00', 2),
(2,  'out', 42,  60,  18,  'INV-0002',  NULL, 'Sale',     NULL,                      '2026-09-20 16:10:00', 2),
(3,  'out', 40,  40,  0,   'INV-0003',  NULL, 'Sale',     'Sold out',                '2026-10-02 10:45:00', 2),
(4,  'out', 56,  120, 64,  'INV-0004',  NULL, 'Sale',     NULL,                      '2026-09-25 15:20:00', 2),
(5,  'out', 65,  80,  15,  'INV-0005',  NULL, 'Sale',     NULL,                      '2026-09-28 17:05:00', 2),
(6,  'out', 15,  50,  35,  'REL-0001', NULL, 'Release / issue', 'Issued to school office', '2026-09-18 08:40:00', 2),
(7,  'out', 12,  20,  8,   'REL-0002', NULL, 'Release / issue', 'Issued to school office', '2026-09-18 08:45:00', 2),
(8,  'out', 8,   60,  52,  'INV-0006',  NULL, 'Sale',     NULL,                      '2026-09-22 12:30:00', 2),
(9,  'out', 3,   30,  27,  'INV-0007',  NULL, 'Sale',     NULL,                      '2026-09-29 09:50:00', 2),
(10, 'out', 24,  24,  0,   'INV-0008',  NULL, 'Sale',     NULL,                      '2026-10-05 11:25:00', 2),
(11, 'out', 6,   20,  14,  'INV-0009',  NULL, 'Sale',     NULL,                      '2026-09-30 14:00:00', 2),
(12, 'out', 6,   10,  4,   'INV-0010',  NULL, 'Sale',     NULL,                      '2026-10-06 16:40:00', 2);

INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES
(1, 'login',           'User logged in.',                       '127.0.0.1', '2026-09-01 08:50:00'),
(1, 'product_created', 'Sample data imported.',                 '127.0.0.1', '2026-09-01 08:55:00');
