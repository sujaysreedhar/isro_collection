<?php
require_once 'config/config.php';

$username = 'admin';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        echo "User '$username' already exists." . PHP_EOL;
    } else {
        $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $hash]);
        echo "Created user '$username' with password '$password'." . PHP_EOL;
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
