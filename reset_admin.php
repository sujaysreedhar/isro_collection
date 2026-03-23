<?php
require_once __DIR__ . '/config/config.php';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE username = 'admin'");
$stmt->execute([$hash]);
if ($stmt->rowCount() > 0) {
    echo "Password for user 'admin' reset to 'admin123'.";
} else {
    // Check if any admin exists at all
    $count = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    if ($count == 0) {
        $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES ('admin', ?)");
        $stmt->execute([$hash]);
        echo "No admin found. Created user 'admin' with password 'admin123'.";
    } else {
        echo "User 'admin' not found, but other admins exist.";
    }
}
