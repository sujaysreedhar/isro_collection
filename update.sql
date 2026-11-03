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

-- BEGIN approved collection tools and seller price tracking (2026-11-02)
-- Wantlist and seller tracking: create the wanted-item catalogue on installations that have not enabled the module yet.
CREATE TABLE IF NOT EXISTS module_wishlist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    theme_id INT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    status ENUM('wanted','buying','collected','searching','shortlisted','ordered','acquired') DEFAULT 'searching',
    priority INT DEFAULT 0,
    item_type VARCHAR(16) NOT NULL DEFAULT 'other',
    catalogue_ref VARCHAR(120) NULL,
    target_price DECIMAL(14,2) NULL,
    target_currency CHAR(3) NULL,
    collection_item_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wishlist_theme (theme_id),
    INDEX idx_wishlist_status (status)
);

-- Wantlist and seller tracking: add stamp/cover classification, catalogue references, target prices, and collection links without replacing existing items.
ALTER TABLE module_wishlist_items
    ADD COLUMN IF NOT EXISTS item_type VARCHAR(16) NOT NULL DEFAULT 'other',
    ADD COLUMN IF NOT EXISTS catalogue_ref VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS target_price DECIMAL(14,2) NULL,
    ADD COLUMN IF NOT EXISTS target_currency CHAR(3) NULL,
    ADD COLUMN IF NOT EXISTS collection_item_id INT NULL,
    MODIFY COLUMN status ENUM('wanted','buying','collected','searching','shortlisted','ordered','acquired') DEFAULT 'searching';

-- Wantlist and seller tracking: retain legacy statuses in the enum while mapping existing records to the equivalent clearer workflow labels.
UPDATE module_wishlist_items SET status = CASE status WHEN 'wanted' THEN 'searching' WHEN 'buying' THEN 'ordered' WHEN 'collected' THEN 'acquired' ELSE status END
WHERE status IN ('wanted','buying','collected');

-- Wantlist and seller tracking: create seller offers with separate currency and shipping values; legacy free-text prices remain intact.
CREATE TABLE IF NOT EXISTS module_wishlist_stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wishlist_item_id INT NOT NULL,
    store_name VARCHAR(255),
    store_url VARCHAR(2000),
    price VARCHAR(100),
    notes TEXT,
    amount DECIMAL(14,2) NULL,
    currency CHAR(3) NULL,
    shipping DECIMAL(14,2) NULL,
    availability ENUM('available','unknown','unavailable','sold') NOT NULL DEFAULT 'unknown',
    last_checked DATE NULL,
    archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wishlist_item_store (wishlist_item_id),
    CONSTRAINT fk_wishlist_item_store FOREIGN KEY (wishlist_item_id) REFERENCES module_wishlist_items(id) ON DELETE CASCADE
);

-- Wantlist and seller tracking: extend existing seller records in place so IDs and dated observations survive edits and removals.
ALTER TABLE module_wishlist_stores
    MODIFY COLUMN store_url VARCHAR(2000),
    ADD COLUMN IF NOT EXISTS amount DECIMAL(14,2) NULL,
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NULL,
    ADD COLUMN IF NOT EXISTS shipping DECIMAL(14,2) NULL,
    ADD COLUMN IF NOT EXISTS availability ENUM('available','unknown','unavailable','sold') NOT NULL DEFAULT 'unknown',
    ADD COLUMN IF NOT EXISTS last_checked DATE NULL,
    ADD COLUMN IF NOT EXISTS archived TINYINT(1) NOT NULL DEFAULT 0;

-- Wantlist and seller tracking: preserve every recorded price, shipping, currency, availability, and checked-date change for each seller offer.
CREATE TABLE IF NOT EXISTS module_wishlist_price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    amount DECIMAL(14,2) NULL,
    currency CHAR(3) NULL,
    shipping DECIMAL(14,2) NULL,
    availability ENUM('available','unknown','unavailable','sold') NOT NULL DEFAULT 'unknown',
    checked_on DATE NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wishlist_history_store (store_id, recorded_at),
    CONSTRAINT fk_wishlist_history_store FOREIGN KEY (store_id) REFERENCES module_wishlist_stores(id) ON DELETE CASCADE
);

-- Collection Workbench: retain and reuse existing private album, page, box and location records.
CREATE TABLE IF NOT EXISTS module_storage (
    item_id INT PRIMARY KEY,
    album VARCHAR(100),
    page_number VARCHAR(50),
    box_id VARCHAR(100),
    location_notes TEXT,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- Collection Workbench: retain and reuse existing private purchase and valuation records.
CREATE TABLE IF NOT EXISTS module_valuations (
    item_id INT PRIMARY KEY,
    purchase_price DECIMAL(15,2),
    current_value DECIMAL(15,2),
    currency VARCHAR(10) DEFAULT 'INR',
    purchase_date DATE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- Collection Workbench: private sleeve, shelf and notes stay separate from publicly selected item records.
CREATE TABLE IF NOT EXISTS collection_private_details (
    item_id INT PRIMARY KEY,
    sleeve VARCHAR(100) NOT NULL DEFAULT '',
    shelf VARCHAR(100) NOT NULL DEFAULT '',
    private_notes TEXT,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- Collection completeness: explicit expected-item checklists grouped by country, year, series or theme.
CREATE TABLE IF NOT EXISTS collection_checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    dimension VARCHAR(20) NOT NULL,
    group_value VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX collection_checklist_group (dimension, group_value)
);

-- Collection completeness: each expected item can link to an owned record; removing that record restores its missing status.
CREATE TABLE IF NOT EXISTS collection_checklist_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT NOT NULL,
    label VARCHAR(255) NOT NULL,
    catalogue_reference VARCHAR(120) NOT NULL DEFAULT '',
    item_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX collection_entry_checklist (checklist_id),
    UNIQUE KEY collection_entry_owned_once (checklist_id, item_id),
    FOREIGN KEY (checklist_id) REFERENCES collection_checklists(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL
);

-- END approved collection tools and seller price tracking
