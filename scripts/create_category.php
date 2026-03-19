<?php
require_once 'config/config.php';

try {
    $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->execute(['Postal Cancellations']);
    echo "Category created with ID: " . $pdo->lastInsertId();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
