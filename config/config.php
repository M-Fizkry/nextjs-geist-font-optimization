<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'inventory_system');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Application settings
define('SITE_NAME', 'Inventory Control System');
define('DEFAULT_LANGUAGE', 'en');

// Available languages
$available_languages = [
    'en' => 'English',
    'id' => 'Indonesian',
    'jp' => 'Japanese'
];

// Time zone setting
date_default_timezone_set('Asia/Jakarta');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session lifetime
ini_set('session.gc_maxlifetime', 3600);
session_set_cookie_params(3600);

// Define base URL
$base_url = sprintf(
    "%s://%s%s",
    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
    $_SERVER['SERVER_NAME'],
    dirname($_SERVER['PHP_SELF'])
);
define('BASE_URL', rtrim($base_url, '/'));
?>
