<?php
require_once __DIR__ . '/config/config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `collections` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `slug` VARCHAR(255) NOT NULL UNIQUE,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `cover_image` VARCHAR(255),
            `is_public` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `collection_items` (
            `collection_id` INT NOT NULL,
            `item_id` INT NOT NULL,
            `sort_order` INT DEFAULT 0,
            PRIMARY KEY (`collection_id`, `item_id`),
            FOREIGN KEY (`collection_id`) REFERENCES `collections`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Curated Collections tables created successfully.\n";
} catch (PDOException $e) {
    die("Error creating tables: " . $e->getMessage());
}
?>
