<?php
require_once __DIR__ . '/config/config.php';

$username = 'sujay';
$password = 'password123';

try {
    $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    echo "Successfully created user: {$username} with password: {$password}\n";
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo "Error: User {$username} already exists.\n";
    } else {
        echo "Database error: " . $e->getMessage() . "\n";
    }
}
