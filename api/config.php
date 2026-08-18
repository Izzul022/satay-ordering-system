<?php
/**
 * Satay Ordering System - Configuration & Database Connector
 */

// Enable error reporting during development
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Persistent Session Lifetime (10 years = 315,360,000 seconds)
$session_lifetime = 315360000;
ini_set('session.cookie_lifetime', $session_lifetime);
ini_set('session.gc_maxlifetime', $session_lifetime);

// Dedicated session directory in data/sessions to protect against server/OS temp purges
$session_dir = __DIR__ . '/../data/sessions';
if (!is_dir($session_dir)) {
    @mkdir($session_dir, 0777, true);
}
if (is_dir($session_dir) && is_writable($session_dir)) {
    session_save_path($session_dir);
}

// Initialize PHP Session with persistent cookie parameters
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// CORS Headers for API endpoints
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database Connection
function get_db_connection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $db_dir = __DIR__ . '/../data';
    if (!is_dir($db_dir)) {
        @mkdir($db_dir, 0777, true);
    }
    
    $db_file = $db_dir . '/satay.sqlite';
    $is_new = !file_exists($db_file) || filesize($db_file) === 0;

    try {
        $pdo = new PDO("sqlite:" . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("PRAGMA journal_mode = WAL;");
        $pdo->exec("PRAGMA foreign_keys = ON;");

        // If new database, trigger schema setup
        require_once __DIR__ . '/init_db.php';
        if ($is_new) {
            init_database_schema($pdo);
        } else {
            migrate_database_schema($pdo);
        }

        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Helper: JSON output
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Helper: Error response
function json_error($message, $status_code = 400, $errors = []) {
    json_response([
        'success' => false,
        'message' => $message,
        'errors' => $errors
    ], $status_code);
}

// Helper: Read JSON payload from request body
function get_json_input() {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return $_POST;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// Helper: Format Currency
function format_currency($amount, $currency = 'RM') {
    return $currency . ' ' . number_format((float)$amount, 2);
}

// Persistent "Remember Token" Helpers for Forever Logins
function create_user_remember_token(PDO $pdo, $user_id) {
    try {
        $token = bin2hex(random_bytes(32));
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
        $expires_at = date('Y-m-d H:i:s', time() + 315360000); // 10 years

        $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token, user_agent, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([(int)$user_id, $token, $user_agent, $expires_at]);

        // Set persistent cookie (10 years)
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('satay_remember_token', $token, [
            'expires' => time() + 315360000,
            'path' => '/',
            'domain' => '',
            'secure' => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        return $token;
    } catch (Exception $e) {
        error_log('Error creating remember token: ' . $e->getMessage());
        return null;
    }
}

function clear_user_remember_token(PDO $pdo = null) {
    $token = $_COOKIE['satay_remember_token'] ?? '';
    if (!empty($token)) {
        try {
            if ($pdo === null) {
                $pdo = get_db_connection();
            }
            $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE token = ?");
            $stmt->execute([$token]);
        } catch (Exception $e) {}
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('satay_remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Auth Helpers
function get_current_auth_user() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 1. If active in session, return it
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => (int)$_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? 'User',
            'email' => $_SESSION['email'] ?? '',
            'phone' => $_SESSION['phone'] ?? '',
            'address' => $_SESSION['address'] ?? '',
            'role' => $_SESSION['role'] ?? 'customer'
        ];
    }

    // 2. If not in session, check persistent remember token in cookie + database
    $remember_token = $_COOKIE['satay_remember_token'] ?? '';
    if (!empty($remember_token)) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("
                SELECT u.* FROM users u
                INNER JOIN auth_tokens t ON u.id = t.user_id
                WHERE t.token = ? AND t.expires_at > datetime('now')
                LIMIT 1
            ");
            $stmt->execute([$remember_token]);
            $user = $stmt->fetch();

            if ($user) {
                // Auto-restore session seamlessly
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['phone'] = $user['phone'] ?? '';
                $_SESSION['address'] = $user['address'] ?? '';
                $_SESSION['role'] = $user['role'] ?? 'customer';

                return [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'] ?? '',
                    'phone' => $user['phone'] ?? '',
                    'address' => $user['address'] ?? '',
                    'role' => $user['role'] ?? 'customer'
                ];
            }
        } catch (Exception $e) {
            error_log('Error restoring session from remember token: ' . $e->getMessage());
        }
    }

    return null;
}

function is_logged_in() {
    return get_current_auth_user() !== null;
}

function is_admin() {
    $user = get_current_auth_user();
    return $user && $user['role'] === 'admin';
}

function is_staff() {
    $user = get_current_auth_user();
    return $user && ($user['role'] === 'staff' || $user['role'] === 'staff_drinks' || $user['role'] === 'admin');
}

function is_drink_staff() {
    $user = get_current_auth_user();
    return $user && ($user['role'] === 'staff_drinks' || ($user['role'] === 'staff' && strpos(strtolower($user['username']), 'drink') !== false));
}

function is_customer() {
    $user = get_current_auth_user();
    return $user && $user['role'] === 'customer';
}

function is_guest() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !isset($_SESSION['user_id']) && isset($_SESSION['is_guest']) && $_SESSION['is_guest'] === true;
}

function get_guest_info() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (is_guest()) {
        return [
            'guest_name' => $_SESSION['guest_name'] ?? 'Guest Customer',
            'guest_phone' => $_SESSION['guest_phone'] ?? '',
            'is_guest' => true
        ];
    }
    return null;
}

function require_auth($role = null) {
    $user = get_current_auth_user();
    if (!$user) {
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            json_error('Authentication required. Please log in.', 401);
        } else {
            header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php') . '&error=auth_required');
            exit;
        }
    }
    
    // Check role permission
    if ($role) {
        $allowed = is_array($role) ? $role : [$role];
        if (!in_array($user['role'], $allowed) && $user['role'] !== 'admin') {
            if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                json_error('Forbidden. Insufficient role permissions.', 403);
            } else {
                header('Location: login.php?error=forbidden');
                exit;
            }
        }
    }
    return $user;
}

// Helper: Determine public base URL
function get_system_base_url($pdo = null) {
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'public_base_url'");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if (!empty($val)) {
                return rtrim($val, '/') . '/';
            }
        } catch (Exception $e) {}
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    $scheme = $is_https ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Determine subdirectory path
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($script);
    $dir = str_replace('\\', '/', $dir);
    if (strpos($dir, '/api') !== false) {
        $dir = substr($dir, 0, strpos($dir, '/api'));
    }
    $dir = rtrim($dir, '/');
    
    return $scheme . $host . ($dir ? $dir : '') . '/';
}
