-- Virtual Museum redesign: add selectable room topology while preserving existing theme values
ALTER TABLE museum_exhibitions
    ADD COLUMN IF NOT EXISTS layout VARCHAR(32) NOT NULL DEFAULT 'grand_hall' AFTER theme;

-- Virtual Museum redesign: allow one exhibition to combine multiple live category, collection, and tag sources
CREATE TABLE IF NOT EXISTS museum_exhibition_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exhibition_id INT NOT NULL,
    source_type ENUM('category','collection','tag') NOT NULL,
    source_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY exhibition_source (exhibition_id, source_type, source_id),
    KEY source_lookup (source_type, source_id),
    CONSTRAINT museum_exhibition_sources_exhibition_fk
        FOREIGN KEY (exhibition_id) REFERENCES museum_exhibitions(id) ON DELETE CASCADE
);

-- Virtual Museum redesign: record manual inclusions, live-source exclusions, and optional curator ordering
ALTER TABLE museum_exhibition_items
    ADD COLUMN IF NOT EXISTS selection_state ENUM('include','exclude') NOT NULL DEFAULT 'include' AFTER item_id,
    MODIFY COLUMN sort_order INT NULL DEFAULT NULL;

-- Virtual Museum redesign: migrate every legacy dynamic source into the new multi-source table without deleting legacy columns
INSERT IGNORE INTO museum_exhibition_sources (exhibition_id, source_type, source_id, sort_order)
SELECT id, source_type, source_id, 0
FROM museum_exhibitions
WHERE source_type IN ('category','collection','tag') AND source_id IS NOT NULL;

-- Security audit: atomic fixed-window counters for login and public writes.
CREATE TABLE IF NOT EXISTS request_rate_limits (
    scope VARCHAR(64) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    window_start BIGINT UNSIGNED NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (scope, key_hash, window_start),
    KEY idx_request_rate_limits_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Automatic backup scheduler: initialize the last failed attempt timestamp for admin status display.
INSERT INTO settings (setting_key, setting_value) VALUES ('auto_backup_last_failure_at', '0')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- Virtual Museum centerpiece: preserve the selectable focal-point model on existing exhibitions.
ALTER TABLE museum_exhibitions
    ADD COLUMN IF NOT EXISTS centerpiece VARCHAR(32) NOT NULL DEFAULT 'auto' AFTER layout;

-- Postmark Atlas: store the permanent pictorial cancellation name alongside the post office.
ALTER TABLE postmark_locations
    ADD COLUMN IF NOT EXISTS ppc_name VARCHAR(200) DEFAULT NULL AFTER post_office;

-- Postmark Atlas: link an acquired postmark location to its collection item.
ALTER TABLE postmark_locations
    ADD COLUMN IF NOT EXISTS linked_item_id INT DEFAULT NULL AFTER is_acquired;

-- Postmark Atlas: preserve manual location overrides with a lock flag.
ALTER TABLE postmark_locations
    ADD COLUMN IF NOT EXISTS is_locked TINYINT(1) DEFAULT 0 AFTER linked_item_id;
