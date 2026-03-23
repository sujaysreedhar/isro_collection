<?php
// contact.php — Public contact form (controller)
require_once __DIR__ . '/../../config/config.php';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Honeypot check
    if (!empty($_POST['website'])) {
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
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message]);
            $success = 'Thank you! Your message has been sent successfully. We will get back to you soon.';
            $name = $email = $subject = $message = '';
        } catch (PDOException $e) {
            $error = 'Something went wrong. Please try again later.';
            error_log('Contact form error: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Contact Us - ' . SITE_TITLE;
$currentMenu = 'contact';

// Load theme template
require_once ThemeManager::getTemplatePath('contact.php');
