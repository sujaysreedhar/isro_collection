<?php
require_once __DIR__ . '/auth.php'; // Initializes session but excludes redirect if script is login.php

$error = '';

// Default admin: admin / admin123
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $suppliedToken = $_POST['csrf_token'] ?? null;
    if (!verifyCsrfToken(is_string($suppliedToken) ? $suppliedToken : null)) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        if (!is_string($username) || !is_string($password) || strlen($username) > 200 || strlen($password) > 4096) {
            http_response_code(400);
            $error = 'Invalid username or password.';
        } else {
            try {
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $perIp = RateLimiter::consume($pdo, 'admin_login_ip', $ip, 100, 900);
                $perUser = RateLimiter::consume($pdo, 'admin_login_user_ip', strtolower(trim($username)) . '|' . $ip, 10, 900);
                if (!$perIp['allowed'] || !$perUser['allowed']) {
                    http_response_code(429);
                    header('Retry-After: ' . max($perIp['retry_after'], $perUser['retry_after']));
                    $error = 'Too many attempts. Please try again later.';
                }
            } catch (Throwable $limiterError) {
                error_log('Login rate limiter unavailable: ' . $limiterError->getMessage());
                http_response_code(503);
                $error = 'Service temporarily unavailable.';
            }
        }

        if ($error === '') {
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM admins WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            // Prevent Session Fixation
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            
            header("Location: " . SITE_URL . "/admin/index.php");
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= SITE_TITLE ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg max-w-sm w-full">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900 border-b pb-4">Museum<span class="text-gray-500 font-light">Admin</span></h1>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded text-sm mb-4 border border-red-200">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
            <div class="mb-4">
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input type="text" id="username" name="username" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900" required autofocus>
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" id="password" name="password" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900" required>
            </div>

            <button type="submit" class="w-full bg-gray-900 text-white font-medium py-2 px-4 rounded hover:bg-gray-800 transition">
                Sign In
            </button>
        </form>
    </div>
</body>
</html>
