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
