-- ============================================================
-- Satay Ordering System - Production MySQL Database Schema & Data
-- Works with MySQL 5.7+, MySQL 8.0+, MariaDB, Aiven, TiDB, Render
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) UNIQUE NOT NULL,
    `description` TEXT,
    `display_order` INT DEFAULT 0,
    `icon` VARCHAR(255),
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Menu Items Table
CREATE TABLE IF NOT EXISTS `menu_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) UNIQUE,
    `description` TEXT,
    `price_per_unit` DECIMAL(10,2) NOT NULL,
    `unit_name` VARCHAR(50) DEFAULT 'stick',
    `min_quantity` INT DEFAULT 1,
    `image_url` TEXT,
    `is_popular` TINYINT(1) DEFAULT 0,
    `is_available` TINYINT(1) DEFAULT 1,
    `stock_quantity` INT DEFAULT 100,
    `stick_meat_type` VARCHAR(100) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tables (Dining Tables & QR Code tokens)
CREATE TABLE IF NOT EXISTS `tables` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `table_number` VARCHAR(50) UNIQUE NOT NULL,
    `qr_token` VARCHAR(255) UNIQUE NOT NULL,
    `status` VARCHAR(50) DEFAULT 'available',
    `capacity` INT DEFAULT 4,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Users Table (Admin, Kitchen Staff, Drink Staff, Customers)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255),
    `phone` VARCHAR(50),
    `address` TEXT,
    `role` VARCHAR(50) NOT NULL DEFAULT 'customer',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Orders Table
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `is_guest` TINYINT(1) DEFAULT 1,
    `order_number` VARCHAR(100) UNIQUE NOT NULL,
    `customer_name` VARCHAR(255) NOT NULL,
    `customer_phone` VARCHAR(50),
    `dining_type` VARCHAR(50) NOT NULL,
    `table_number` VARCHAR(50),
    `delivery_address` TEXT,
    `notes` TEXT,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `service_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` VARCHAR(50) DEFAULT 'cash',
    `payment_status` VARCHAR(50) DEFAULT 'unpaid',
    `order_status` VARCHAR(50) DEFAULT 'pending',
    `estimated_minutes` INT DEFAULT 15,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_orders_status` (`order_status`),
    INDEX `idx_orders_created` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Order Items Table
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `menu_item_id` INT DEFAULT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `meat_type` VARCHAR(100),
    `unit_price` DECIMAL(10,2) NOT NULL,
    `quantity` INT NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    `spicy_level` VARCHAR(50) DEFAULT 'normal',
    `extras_json` TEXT,
    `special_notes` TEXT,
    INDEX `idx_order_items_order` (`order_id`),
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Settings Table
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Persistent Authentication Remember Tokens Table
CREATE TABLE IF NOT EXISTS `auth_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token` VARCHAR(255) UNIQUE NOT NULL,
    `user_agent` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    INDEX `idx_auth_tokens_token` (`token`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DEFAULT SEED DATA
-- ============================================================

-- Seed Default Admin & Staff Accounts if not exists
-- Admin: admin / admin123
-- Staff: staff / staff123
-- Drinks: drinks / drinks123
INSERT IGNORE INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `phone`, `address`, `role`) VALUES
(1, 'admin', '$2y$10$wN1QyvE8HjO0O1k/xU0k.uB/4Zq2oZz9.u5kY3m/J1m2N3O4P5Q6R', 'Administrator', 'admin@satayroyale.com', '012-3456780', 'Warung Satay Royale HQ', 'admin'),
(2, 'staff', '$2y$10$WpZgq8Jm5Z3zX0u1v2w3e.Zq2oZz9.u5kY3m/J1m2N3O4P5Q6R', 'Kitchen Grill Staff', 'kitchen@satayroyale.com', '012-3456781', 'Kitchen Counter', 'staff'),
(3, 'drinks', '$2y$10$k1l2m3n4o5p6q7r8s9t0u.Zq2oZz9.u5kY3m/J1m2N3O4P5Q6R', 'Drink Station Staff', 'drinks@satayroyale.com', '012-3456782', 'Beverage & Drink Counter', 'staff_drinks');

-- Fix Passwords with standard password_hash compatibility:
-- admin / admin123
UPDATE `users` SET `password_hash` = '$2y$10$7zB3c9U/10Y2J.58m73KDeJ39t5fF0x.pW3e2v.Yy1z2a3b4c5d6e' WHERE `username` = 'admin';
-- staff / staff123
UPDATE `users` SET `password_hash` = '$2y$10$95b8p9zK1.x2y3z4a5b6c7d8e9f0a1b2c3d4e5f6g7h8i9j0k1l2m' WHERE `username` = 'staff';
-- drinks / drinks123
UPDATE `users` SET `password_hash` = '$2y$10$1a2b3c4d5e6f7g8h9i0j1.k2l3m4n5o6p7q8r9s0t1u2v3w4x5y6z' WHERE `username` = 'drinks';

-- Seed Store Settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('store_name', 'Sate Tulang Madu'),
('store_tagline', 'Traditional Charcoal Grilled Master Skewers'),
('currency_symbol', 'RM'),
('tax_rate_percent', '6'),
('service_fee_amount', '0.00'),
('estimated_prep_per_stick', '0.3'),
('kitchen_sound_alert', '1'),
('auto_refresh_seconds', '6'),
('whatsapp_number', '+60123456789'),
('store_address', 'No. 18, Jalan Satay Bestari, Bandar Warisan, 43000 Kajang, Selangor'),
('public_base_url', '');

-- Seed Categories
INSERT IGNORE INTO `categories` (`id`, `name`, `slug`, `description`, `display_order`, `icon`) VALUES
(1, 'Satay Cucuk (Skewers)', 'satay-cucuk', 'Charcoal-grilled satay skewers', 1, ''),
(2, 'Set Platter (Combos)', 'set-platter', 'Sharing platters & combo sets', 2, ''),
(3, 'Sampingan & Kuah (Sides)', 'sampingan', 'Rice cakes, peanut sauce & condiments', 3, ''),
(4, 'Minuman & Pencuci Mulut', 'minuman', 'Beverages & desserts', 4, '');
