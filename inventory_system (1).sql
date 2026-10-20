-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 20, 2026 at 04:49 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `details` varchar(500) NOT NULL DEFAULT '',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, NULL, 'login_failed', 'Failed login for username: admin', '::1', '2026-10-12 21:49:02'),
(2, 3, 'login', 'User logged in.', '::1', '2026-10-12 21:52:16'),
(3, 3, 'user_created', 'Created user staff1 (role staff)', '::1', '2026-10-12 21:53:18'),
(4, 3, 'user_updated', 'Updated user #3 admin (role admin, active), password changed', '::1', '2026-10-12 21:53:26'),
(5, 3, 'category_created', 'Created category Rice', '::1', '2026-10-12 21:56:25'),
(6, 3, 'supplier_created', 'Created supplier Joshua Rice Supply', '::1', '2026-10-12 21:56:59'),
(7, 3, 'product_created', 'Created product 54321 - Hasmin RIce', '::1', '2026-10-12 21:59:32'),
(8, 3, 'product_updated', 'Updated product #7 54321 - Hasmin Rice', '::1', '2026-10-12 21:59:52'),
(9, 3, 'sale_completed', 'Receipt RCP-20261012-00008, total 2250.00, cash', '::1', '2026-10-12 22:00:20'),
(10, 3, 'stock_in', 'Product #7, quantity 500, reference IN-20261012-AF923', '::1', '2026-10-12 22:01:46'),
(11, 3, 'logout', 'User logged out.', '::1', '2026-10-12 22:11:38'),
(12, 3, 'login', 'User logged in.', '::1', '2026-10-12 22:11:56'),
(13, 3, 'sale_completed', 'Receipt RCP-20261012-00009, total 45.00, cash', '::1', '2026-10-12 22:13:55'),
(14, 3, 'category_created', 'Created category Poultry Products', '::1', '2026-10-12 22:15:27'),
(15, 3, 'product_created', 'Created product 12356 - Sulodlan tubig ka manok', '::1', '2026-10-12 22:16:15'),
(16, 3, 'sale_completed', 'Receipt RCP-20261012-00010, total 105.00, cash', '::1', '2026-10-12 22:16:32'),
(17, 3, 'stock_in', 'Product #8, quantity 50, reference IN-20261012-C7C47', '::1', '2026-10-12 22:17:24'),
(18, 3, 'logout', 'User logged out.', '::1', '2026-10-12 22:17:48'),
(19, 4, 'login', 'User logged in.', '::1', '2026-10-12 22:17:53'),
(20, 4, 'sale_completed', 'Receipt RCP-20261012-00011, total 175.00, gcash', '::1', '2026-10-12 22:18:06'),
(21, 4, 'logout', 'User logged out.', '::1', '2026-10-12 22:18:29'),
(22, 3, 'login', 'User logged in.', '::1', '2026-10-12 22:18:33'),
(23, 3, 'logout', 'User logged out.', '::1', '2026-10-12 22:20:58'),
(24, 3, 'login', 'User logged in.', '::1', '2026-10-14 09:47:51'),
(25, 3, 'stock_out', 'Product #7, quantity 945, reference OUT-20261014-450C1', '::1', '2026-10-14 09:52:02'),
(26, 3, 'sale_completed', 'Receipt RCP-20261014-00012, total 2000.00, cash', '::1', '2026-10-14 10:02:02'),
(27, 3, 'login', 'User logged in.', '::1', '2026-10-18 22:27:04'),
(28, 3, 'logout', 'User logged out.', '::1', '2026-10-18 22:32:12'),
(29, 3, 'login', 'User logged in.', '::1', '2026-10-18 22:32:26'),
(30, 3, 'logout', 'User logged out.', '::1', '2026-10-18 22:42:05'),
(31, 4, 'login', 'User logged in.', '::1', '2026-10-18 22:42:12'),
(32, 4, 'logout', 'User logged out.', '::1', '2026-10-18 22:48:30'),
(33, 3, 'login', 'User logged in.', '::1', '2026-10-18 22:48:40'),
(34, 3, 'user_updated', 'Updated user #3 admin (role admin, active)', '::1', '2026-10-18 22:53:11'),
(35, 3, 'login', 'User logged in.', '::1', '2026-10-20 10:08:00'),
(36, 3, 'logout', 'User logged out.', '::1', '2026-10-20 10:08:05');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(2, 'Rice', '', '2026-10-12 21:56:25'),
(3, 'Poultry Products', '', '2026-10-12 22:15:27');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `sku` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` decimal(12,3) NOT NULL DEFAULT 0.000,
  `min_stock` int(11) NOT NULL DEFAULT 10,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `base_unit` varchar(30) NOT NULL DEFAULT 'pc',
  `base_allow_decimal` tinyint(1) NOT NULL DEFAULT 0,
  `multi_unit` tinyint(1) NOT NULL DEFAULT 0
) ;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sku`, `name`, `category_id`, `supplier_id`, `description`, `image`, `cost_price`, `selling_price`, `quantity`, `min_stock`, `created_at`, `updated_at`, `base_unit`, `base_allow_decimal`, `multi_unit`) VALUES
(7, '54321', 'Hasmin Rice', 2, 2, 'can sell in kilos', 'decf334d03b03c32cf3140d199df31f0.png', 27.00, 45.00, 0.000, 50, '2026-10-12 21:59:32', '2026-10-14 09:52:02', 'pc', 0, 1),
(8, '12356', 'Sulodlan tubig ka manok', 3, 2, '', 'a681bd486a77e6eff7a8343fb3c3affc.jpg', 10.00, 20.00, 45.000, 10, '2026-10-12 22:16:15', '2026-10-14 10:02:02', 'pc', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `product_units`
--

CREATE TABLE `product_units` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `unit_name` varchar(30) NOT NULL,
  `conversion_qty` decimal(14,4) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `allow_decimal` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ;

--
-- Dumping data for table `product_units`
--

INSERT INTO `product_units` (`id`, `product_id`, `unit_name`, `conversion_qty`, `selling_price`, `allow_decimal`, `sort_order`) VALUES
(3, 7, 'Sack', 50.0000, 2250.00, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(10) UNSIGNED NOT NULL,
  `receipt_number` varchar(30) NOT NULL,
  `checkout_token` char(32) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `amount_paid` decimal(12,2) NOT NULL,
  `payment_method` varchar(10) NOT NULL DEFAULT 'cash',
  `payment_reference` varchar(50) DEFAULT NULL,
  `change_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('completed','cancelled') NOT NULL DEFAULT 'completed',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `cancelled_by` int(10) UNSIGNED DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL
) ;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `receipt_number`, `checkout_token`, `total_amount`, `amount_paid`, `payment_method`, `payment_reference`, `change_amount`, `status`, `created_by`, `created_at`, `cancelled_by`, `cancelled_at`, `cancel_reason`) VALUES
(8, 'RCP-20261012-00008', 'b60a664c9a9234e7bb3ff81688683e5c', 2250.00, 2250.00, 'cash', NULL, 0.00, 'completed', 3, '2026-10-12 22:00:20', NULL, NULL, NULL),
(9, 'RCP-20261012-00009', 'ec31de595b2d6c09a096f0607e395380', 45.00, 50.00, 'cash', NULL, 5.00, 'completed', 3, '2026-10-12 22:13:55', NULL, NULL, NULL),
(10, 'RCP-20261012-00010', 'ccc771da1ad462e3273c21060b1e9a36', 105.00, 105.00, 'cash', NULL, 0.00, 'completed', 3, '2026-10-12 22:16:32', NULL, NULL, NULL),
(11, 'RCP-20261012-00011', '8232c8e96ee4351ff99bfcab11288ce4', 175.00, 175.00, 'gcash', '123553623213', 0.00, 'completed', 4, '2026-10-12 22:18:06', NULL, NULL, NULL),
(12, 'RCP-20261014-00012', 'd66a4205cb36a40d0c677d0774137e49', 2000.00, 2000.00, 'cash', NULL, 0.00, 'completed', 3, '2026-10-14 10:02:02', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `sale_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `unit_name` varchar(30) DEFAULT NULL,
  `unit_qty` decimal(12,3) DEFAULT NULL,
  `unit_conversion` decimal(14,4) DEFAULT NULL
) ;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `line_total`, `unit_name`, `unit_qty`, `unit_conversion`) VALUES
(1, 8, 7, 'Hasmin Rice', 50.000, 2250.00, 2250.00, 'Sack', 1.000, 50.0000),
(2, 9, 7, 'Hasmin Rice', 1.000, 45.00, 45.00, 'pc', 1.000, 1.0000),
(3, 10, 7, 'Hasmin Rice', 1.000, 45.00, 45.00, 'pc', 1.000, 1.0000),
(4, 10, 8, 'Sulodlan tubig ka manok', 3.000, 20.00, 60.00, NULL, 3.000, 1.0000),
(5, 11, 7, 'Hasmin Rice', 3.000, 45.00, 135.00, 'pc', 3.000, 1.0000),
(6, 11, 8, 'Sulodlan tubig ka manok', 2.000, 20.00, 40.00, NULL, 2.000, 1.0000),
(7, 12, 8, 'Sulodlan tubig ka manok', 100.000, 20.00, 2000.00, NULL, 100.000, 1.0000);

