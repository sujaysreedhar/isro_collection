-- Run this in phpMyAdmin or your MySQL client against the 'eish' database

-- 1. Add metadata fields to media table
ALTER TABLE media
ADD COLUMN file_size INT UNSIGNED NULL COMMENT 'Size in bytes',
ADD COLUMN mime_type VARCHAR(50) NULL COMMENT 'e.g. image/webp',
ADD COLUMN dimensions VARCHAR(50) NULL COMMENT 'e.g. 1920x1080',
ADD COLUMN upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- 2. Add visibility toggle to items table
ALTER TABLE items
ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=visible, 0=hidden';

-- Index to speed up visibility toggle queries
ALTER TABLE items ADD INDEX idx_is_visible (is_visible);