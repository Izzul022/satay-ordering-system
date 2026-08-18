<?php
/**
 * Satay Ordering System - Schema Initialization
 * Clean production schema without hardcoded demo menu items or mock orders.
 */

function init_database_schema(PDO $pdo) {
    // 1. Create tables
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
            status TEXT DEFAULT 'available', -- available, occupied, reserved
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
            dining_type TEXT NOT NULL, -- dine_in, takeaway, delivery
            table_number TEXT,
            delivery_address TEXT,
            notes TEXT,
            subtotal REAL NOT NULL DEFAULT 0.00,
            tax_amount REAL NOT NULL DEFAULT 0.00,
            service_fee REAL NOT NULL DEFAULT 0.00,
            discount_amount REAL NOT NULL DEFAULT 0.00,
            total_amount REAL NOT NULL DEFAULT 0.00,
            payment_method TEXT DEFAULT 'cash', -- cash, qr_pay, card, online
            payment_status TEXT DEFAULT 'unpaid', -- unpaid, paid, refunded
            order_status TEXT DEFAULT 'pending', -- pending, confirmed, grilling, ready, completed, cancelled
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
            spicy_level TEXT DEFAULT 'normal', -- mild, normal, pedas, extra_pedas
            extras_json TEXT, -- JSON of addons (e.g. extra sauce, onions)
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
            role TEXT NOT NULL DEFAULT 'customer', -- 'admin', 'staff', 'customer'
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

    // 5. Seed Standard Beverage Items (if category 4 is empty)
    $drink_items_count = (int)$pdo->query("SELECT COUNT(*) FROM menu_items WHERE category_id = 4")->fetchColumn();
    if ($drink_items_count === 0) {
        $drink_stmt = $pdo->prepare("
            INSERT INTO menu_items (category_id, name, slug, description, price_per_unit, unit_name, min_quantity, is_popular, is_available, stock_quantity, image_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $default_drinks = [
            [4, 'Teh Tarik Kaw', 'teh-tarik-kaw', 'Rich foamy Malaysian pulled milk tea', 3.50, 'cawan', 1, 1, 1, 150, 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=500&auto=format&fit=crop&q=80'],
            [4, 'Teh O Ais Limau', 'teh-o-ais-limau', 'Refreshing iced black tea with fresh calamansi lime', 3.00, 'gelas', 1, 1, 1, 200, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=500&auto=format&fit=crop&q=80'],
            [4, 'Sirap Bandung Cincau', 'sirap-bandung-cincau', 'Rose syrup milk with grass jelly cubes', 4.00, 'gelas', 1, 1, 1, 100, 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=500&auto=format&fit=crop&q=80'],
            [4, 'Milo Ais Kaw', 'milo-ais-kaw', 'Thick iced chocolate malt beverage', 4.50, 'gelas', 1, 1, 1, 120, 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=500&auto=format&fit=crop&q=80'],
            [4, 'Kopi O Ais', 'kopi-o-ais', 'Traditional aromatic iced black coffee', 3.00, 'gelas', 1, 0, 1, 150, 'https://images.unsplash.com/photo-1517256064527-09c73fc73e38?w=500&auto=format&fit=crop&q=80'],
            [4, 'Air Kelapa Muda', 'air-kelapa-muda', 'Fresh coconut water served chilled', 5.50, 'biji', 1, 1, 1, 50, 'https://images.unsplash.com/photo-1588767763767-622839937107?w=500&auto=format&fit=crop&q=80'],
            [4, 'Air Mineral', 'air-mineral', 'Bottled pure mineral water (500ml)', 2.00, 'botol', 1, 0, 1, 300, 'https://images.unsplash.com/photo-1560023907-5f339617ea30?w=500&auto=format&fit=crop&q=80']
        ];
        foreach ($default_drinks as $d) {
            $drink_stmt->execute($d);
        }
    }
}

function migrate_database_schema(PDO $pdo) {
    // 1. Check users table columns
    $user_cols = [];
    try {
        $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll();
        $user_cols = array_column($cols, 'name');
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
        $cols = $pdo->query("PRAGMA table_info(orders)")->fetchAll();
        $order_cols = array_column($cols, 'name');
    } catch (Exception $e) {}

    if (!empty($order_cols)) {
        if (!in_array('user_id', $order_cols)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN user_id INTEGER DEFAULT NULL;");
        }
        if (!in_array('is_guest', $order_cols)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN is_guest INTEGER DEFAULT 1;");
        }
    }

    // 3. Check menu_items table columns
    $menu_cols = [];
    try {
        $cols = $pdo->query("PRAGMA table_info(menu_items)")->fetchAll();
        $menu_cols = array_column($cols, 'name');
    } catch (Exception $e) {}

    if (!empty($menu_cols)) {
        if (!in_array('stock_quantity', $menu_cols)) {
            $pdo->exec("ALTER TABLE menu_items ADD COLUMN stock_quantity INTEGER DEFAULT 100;");
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

    // 5. Ensure default beverage items exist if category 4 is empty
    try {
        $drink_items_count = (int)$pdo->query("SELECT COUNT(*) FROM menu_items WHERE category_id = 4")->fetchColumn();
        if ($drink_items_count === 0) {
            $drink_stmt = $pdo->prepare("
                INSERT INTO menu_items (category_id, name, slug, description, price_per_unit, unit_name, min_quantity, is_popular, is_available, stock_quantity, image_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $default_drinks = [
                [4, 'Teh Tarik Kaw', 'teh-tarik-kaw', 'Rich foamy Malaysian pulled milk tea', 3.50, 'cawan', 1, 1, 1, 150, 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=500&auto=format&fit=crop&q=80'],
                [4, 'Teh O Ais Limau', 'teh-o-ais-limau', 'Refreshing iced black tea with fresh calamansi lime', 3.00, 'gelas', 1, 1, 1, 200, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=500&auto=format&fit=crop&q=80'],
                [4, 'Sirap Bandung Cincau', 'sirap-bandung-cincau', 'Rose syrup milk with grass jelly cubes', 4.00, 'gelas', 1, 1, 1, 100, 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=500&auto=format&fit=crop&q=80'],
                [4, 'Milo Ais Kaw', 'milo-ais-kaw', 'Thick iced chocolate malt beverage', 4.50, 'gelas', 1, 1, 1, 120, 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=500&auto=format&fit=crop&q=80'],
                [4, 'Kopi O Ais', 'kopi-o-ais', 'Traditional aromatic iced black coffee', 3.00, 'gelas', 1, 0, 1, 150, 'https://images.unsplash.com/photo-1517256064527-09c73fc73e38?w=500&auto=format&fit=crop&q=80'],
                [4, 'Air Kelapa Muda', 'air-kelapa-muda', 'Fresh coconut water served chilled', 5.50, 'biji', 1, 1, 1, 50, 'https://images.unsplash.com/photo-1588767763767-622839937107?w=500&auto=format&fit=crop&q=80'],
                [4, 'Air Mineral', 'air-mineral', 'Bottled pure mineral water (500ml)', 2.00, 'botol', 1, 0, 1, 300, 'https://images.unsplash.com/photo-1560023907-5f339617ea30?w=500&auto=format&fit=crop&q=80']
            ];
            foreach ($default_drinks as $d) {
                $drink_stmt->execute($d);
            }
        }
    } catch (Exception $e) {}

    // 6. Ensure auth_tokens table exists for persistent forever login sessions
    try {
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
    } catch (Exception $e) {}
}
