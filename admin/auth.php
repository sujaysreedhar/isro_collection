<?php
// admin/auth.php
session_start();

require_once __DIR__ . '/../config/config.php';

// If user is not logged in, redirect to login page
// Exclude this check for the login page itself
$currentFile = basename($_SERVER['SCRIPT_NAME']);
if ($currentFile !== 'login.php' && !isset($_SESSION['admin_id'])) {
    header("Location: " . SITE_URL . "/admin/login.php");
    exit;
}

// Security: Prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id();
    $_SESSION['initiated'] = true;
}
?>