-- --------------------------------------------------------

--
-- Table structure for table `stock_transactions`
--

CREATE TABLE `stock_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `transaction_type` enum('in','out') NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `stock_before` decimal(12,3) NOT NULL,
  `stock_after` decimal(12,3) NOT NULL,
  `reference_no` varchar(50) NOT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `reason` varchar(50) NOT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `transaction_date` datetime NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `stock_transactions`
--

INSERT INTO `stock_transactions` (`id`, `product_id`, `transaction_type`, `quantity`, `stock_before`, `stock_after`, `reference_no`, `supplier_id`, `reason`, `remarks`, `transaction_date`, `created_by`, `created_at`) VALUES
(24, 7, 'in', 500.000, 0.000, 500.000, 'OPEN-20261012-F552A', 2, 'Opening stock', 'Recorded when the product was created.', '2026-10-12 21:59:32', 3, '2026-10-12 21:59:32'),
(25, 7, 'out', 50.000, 500.000, 450.000, 'RCP-20261012-00008', NULL, 'Sale', 'POS sale: 1 Sack', '2026-10-12 22:00:20', 3, '2026-10-12 22:00:20'),
(26, 7, 'in', 500.000, 450.000, 950.000, 'IN-20261012-AF923', NULL, 'Delivery', '', '2026-10-12 22:01:00', 3, '2026-10-12 22:01:46'),
(27, 7, 'out', 1.000, 950.000, 949.000, 'RCP-20261012-00009', NULL, 'Sale', 'POS sale: 1 pc', '2026-10-12 22:13:55', 3, '2026-10-12 22:13:55'),
(28, 8, 'in', 100.000, 0.000, 100.000, 'OPEN-20261012-BCD1E', 2, 'Opening stock', 'Recorded when the product was created.', '2026-10-12 22:16:15', 3, '2026-10-12 22:16:15'),
(29, 7, 'out', 1.000, 949.000, 948.000, 'RCP-20261012-00010', NULL, 'Sale', 'POS sale: 1 pc', '2026-10-12 22:16:32', 3, '2026-10-12 22:16:32'),
(30, 8, 'out', 3.000, 100.000, 97.000, 'RCP-20261012-00010', NULL, 'Sale', 'POS sale', '2026-10-12 22:16:32', 3, '2026-10-12 22:16:32'),
(31, 8, 'in', 50.000, 97.000, 147.000, 'IN-20261012-C7C47', 2, 'Delivery', '', '2026-10-12 22:17:00', 3, '2026-10-12 22:17:24'),
(32, 7, 'out', 3.000, 948.000, 945.000, 'RCP-20261012-00011', NULL, 'Sale', 'POS sale: 3 pc', '2026-10-12 22:18:06', 4, '2026-10-12 22:18:06'),
(33, 8, 'out', 2.000, 147.000, 145.000, 'RCP-20261012-00011', NULL, 'Sale', 'POS sale', '2026-10-12 22:18:06', 4, '2026-10-12 22:18:06'),
(34, 7, 'out', 945.000, 945.000, 0.000, 'OUT-20261014-450C1', NULL, 'Damaged or expired', '', '2026-10-14 09:51:00', 3, '2026-10-14 09:52:02'),
(35, 8, 'out', 100.000, 145.000, 45.000, 'RCP-20261014-00012', NULL, 'Sale', 'POS sale', '2026-10-14 10:02:02', 3, '2026-10-14 10:02:02');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `created_at`) VALUES
(2, 'Joshua Rice Supply', 'Joshua Tatano', '0969696969', 'joshuagay@gmail.com', 'Leon, Iloilo', '2026-10-12 21:56:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(30) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password_hash`, `role`, `is_active`, `last_login`, `created_at`) VALUES
(3, 'Paulo', 'admin', '$2y$10$wlHBj5fUfOR7C5bYaZrB5ebtTfZVxx3w3P5ZF52r6K8YzMmKXV0OC', 'admin', 1, '2026-10-20 10:08:00', '2026-10-12 21:52:03'),
(4, 'Joshua Tano', 'staff1', '$2y$10$cYrC.erCQEVOC07UoWUqfueBPbbUCZQKmBbP1.C/jT9qtRyK01opy', 'staff', 1, '2026-10-18 22:42:11', '2026-10-12 21:53:18');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_user` (`user_id`),
  ADD KEY `idx_logs_created` (`created_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categories_name` (`name`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_sku` (`sku`),
  ADD KEY `idx_products_name` (`name`),
  ADD KEY `idx_products_category` (`category_id`),
  ADD KEY `idx_products_supplier` (`supplier_id`);

