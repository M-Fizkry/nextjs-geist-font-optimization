<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header('Location: login.php');
    exit();
}

// Include header
include 'includes/header.php';

// Router
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$allowed_pages = ['dashboard', 'bom', 'production', 'users', 'settings'];

if (in_array($page, $allowed_pages) && file_exists("modules/{$page}.php")) {
    include "modules/{$page}.php";
} else {
    include "modules/dashboard.php";
}

// Include footer
include 'includes/footer.php';
?>
