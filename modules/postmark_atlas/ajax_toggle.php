<?php
// modules/postmark_atlas/ajax_toggle.php
// Standalone AJAX endpoint for toggling acquired status

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

// Must be logged in (admin_id is set by the standard login system)
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Simple CSRF check for AJAX
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$newStatus = (int)($_POST['new_status'] ?? 0);

if (!$id) {
    echo json_encode(['error' => 'Missing id']);
    exit;
}

global $pdo;
$stmt = $pdo->prepare("UPDATE postmark_locations SET is_acquired = ? WHERE id = ?");
$stmt->execute([$newStatus, $id]);

echo json_encode(['success' => true, 'id' => $id, 'is_acquired' => $newStatus]);
