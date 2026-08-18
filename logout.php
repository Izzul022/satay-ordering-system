<?php
/**
 * Satay Ordering System - Logout Handler
 */

require_once __DIR__ . '/api/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

header('Location: login.php?logged_out=1');
exit;

