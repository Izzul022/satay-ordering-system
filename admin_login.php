<?php
require_once __DIR__ . '/api/config.php';

// Seamless redirect to standard login page
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: login.php' . $query);
exit;
