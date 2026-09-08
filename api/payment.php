<?php
/**
 * Satay Ordering System - Online Payment Gateway & Simulator API
 * Handles payment sessions, verification, and simulation callbacks.
 */

require_once __DIR__ . '/config.php';

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];

define('PAYMENT_SIGNING_KEY', 'satay_gateway_secret_salt_2026');

function generate_payment_token($order_number, $total_amount) {
    $clean_amount = number_format((float)$total_amount, 2, '.', '');
    return hash_hmac('sha256', "{$order_number}|{$clean_amount}", PAYMENT_SIGNING_KEY);
}

function verify_payment_token($order_number, $total_amount, $token) {
    $expected = generate_payment_token($order_number, $total_amount);
    return hash_equals($expected, (string)$token);
}

switch ($method) {
    case 'GET':
        handle_get_payment_info($pdo);
        break;
    case 'POST':
        handle_process_payment($pdo);
        break;
    default:
        json_error('Method not allowed', 405);
}

/**
 * GET: Retrieve details of an order for display on the payment gateway checkout
 */
function handle_get_payment_info(PDO $pdo) {
    $order_number = trim($_GET['order_number'] ?? '');
    $token = trim($_GET['token'] ?? '');

    if (empty($order_number)) {
        json_error('Order number is required', 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([$order_number]);
    $order = $stmt->fetch();

    if (!$order) {
        json_error('Order not found', 404);
    }

    // Validate token if supplied
    $is_valid_token = !empty($token) && verify_payment_token($order['order_number'], $order['total_amount'], $token);

    // Fetch order items
    $item_stmt = $pdo->prepare("SELECT item_name, quantity, unit_price, total_price, special_notes FROM order_items WHERE order_id = ?");
    $item_stmt->execute([$order['id']]);
    $items = $item_stmt->fetchAll();

    json_response([
        'success' => true,
        'order' => [
            'order_number' => $order['order_number'],
            'customer_name' => $order['customer_name'],
            'customer_phone' => $order['customer_phone'],
            'dining_type' => $order['dining_type'],
            'table_number' => $order['table_number'],
            'subtotal' => (float)$order['subtotal'],
            'tax_amount' => (float)$order['tax_amount'],
            'total_amount' => (float)$order['total_amount'],
            'payment_status' => $order['payment_status'],
            'payment_method' => $order['payment_method'],
            'payment_gateway' => $order['payment_gateway'] ?? null,
            'payment_gateway_ref' => $order['payment_gateway_ref'] ?? null,
            'paid_at' => $order['paid_at'] ?? null,
            'created_at' => $order['created_at'],
            'items' => $items,
            'is_valid_token' => $is_valid_token
        ]
    ]);
}

/**
 * POST: Process payment simulation (Success or Cancelled/Failed)
 */
function handle_process_payment(PDO $pdo) {
    $data = get_json_input();

    $order_number = trim($data['order_number'] ?? '');
    $token = trim($data['token'] ?? '');
    $action = trim($data['action'] ?? 'success'); // success or failed
    $method_type = trim($data['payment_type'] ?? 'fpx'); // fpx, card, ewallet
    $provider = trim($data['provider'] ?? 'Maybank2u'); // Bank or eWallet name
    $card_last4 = trim($data['card_last4'] ?? '');

    if (empty($order_number)) {
        json_error('Order number is required', 400);
    }

    // Lookup order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([$order_number]);
    $order = $stmt->fetch();

    if (!$order) {
        json_error('Order not found', 404);
    }

    // If token provided, verify it (or allow if session matches)
    if (!empty($token) && !verify_payment_token($order['order_number'], $order['total_amount'], $token)) {
        json_error('Invalid payment security token.', 403);
    }

    // Check if already paid
    if ($order['payment_status'] === 'paid') {
        json_response([
            'success' => true,
            'already_paid' => true,
            'message' => 'This order has already been paid.',
            'transaction_ref' => $order['payment_gateway_ref'],
            'redirect_url' => 'index.php?order=' . urlencode($order_number) . '&payment=success'
        ]);
    }

    $now = date('Y-m-d H:i:s');

    if ($action === 'success') {
        // Generate a simulated transaction reference
        $prefix = strtoupper($method_type);
        $rand_str = strtoupper(substr(md5(uniqid()), 0, 6));
        $tx_ref = "SIM-{$prefix}-" . date('YmdHis') . "-{$rand_str}";

        $upd_stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'paid',
                payment_method = 'online',
                payment_gateway = 'mock_simulator',
                payment_gateway_ref = ?,
                paid_at = ?,
                updated_at = ?
            WHERE id = ?
        ");
        $upd_stmt->execute([$tx_ref, $now, $now, $order['id']]);

        json_response([
            'success' => true,
            'message' => 'Payment authorized & verified successfully!',
            'transaction_ref' => $tx_ref,
            'paid_at' => $now,
            'provider' => $provider,
            'amount' => (float)$order['total_amount'],
            'redirect_url' => 'index.php?order=' . urlencode($order_number) . '&payment=success'
        ]);
    } else {
        // Mark payment as failed or cancelled
        $upd_stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'unpaid',
                payment_gateway = 'mock_simulator',
                updated_at = ?
            WHERE id = ?
        ");
        $upd_stmt->execute([$now, $order['id']]);

        json_response([
            'success' => false,
            'message' => 'Payment simulation was cancelled or rejected.',
            'redirect_url' => 'index.php?order=' . urlencode($order_number) . '&payment=failed'
        ]);
    }
}
