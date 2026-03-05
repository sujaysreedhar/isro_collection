-- Migration: Add settings table for configurable storage backend
-- Run this in phpMyAdmin or MySQL CLI.

CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

INSERT INTO
    `settings` (
        `setting_key`,
        `setting_value`
    )
VALUES ('storage_driver', 'local'),
    ('s3_bucket', ''),
    ('s3_region', 'us-east-1'),
    ('s3_access_key', ''),
    ('s3_secret_key', ''),
    ('s3_endpoint', ''),
    (
        's3_path_prefix',
        'collection/uploads'
    )
ON DUPLICATE KEY UPDATE
    `setting_key` = `setting_key`;