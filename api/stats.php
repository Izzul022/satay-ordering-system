<?php
/**
 * Satay Ordering System - Analytics, Stats & Settings API
 */

require_once __DIR__ . '/config.php';

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];

if (isset($_GET['export']) && $_GET['export'] === 'orders_csv') {
    export_orders_csv($pdo);
    exit;
}

switch ($method) {
    case 'GET':
        if (isset($_GET['settings'])) {
            handle_get_settings($pdo);
        } elseif (isset($_GET['users'])) {
            handle_get_users($pdo);
        } else {
            handle_get_dashboard_stats($pdo);
        }
        break;
    case 'POST':
        if (isset($_GET['settings'])) {
            handle_update_settings($pdo);
        } elseif (isset($_GET['users'])) {
            handle_save_user($pdo);
        } else {
            json_error('Invalid action');
        }
        break;
    case 'DELETE':
        if (isset($_GET['users'])) {
            handle_delete_user($pdo);
        } else {
            json_error('Invalid action');
        }
        break;
    default:
        json_error('Method not allowed', 405);
}

function handle_get_dashboard_stats(PDO $pdo) {
    $today = date('Y-m-d');
    
    // 1. Overview KPIs (Today & All-time)
    $today_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(CASE WHEN order_status != 'cancelled' THEN total_amount ELSE 0 END), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END), 0) as completed_orders,
            COALESCE(SUM(CASE WHEN order_status IN ('pending', 'confirmed', 'grilling', 'ready') THEN 1 ELSE 0 END), 0) as active_orders,
            COALESCE(AVG(CASE WHEN order_status != 'cancelled' THEN total_amount ELSE NULL END), 0) as avg_order_value
        FROM orders 
        WHERE DATE(created_at) = ?
    ");
    $today_stmt->execute([$today]);
    $today_stats = $today_stmt->fetch();

    $all_time_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(CASE WHEN order_status != 'cancelled' THEN total_amount ELSE 0 END), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END), 0) as completed_orders
        FROM orders
    ");
    $all_time_stats = $all_time_stmt->fetch();

    // 2. Meat Sticks Breakdown (Total units sold)
    $items_stmt = $pdo->query("
        SELECT oi.meat_type, oi.item_name, SUM(oi.quantity) as total_qty, SUM(oi.total_price) as total_sales
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.order_status != 'cancelled'
        GROUP BY oi.meat_type, oi.item_name
        ORDER BY total_qty DESC
    ");
    $raw_items = $items_stmt->fetchAll();

    $stick_counts = [
        'Sate Tulang Madu' => 0,
        'Ayam (Chicken)' => 0,
        'Daging (Beef)' => 0
    ];
    $total_calculated_sticks = 0;

    foreach ($raw_items as $ri) {
        $meat = strtolower($ri['meat_type'] ?? '');
        $name = strtolower($ri['item_name'] ?? '');
        $qty = (int)$ri['total_qty'];

        if ($meat === 'tulang_madu' || strpos($name, 'tulang') !== false) {
            $stick_counts['Sate Tulang Madu'] += $qty;
            $total_calculated_sticks += $qty;
        } elseif ($meat === 'ayam' || strpos($name, 'ayam') !== false) {
            $stick_counts['Ayam (Chicken)'] += $qty;
            $total_calculated_sticks += $qty;
        } elseif ($meat === 'daging' || strpos($name, 'daging') !== false || strpos($name, 'beef') !== false) {
            $stick_counts['Daging (Beef)'] += $qty;
            $total_calculated_sticks += $qty;
        }
    }

    // 3. Last 7 Days Revenue Trend
    $trend_stmt = $pdo->query("
        SELECT 
            DATE(created_at) as order_date,
            COUNT(*) as orders_count,
            COALESCE(SUM(CASE WHEN order_status != 'cancelled' THEN total_amount ELSE 0 END), 0) as daily_revenue
        FROM orders
        WHERE created_at >= date('now', '-6 days')
        GROUP BY DATE(created_at)
        ORDER BY order_date ASC
    ");
    $daily_trends = $trend_stmt->fetchAll();

    // 4. Dining Type Distribution
    $dining_stmt = $pdo->query("
        SELECT dining_type, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue
        FROM orders
        WHERE order_status != 'cancelled'
        GROUP BY dining_type
    ");
    $dining_distribution = $dining_stmt->fetchAll();

    // 5. Top 5 Best Selling Items
    $top_stmt = $pdo->query("
        SELECT oi.item_name, SUM(oi.quantity) as total_qty, SUM(oi.total_price) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.order_status != 'cancelled'
        GROUP BY oi.item_name
        ORDER BY total_qty DESC
        LIMIT 5
    ");
    $top_items = $top_stmt->fetchAll();

    // 6. Recent 10 Orders
    $recent_stmt = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 10");
    $recent_orders = $recent_stmt->fetchAll();

    json_response([
        'success' => true,
        'today' => [
            'total_orders' => (int)$today_stats['total_orders'],
            'total_revenue' => (float)$today_stats['total_revenue'],
            'completed_orders' => (int)$today_stats['completed_orders'],
            'active_orders' => (int)$today_stats['active_orders'],
            'avg_order_value' => (float)$today_stats['avg_order_value'],
            'total_sticks_sold' => $total_calculated_sticks
        ],
        'all_time' => [
            'total_orders' => (int)$all_time_stats['total_orders'],
            'total_revenue' => (float)$all_time_stats['total_revenue'],
            'completed_orders' => (int)$all_time_stats['completed_orders']
        ],
        'stick_breakdown' => $stick_counts,
        'daily_trends' => $daily_trends,
        'dining_distribution' => $dining_distribution,
        'top_items' => $top_items,
        'recent_orders' => $recent_orders
    ]);
}

function handle_get_settings(PDO $pdo) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $rows = $stmt->fetchAll();
    $settings = [];
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    json_response(['success' => true, 'settings' => $settings]);
}

function handle_update_settings(PDO $pdo) {
    $data = get_json_input();
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");

    foreach ($data as $key => $val) {
        $stmt->execute([$key, (string)$val]);
    }

    json_response(['success' => true, 'message' => 'Settings saved successfully']);
}

function export_orders_csv(PDO $pdo) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=satay_orders_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order ID', 'Order Number', 'Date', 'Customer Name', 'Phone', 'Dining Type', 'Table #', 'Payment Method', 'Payment Status', 'Order Status', 'Total (RM)', 'Notes']);

    $stmt = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC");
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['order_number'],
            $row['created_at'],
            $row['customer_name'],
            $row['customer_phone'],
            strtoupper(str_replace('_', ' ', $row['dining_type'])),
            $row['table_number'] ?: '-',
            strtoupper($row['payment_method']),
            strtoupper($row['payment_status']),
            strtoupper($row['order_status']),
            number_format((float)$row['total_amount'], 2),
            $row['notes']
        ]);
    }
    fclose($output);
    exit;
}

