<?php
require_once __DIR__ . '/config/config.php';
try {
    $pdo->exec("ALTER TABLE collections ADD COLUMN cover_image VARCHAR(255) DEFAULT NULL AFTER description");
    echo "Successfully added cover_image column to collections table.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
