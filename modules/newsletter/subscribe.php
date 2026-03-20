<?php
// modules/newsletter/subscribe.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Security token expired. Please reload the page.']);
    exit;
}

if (!empty($_POST['hp_name'])) {
    // Spambot
    echo json_encode(['success' => true, 'message' => 'Subscribed successfully.']); // Fake success
    exit;
}

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO subscribers (email) VALUES (?)");
    $stmt->execute([$email]);
    echo json_encode(['success' => true, 'message' => 'Thank you for subscribing!']);
} catch (PDOException $e) {
    // 1062 is Duplicate entry for unique key
    if ($e->getCode() == 23000 || $e->getCode() == 1062) {
        echo json_encode(['success' => true, 'message' => 'You are already subscribed!']);
    } else {
        error_log("Subscribe DB Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
    }
}
exit;
