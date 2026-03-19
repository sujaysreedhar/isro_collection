<?php
// modules/trade_manager/process_trade.php
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = $_POST['item_id'] ?? 0;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($itemId && $name && $email) {
        try {
            $stmt = $pdo->prepare("INSERT INTO trade_requests (item_id, name, email, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$itemId, $name, $email, $message]);
            
            // Redirect back with success
            header("Location: " . SITE_URL . "/item_detail.php?id=$itemId&trade_success=1#trade-request");
            exit;
        } catch (Exception $e) {
            die("Error processing trade request: " . $e->getMessage());
        }
    }
}

header("Location: " . SITE_URL);
exit;
