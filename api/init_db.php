<?php
/**
 * Satay Ordering System - Schema Initialization
 * Clean production schema without hardcoded demo menu items or mock orders.
 */

function init_database_schema(PDO $pdo) {
    $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

    if ($driver === 'mysql') {
        // MySQL DDL Schema
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                description TEXT,
                display_order INT DEFAULT 0,
                icon VARCHAR(255),
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS menu_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE,
                description TEXT,
                price_per_unit DECIMAL(10,2) NOT NULL,
                unit_name VARCHAR(50) DEFAULT 'stick',
                min_quantity INT DEFAULT 1,
                image_url TEXT,
                is_popular TINYINT(1) DEFAULT 0,
                is_available TINYINT(1) DEFAULT 1,
                stock_quantity INT DEFAULT 100,
                stick_meat_type VARCHAR(100) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS tables (
                id INT AUTO_INCREMENT PRIMARY KEY,
                table_number VARCHAR(50) UNIQUE NOT NULL,
                qr_token VARCHAR(255) UNIQUE NOT NULL,
                status VARCHAR(50) DEFAULT 'available',
                capacity INT DEFAULT 4,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                full_name VARCHAR(255) NOT NULL,
                email VARCHAR(255),
                phone VARCHAR(50),
                address TEXT,
                role VARCHAR(50) NOT NULL DEFAULT 'customer',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                is_guest TINYINT(1) DEFAULT 1,
                order_number VARCHAR(100) UNIQUE NOT NULL,
                customer_name VARCHAR(255) NOT NULL,
                customer_phone VARCHAR(50),
                dining_type VARCHAR(50) NOT NULL,
                table_number VARCHAR(50),
                delivery_address TEXT,
                notes TEXT,
                subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                service_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                payment_method VARCHAR(50) DEFAULT 'cash',
                payment_status VARCHAR(50) DEFAULT 'unpaid',
                order_status VARCHAR(50) DEFAULT 'pending',
                estimated_minutes INT DEFAULT 15,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_orders_status (order_status),
                INDEX idx_orders_created (created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                menu_item_id INT DEFAULT NULL,
                item_name VARCHAR(255) NOT NULL,
                meat_type VARCHAR(100),
                unit_price DECIMAL(10,2) NOT NULL,
                quantity INT NOT NULL,
                total_price DECIMAL(10,2) NOT NULL,
                spicy_level VARCHAR(50) DEFAULT 'normal',
                extras_json TEXT,
                special_notes TEXT,
                INDEX idx_order_items_order (order_id),
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS auth_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(255) UNIQUE NOT NULL,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                INDEX idx_auth_tokens_token (token),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } else {
        // SQLite DDL Schema
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                description TEXT,
                display_order INTEGER DEFAULT 0,
                icon TEXT,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS menu_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                slug TEXT UNIQUE,
                description TEXT,
                price_per_unit REAL NOT NULL,
                unit_name TEXT DEFAULT 'stick',
                min_quantity INTEGER DEFAULT 1,
                image_url TEXT,
                is_popular INTEGER DEFAULT 0,
                is_available INTEGER DEFAULT 1,
                stock_quantity INTEGER DEFAULT 100,
                stick_meat_type TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS tables (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                table_number TEXT UNIQUE NOT NULL,
                qr_token TEXT UNIQUE NOT NULL,
                status TEXT DEFAULT 'available',
                capacity INTEGER DEFAULT 4,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER DEFAULT NULL,
                is_guest INTEGER DEFAULT 1,
                order_number TEXT UNIQUE NOT NULL,
                customer_name TEXT NOT NULL,
                customer_phone TEXT,
                dining_type TEXT NOT NULL,
                table_number TEXT,
                delivery_address TEXT,
                notes TEXT,
                subtotal REAL NOT NULL DEFAULT 0.00,
                tax_amount REAL NOT NULL DEFAULT 0.00,
                service_fee REAL NOT NULL DEFAULT 0.00,
                discount_amount REAL NOT NULL DEFAULT 0.00,
                total_amount REAL NOT NULL DEFAULT 0.00,
                payment_method TEXT DEFAULT 'cash',
                payment_status TEXT DEFAULT 'unpaid',
                order_status TEXT DEFAULT 'pending',
                estimated_minutes INTEGER DEFAULT 15,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                menu_item_id INTEGER,
                item_name TEXT NOT NULL,
                meat_type TEXT,
                unit_price REAL NOT NULL,
                quantity INTEGER NOT NULL,
                total_price REAL NOT NULL,
                spicy_level TEXT DEFAULT 'normal',
                extras_json TEXT,
                special_notes TEXT,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS settings (
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL,
                email TEXT,
                phone TEXT,
                address TEXT,
                role TEXT NOT NULL DEFAULT 'customer',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS auth_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token TEXT UNIQUE NOT NULL,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(order_status);
            CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);
            CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);
            CREATE INDEX IF NOT EXISTS idx_auth_tokens_token ON auth_tokens(token);
        ");
    }

    // Run safe migrations for existing databases
    migrate_database_schema($pdo);

    // 2. Seed Default Administrator & Staff Accounts (if none exist)
    $admin_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($admin_count === 0) {
        $user_stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, email, phone, address, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $user_stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', 'admin@satayroyale.com', '012-3456780', 'Warung Satay Royale HQ', 'admin']);
    }

    $staff_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetchColumn();
    if ($staff_count === 0) {
        $user_stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, email, phone, address, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $user_stmt->execute(['staff', password_hash('staff123', PASSWORD_DEFAULT), 'Kitchen Grill Staff', 'kitchen@satayroyale.com', '012-3456781', 'Kitchen Counter', 'staff']);
    }

    $drinks_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username = 'drinks' OR role = 'staff_drinks'")->fetchColumn();
    if ($drinks_count === 0) {
        $user_stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, email, phone, address, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $user_stmt->execute(['drinks', password_hash('drinks123', PASSWORD_DEFAULT), 'Drink Station Staff', 'drinks@satayroyale.com', '012-3456782', 'Beverage & Drink Counter', 'staff_drinks']);
    }

    // 3. Seed Default Store Settings (if empty)
    $settings_count = (int)$pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($settings_count === 0) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $settings = [
            ['store_name', 'Sate Tulang Madu'],
            ['store_tagline', 'Traditional Charcoal Grilled Master Skewers'],
            ['currency_symbol', 'RM'],
            ['tax_rate_percent', '6'],
            ['service_fee_amount', '0.00'],
            ['estimated_prep_per_stick', '0.3'],
            ['kitchen_sound_alert', '1'],
            ['auto_refresh_seconds', '6'],
            ['whatsapp_number', '+60123456789'],
            ['store_address', 'No. 18, Jalan Satay Bestari, Bandar Warisan, 43000 Kajang, Selangor'],
            ['public_base_url', '']
        ];
        foreach ($settings as $setting) {
            $stmt->execute($setting);
        }
    }

    // 4. Seed Standard Menu Categories (if empty)
    $cat_count = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($cat_count === 0) {
        $pdo->exec("
            INSERT INTO categories (id, name, slug, description, display_order, icon) VALUES
            (1, 'Satay Cucuk (Skewers)', 'satay-cucuk', 'Charcoal-grilled satay skewers', 1, ''),
            (2, 'Set Platter (Combos)', 'set-platter', 'Sharing platters & combo sets', 2, ''),
            (3, 'Sampingan & Kuah (Sides)', 'sampingan', 'Rice cakes, peanut sauce & condiments', 3, ''),
            (4, 'Minuman & Pencuci Mulut', 'minuman', 'Beverages & desserts', 4, '');
        ");
    }


}

function migrate_database_schema(PDO $pdo) {
    $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

    // 1. Check users table columns
    $user_cols = [];
    try {
        if ($driver === 'mysql') {
            $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll();
            $user_cols = array_column($cols, 'Field');
        } else {
            $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll();
            $user_cols = array_column($cols, 'name');
        }
    } catch (Exception $e) {}

    if (!empty($user_cols)) {
        if (!in_array('email', $user_cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT;");
        }
        if (!in_array('phone', $user_cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN phone TEXT;");
        }
        if (!in_array('address', $user_cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN address TEXT;");
        }
    }

    // 2. Check orders table columns
    $order_cols = [];
    try {
        if ($driver === 'mysql') {
            $cols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll();
            $order_cols = array_column($cols, 'Field');
        } else {
            $cols = $pdo->query("PRAGMA table_info(orders)")->fetchAll();
            $order_cols = array_column($cols, 'name');
        }
    } catch (Exception $e) {}

    if (!empty($order_cols)) {
        if (!in_array('user_id', $order_cols)) {
            $type = ($driver === 'mysql') ? "INT DEFAULT NULL" : "INTEGER DEFAULT NULL";
            $pdo->exec("ALTER TABLE orders ADD COLUMN user_id {$type};");
        }
        if (!in_array('is_guest', $order_cols)) {
            $type = ($driver === 'mysql') ? "TINYINT(1) DEFAULT 1" : "INTEGER DEFAULT 1";
            $pdo->exec("ALTER TABLE orders ADD COLUMN is_guest {$type};");
        }
    }

    // 3. Check menu_items table columns
    $menu_cols = [];
    try {
        if ($driver === 'mysql') {
            $cols = $pdo->query("SHOW COLUMNS FROM menu_items")->fetchAll();
            $menu_cols = array_column($cols, 'Field');
        } else {
            $cols = $pdo->query("PRAGMA table_info(menu_items)")->fetchAll();
            $menu_cols = array_column($cols, 'name');
        }
    } catch (Exception $e) {}

    if (!empty($menu_cols)) {
        if (!in_array('stock_quantity', $menu_cols)) {
            $type = ($driver === 'mysql') ? "INT DEFAULT 100" : "INTEGER DEFAULT 100";
            $pdo->exec("ALTER TABLE menu_items ADD COLUMN stock_quantity {$type};");
        }
    }

    // 4. Ensure default staff users exist for existing databases
    try {
        $staff_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetchColumn();
        if ($staff_count === 0) {
            $user_stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, email, phone, address, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $user_stmt->execute(['staff', password_hash('staff123', PASSWORD_DEFAULT), 'Kitchen Grill Staff', 'kitchen@satayroyale.com', '012-3456781', 'Kitchen Counter', 'staff']);
        }

        $drinks_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username = 'drinks' OR role = 'staff_drinks'")->fetchColumn();
        if ($drinks_count === 0) {
            $user_stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, email, phone, address, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $user_stmt->execute(['drinks', password_hash('drinks123', PASSWORD_DEFAULT), 'Drink Station Staff', 'drinks@satayroyale.com', '012-3456782', 'Beverage & Drink Counter', 'staff_drinks']);
        }
    } catch (Exception $e) {}


    // 6. Ensure auth_tokens table exists for persistent forever login sessions
    try {
        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS auth_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(255) UNIQUE NOT NULL,
                    user_agent TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    INDEX idx_auth_tokens_token (token),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS auth_tokens (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    token TEXT UNIQUE NOT NULL,
                    user_agent TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                );
                CREATE INDEX IF NOT EXISTS idx_auth_tokens_token ON auth_tokens(token);
            ");
        }
    } catch (Exception $e) {}
}
