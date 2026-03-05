-- Migration: Add Tags and Item-Tag tables
-- Run this in phpMyAdmin or MySQL CLI before using the tags feature.

CREATE TABLE IF NOT EXISTS `tags` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `slug` varchar(100) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `item_tag` (
    `item_id` int(11) NOT NULL,
    `tag_id` int(11) NOT NULL,
    PRIMARY KEY (`item_id`, `tag_id`),
    KEY `tag_id` (`tag_id`),
    CONSTRAINT `item_tag_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `item_tag_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;