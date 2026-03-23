<?php
require_once __DIR__ . '/../config/config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `api_keys` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `key_value` VARCHAR(64) NOT NULL UNIQUE,
            `client_name` VARCHAR(100) NOT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "api_keys table created successfully.\n";
} catch (PDOException $e) {
    die("Error creating table: " . $e->getMessage());
}
?>
