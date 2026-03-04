<?php
// admin/auth.php

$httpsEnabled = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $httpsEnabled ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

if (!function_exists('ensureCsrfToken')) {
    function ensureCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken(?string $token): bool {
        return isset($_SESSION['csrf_token'])
            && is_string($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

$currentFile = basename($_SERVER['SCRIPT_NAME']);
if ($currentFile !== 'login.php' && !isset($_SESSION['admin_id'])) {
    header("Location: " . SITE_URL . "/admin/login.php");
    exit;
}

if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

$sessionTimeoutSeconds = 1800;
if (isset($_SESSION['admin_id'])) {
    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) > $sessionTimeoutSeconds) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header("Location: " . SITE_URL . "/admin/login.php?timeout=1");
        exit;
    }
    $_SESSION['last_activity'] = time();
}

ensureCsrfToken();
?>
