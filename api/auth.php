<?php
/**
 * Satay Ordering System - Authentication API (Login, Register, Guest Mode, Profile, Logout)
 */

require_once __DIR__ . '/config.php';

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($action === 'logout' || ($method === 'POST' && $action === 'logout')) {
    handle_logout();
}

if ($method === 'GET') {
    handle_check_auth();
} elseif ($method === 'POST') {
    if ($action === 'register') {
        handle_register($pdo);
    } elseif ($action === 'guest') {
        handle_guest_mode();
    } elseif ($action === 'profile_update') {
        handle_profile_update($pdo);
    } else {
        handle_login($pdo);
    }
} else {
    json_error('Method not allowed', 405);
}

function handle_login(PDO $pdo) {
    $data = get_json_input();
    $login_input = trim($data['username'] ?? $data['login'] ?? '');
    $password = trim($data['password'] ?? '');
    $portal = trim($data['portal'] ?? ''); // 'admin', 'kitchen', 'customer'
    $redirect = trim($data['redirect'] ?? '');

    if (empty($login_input) || empty($password)) {
        json_error('Please enter both your username/email/phone and password.');
    }

    // Lookup user by username, email, or phone
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE username = ? OR (email IS NOT NULL AND email = ?) OR (phone IS NOT NULL AND phone = ?)
        LIMIT 1
    ");
    $stmt->execute([$login_input, $login_input, $login_input]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_error('Invalid credentials. Please check your username and password.', 401);
    }

    // Clear any guest session
    unset($_SESSION['is_guest'], $_SESSION['guest_name'], $_SESSION['guest_phone']);

    // Set session data
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'] ?? '';
    $_SESSION['phone'] = $user['phone'] ?? '';
    $_SESSION['address'] = $user['address'] ?? '';
    $_SESSION['role'] = $user['role'] ?? 'customer';

    // Issue persistent 10-year remember token
    create_user_remember_token($pdo, (int)$user['id']);

    // Determine redirect target strictly by role (customer order page is exclusively for customers)
    if ($user['role'] === 'admin') {
        $target_redirect = (!empty($redirect) && (strpos($redirect, 'admin.php') !== false || strpos($redirect, 'kitchen.php') !== false)) ? $redirect : 'admin.php';
    } elseif ($user['role'] === 'staff' || $user['role'] === 'staff_drinks') {
        $target_redirect = (!empty($redirect) && strpos($redirect, 'kitchen.php') !== false) ? $redirect : 'kitchen.php';
    } else {
        $target_redirect = (!empty($redirect) && strpos($redirect, 'admin.php') === false && strpos($redirect, 'kitchen.php') === false) ? $redirect : 'index.php';
    }

    json_response([
        'success' => true,
        'message' => 'Welcome back, ' . htmlspecialchars($user['full_name']) . '!',
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'email' => $user['email'] ?? '',
            'phone' => $user['phone'] ?? '',
            'address' => $user['address'] ?? '',
            'role' => $user['role']
        ],
        'redirect_url' => $target_redirect
    ]);
}

