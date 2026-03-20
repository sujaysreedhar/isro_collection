<?php
// modules/item_comments/submit.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

$itemId = (int)($_POST['item_id'] ?? 0);

// Basic validation and CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['comment_error'] = "Security check failed. Please try again.";
    header("Location: " . SITE_URL . "/item/{$itemId}#comments");
    exit;
}

// Honeypot check
if (!empty($_POST['website_url'])) {
    // Spambot caught
    header("Location: " . SITE_URL . "/item/{$itemId}");
    exit;
}

$name = trim(strip_tags($_POST['author_name'] ?? ''));
$email = trim(strip_tags($_POST['author_email'] ?? ''));
$comment = trim(strip_tags($_POST['comment'] ?? ''));

if (empty($name) || empty($email) || empty($comment) || $itemId <= 0) {
    $_SESSION['comment_error'] = "Please fill out all required fields.";
    header("Location: " . SITE_URL . "/item/{$itemId}#comments");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['comment_error'] = "Please provide a valid email address.";
    header("Location: " . SITE_URL . "/item/{$itemId}#comments");
    exit;
}

// Ensure the item exists
$stmt = $pdo->prepare("SELECT id FROM items WHERE id = ?");
$stmt->execute([$itemId]);
if (!$stmt->fetchColumn()) {
    die("Item not found.");
}

// Insert comment as pending
try {
    $ins = $pdo->prepare("INSERT INTO item_comments (item_id, author_name, author_email, comment) VALUES (?, ?, ?, ?)");
    $ins->execute([$itemId, $name, $email, $comment]);
    $_SESSION['comment_success'] = true;
} catch (PDOException $e) {
    error_log("Comment DB Error: " . $e->getMessage());
    $_SESSION['comment_error'] = "A database error occurred while saving your note.";
}

header("Location: " . SITE_URL . "/item/{$itemId}#comments");
exit;