--
-- Indexes for table `product_units`
--
ALTER TABLE `product_units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pu_product` (`product_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sales_receipt` (`receipt_number`),
  ADD UNIQUE KEY `uq_sales_token` (`checkout_token`),
  ADD UNIQUE KEY `uq_sales_payment_reference` (`payment_reference`),
  ADD KEY `idx_sales_created` (`created_at`),
  ADD KEY `idx_sales_status_created` (`status`,`created_at`),
  ADD KEY `idx_sales_user` (`created_by`),
  ADD KEY `idx_sales_cancelled_by` (`cancelled_by`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sale_items_sale_product` (`sale_id`,`product_id`),
  ADD KEY `idx_sale_items_product` (`product_id`);

--
-- Indexes for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tx_product` (`product_id`),
  ADD KEY `idx_tx_date` (`transaction_date`),
  ADD KEY `idx_tx_type` (`transaction_type`),
  ADD KEY `idx_tx_reference` (`reference_no`),
  ADD KEY `idx_tx_supplier` (`supplier_id`),
  ADD KEY `idx_tx_user` (`created_by`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_suppliers_name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_units`
--
ALTER TABLE `product_units`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `product_units`
--
ALTER TABLE `product_units`
  ADD CONSTRAINT `fk_pu_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_cancelled` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sales_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD CONSTRAINT `fk_tx_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tx_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tx_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