function handle_register(PDO $pdo) {
    $data = get_json_input();
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');
    $full_name = trim($data['full_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');

    if (empty($username) || empty($password) || empty($full_name)) {
        json_error('Please fill in your Full Name, Username, and Password.');
    }

    if (strlen($password) < 4) {
        json_error('Password must be at least 4 characters long.');
    }

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        json_error('Username "' . htmlspecialchars($username) . '" is already registered. Please choose another username or sign in.');
    }

    // If email provided, check duplicate
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            json_error('Email "' . htmlspecialchars($email) . '" is already registered.');
        }
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'customer';

    $insert = $pdo->prepare("
        INSERT INTO users (username, password_hash, full_name, email, phone, address, role)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->execute([$username, $password_hash, $full_name, $email, $phone, $address, $role]);
    $user_id = (int)$pdo->lastInsertId();

    // Link/claim any guest orders to this newly registered user account
    $order_numbers = $data['order_numbers'] ?? [];
    if (!empty($order_numbers) && is_array($order_numbers)) {
        $clean_nums = array_map('trim', $order_numbers);
        $placeholders = implode(',', array_fill(0, count($clean_nums), '?'));
        $params = array_merge([$user_id], $clean_nums);
        try {
            $stmt = $pdo->prepare("UPDATE orders SET user_id = ? WHERE order_number IN ($placeholders) AND (user_id IS NULL OR user_id = 0)");
            $stmt->execute($params);
        } catch (Exception $e) {}
    }

    // Auto log in new customer with persistent session
    unset($_SESSION['is_guest'], $_SESSION['guest_name'], $_SESSION['guest_phone']);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['email'] = $email;
    $_SESSION['phone'] = $phone;
    $_SESSION['address'] = $address;
    $_SESSION['role'] = $role;

    // Issue persistent 10-year remember token
    create_user_remember_token($pdo, $user_id);

    json_response([
        'success' => true,
        'message' => 'Account created successfully! Welcome to Sate Tulang Madu, ' . htmlspecialchars($full_name) . '!',
        'user' => [
            'id' => $user_id,
            'username' => $username,
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'role' => $role
        ],
        'redirect_url' => 'index.php'
    ], 201);
}

function handle_guest_mode() {
    $data = get_json_input();
    $guest_name = trim($data['name'] ?? $data['guest_name'] ?? 'Guest Customer');
    $guest_phone = trim($data['phone'] ?? $data['guest_phone'] ?? '');

    // Clear any previous user login
    $_SESSION = [];
    $_SESSION['is_guest'] = true;
    $_SESSION['guest_name'] = !empty($guest_name) ? $guest_name : 'Guest Customer';
    $_SESSION['guest_phone'] = $guest_phone;

    json_response([
        'success' => true,
        'message' => 'Continuing as Guest Mode',
        'guest' => [
            'is_guest' => true,
            'name' => $_SESSION['guest_name'],
            'phone' => $_SESSION['guest_phone']
        ],
        'redirect_url' => 'index.php'
    ]);
}

function handle_profile_update(PDO $pdo) {
    $user = get_current_auth_user();
    if (!$user) {
        json_error('Please log in to update your profile.', 401);
    }

    $data = get_json_input();
    $full_name = trim($data['full_name'] ?? $user['full_name']);
    $phone = trim($data['phone'] ?? $user['phone']);
    $address = trim($data['address'] ?? $user['address']);
    $email = trim($data['email'] ?? $user['email']);

    $stmt = $pdo->prepare("
        UPDATE users 
        SET full_name = ?, phone = ?, address = ?, email = ?
        WHERE id = ?
    ");
    $stmt->execute([$full_name, $phone, $address, $email, $user['id']]);

    $_SESSION['full_name'] = $full_name;
    $_SESSION['phone'] = $phone;
    $_SESSION['address'] = $address;
    $_SESSION['email'] = $email;

    json_response([
        'success' => true,
        'message' => 'Profile updated successfully!',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'role' => $user['role']
        ]
    ]);
}

function handle_check_auth() {
    $user = get_current_auth_user();
    $guest = get_guest_info();

    if ($user) {
        json_response([
            'success' => true,
            'authenticated' => true,
            'is_guest' => false,
            'user' => $user
        ]);
    } elseif ($guest) {
        json_response([
            'success' => true,
            'authenticated' => false,
            'is_guest' => true,
            'guest' => $guest
        ]);
    } else {
        json_response([
            'success' => true,
            'authenticated' => false,
            'is_guest' => false,
            'user' => null
        ]);
    }
}

function handle_logout() {
    clear_user_remember_token();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    $redirect = $_GET['redirect'] ?? '';
    if (!empty($redirect)) {
        header('Location: ' . $redirect);
        exit;
    }

    if (isset($_GET['portal'])) {
        $portal = $_GET['portal'];
        if ($portal === 'admin') {
            header('Location: ../admin_login.php?logged_out=1');
        } elseif ($portal === 'kitchen') {
            header('Location: ../kitchen_login.php?logged_out=1');
        } else {
            header('Location: ../customer_login.php?logged_out=1');
        }
        exit;
    }

    json_response([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}
