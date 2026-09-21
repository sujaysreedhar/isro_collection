<?php
// contact.php — Public contact form (controller)
require_once __DIR__ . '/../../config/config.php';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validShape = true;
    foreach (['name', 'email', 'subject', 'message', 'website'] as $field) {
        if (isset($_POST[$field]) && !is_string($_POST[$field])) $validShape = false;
    }
    $name    = $validShape ? trim($_POST['name'] ?? '') : '';
    $email   = $validShape ? trim($_POST['email'] ?? '') : '';
    $subject = $validShape ? trim($_POST['subject'] ?? '') : '';
    $message = $validShape ? trim($_POST['message'] ?? '') : '';

    // Honeypot check
    if (!$validShape || strlen($name) > 200 || strlen($email) > 254 || strlen($subject) > 200 || strlen($message) > 10000) {
        http_response_code(400);
        $error = 'Please check the length and format of your message.';
    } elseif (!empty($_POST['website'])) {
        // Bot detected — silently ignore
        $success = 'Thank you! Your message has been sent.';
    } elseif (empty($name) || empty($email) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($message) < 10) {
        $error = 'Your message is too short. Please provide more details.';
    } else {
        try {
            $limit = RateLimiter::consume($pdo, 'contact_ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 5, 600);
            if (!$limit['allowed']) {
                http_response_code(429);
                header('Retry-After: ' . $limit['retry_after']);
                $error = 'Too many requests. Please try again later.';
            } else {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message]);
            $success = 'Thank you! Your message has been sent successfully. We will get back to you soon.';
            $name = $email = $subject = $message = '';
            }
        } catch (Throwable $e) {
            http_response_code(503);
            $error = 'Something went wrong. Please try again later.';
            error_log('Contact form error: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Contact Us - ' . SITE_TITLE;
$currentMenu = 'contact';

// Load theme template
require_once ThemeManager::getTemplatePath('contact.php');