function handle_get_users(PDO $pdo) {
    require_auth('admin');
    $stmt = $pdo->query("
        SELECT 
            u.id, u.username, u.full_name, u.email, u.phone, u.address, u.role, u.created_at,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_spent
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id
        GROUP BY u.id
        ORDER BY 
            CASE u.role 
                WHEN 'admin' THEN 1 
                WHEN 'staff' THEN 2 
                ELSE 3 
            END,
            u.created_at DESC
    ");
    $users = $stmt->fetchAll();
    json_response(['success' => true, 'users' => $users]);
}

function handle_save_user(PDO $pdo) {
    require_auth('admin');
    $data = get_json_input();
    $id = isset($data['id']) ? (int)$data['id'] : null;
    $username = trim($data['username'] ?? '');
    $full_name = trim($data['full_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');
    $role = trim($data['role'] ?? 'customer');
    $password = trim($data['password'] ?? '');

    if (empty($username) || empty($full_name)) {
        json_error('Username and Full Name are required.');
    }

    if ($id) {
        // Update user
        if (!empty($password)) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, full_name = ?, email = ?, phone = ?, address = ?, role = ?, password_hash = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $full_name, $email, $phone, $address, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, full_name = ?, email = ?, phone = ?, address = ?, role = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $full_name, $email, $phone, $address, $role, $id]);
        }
        json_response(['success' => true, 'message' => 'User updated successfully']);
    } else {
        // Create user
        if (empty($password)) {
            json_error('Password is required when creating a new user account.');
        }
        // Check duplicate
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            json_error('Username already exists.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO users (username, password_hash, full_name, email, phone, address, role)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $full_name, $email, $phone, $address, $role]);
        json_response(['success' => true, 'message' => 'User account created successfully']);
    }
}

function handle_delete_user(PDO $pdo) {
    require_auth('admin');
    $data = get_json_input();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($data['id'] ?? 0);

    if (!$id) {
        json_error('User ID is required');
    }

    $curr = get_current_auth_user();
    if ($curr && $curr['id'] === $id) {
        json_error('Cannot delete your own admin account');
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    json_response(['success' => true, 'message' => 'User deleted successfully']);
}

