<?php
// modules/trade_manager/process_trade.php
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

foreach (['item_id', 'name', 'email', 'message'] as $field) {
    if (isset($_POST[$field]) && !is_string($_POST[$field])) {
        http_response_code(400);
        exit('Invalid request.');
    }
}
$itemId = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');
if (!$itemId || $name === '' || strlen($name) > 200 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($email) > 254 || strlen($message) > 10000) {
    http_response_code(400);
    exit('Invalid trade request.');
}

try {
    $eligible = $pdo->prepare('SELECT id FROM items WHERE id = ? AND is_visible = 1 AND allow_trade = 1');
    $eligible->execute([$itemId]);
    if (!$eligible->fetchColumn()) {
        http_response_code(404);
        exit('Item unavailable.');
    }
    $limit = RateLimiter::consume($pdo, 'trade_ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 5, 600);
    if (!$limit['allowed']) {
        http_response_code(429);
        header('Retry-After: ' . $limit['retry_after']);
        exit('Too many requests. Please try again later.');
    }
    $stmt = $pdo->prepare('INSERT INTO trade_requests (item_id, name, email, message) VALUES (?, ?, ?, ?)');
    $stmt->execute([$itemId, $name, $email, $message]);
    header('Location: ' . SITE_URL . "/item/$itemId/?trade_success=1#trade-request");
} catch (Throwable $error) {
    $publicError = PublicError::payload($error);
    http_response_code(503);
    exit($publicError['error'] . ' Reference: ' . $publicError['error_id']);
}
exit;
