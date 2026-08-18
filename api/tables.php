<?php
/**
 * Satay Ordering System - Tables & QR Code API
 */

require_once __DIR__ . '/config.php';

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get_tables($pdo);
        break;
    case 'POST':
        handle_add_table($pdo);
        break;
    case 'PATCH':
        handle_update_table($pdo);
        break;
    case 'DELETE':
        handle_delete_table($pdo);
        break;
    default:
        json_error('Method not allowed', 405);
}

function handle_get_tables(PDO $pdo) {
    $stmt = $pdo->query("SELECT * FROM tables ORDER BY table_number ASC");
    $tables = $stmt->fetchAll();

    // Check currently occupied tables with active dine_in orders
    $active_tables_stmt = $pdo->query("
        SELECT table_number, COUNT(*) as active_order_count 
        FROM orders 
        WHERE dining_type = 'dine_in' AND order_status IN ('pending', 'confirmed', 'grilling', 'ready')
        GROUP BY table_number
    ");
    $active_map = [];
    while ($row = $active_tables_stmt->fetch()) {
        $active_map[$row['table_number']] = (int)$row['active_order_count'];
    }

    $base_url = get_system_base_url($pdo);
    foreach ($tables as &$tbl) {
        $tbl['active_orders'] = $active_map[$tbl['table_number']] ?? 0;
        $tbl['is_occupied'] = ($tbl['active_orders'] > 0);
        $tbl['order_url'] = $base_url . 'index.php?table=' . urlencode($tbl['table_number']) . '&token=' . urlencode($tbl['qr_token']);
    }

    json_response(['success' => true, 'tables' => $tables, 'base_url' => $base_url]);
}

function handle_add_table(PDO $pdo) {
    $data = get_json_input();
    $table_number = trim($data['table_number'] ?? '');
    $capacity = isset($data['capacity']) ? (int)$data['capacity'] : 4;

    if (empty($table_number)) {
        json_error('Table number is required (e.g. T13 or Table 13).');
    }

    $token = 'TBL-' . strtoupper(substr(md5(uniqid($table_number, true)), 0, 8));

    try {
        $stmt = $pdo->prepare("INSERT INTO tables (table_number, qr_token, status, capacity) VALUES (?, ?, 'available', ?)");
        $stmt->execute([$table_number, $token, $capacity]);
        json_response(['success' => true, 'message' => 'Table added successfully', 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        json_error('Table number already exists.');
    }
}

function handle_update_table(PDO $pdo) {
    $data = get_json_input();
    $id = isset($data['id']) ? (int)$data['id'] : null;
    $status = trim($data['status'] ?? '');

    if (!$id || empty($status)) {
        json_error('Table ID and status are required.');
    }

    $stmt = $pdo->prepare("UPDATE tables SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    json_response(['success' => true, 'message' => 'Table status updated']);
}

function handle_delete_table(PDO $pdo) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$id) {
        json_error('Table ID is required.');
    }

    $stmt = $pdo->prepare("DELETE FROM tables WHERE id = ?");
    $stmt->execute([$id]);

    json_response(['success' => true, 'message' => 'Table deleted successfully']);
}
