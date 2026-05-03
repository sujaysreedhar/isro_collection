-- Create item_category pivot table for many-to-many relationship
CREATE TABLE IF NOT EXISTS item_category (
    item_id INT(11) NOT NULL,
    category_id INT(11) NOT NULL,
    PRIMARY KEY (item_id, category_id),
    CONSTRAINT fk_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE,
    CONSTRAINT fk_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
);

-- Migrate existing single category links to the pivot table
INSERT IGNORE INTO
    item_category (item_id, category_id)
SELECT id, category_id
FROM items
WHERE
    category_id IS NOT NULL;

-- Store SEO Meta overrides for items and categories
CREATE TABLE IF NOT EXISTS seo_meta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    linked_type VARCHAR(20) NOT NULL,
    linked_id INT NOT NULL,
    seo_title TEXT,
    meta_description TEXT,
    meta_keywords TEXT,
    canonical_url TEXT,
    UNIQUE INDEX idx_linked (linked_type, linked_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Add slug to categories for clean URLs
ALTER TABLE categories ADD COLUMN slug VARCHAR(255) AFTER name;

-- Migrate existing categories to have slugs (lowercase, dash-separated)
-- Note: This is a simple migration. Production environments might need more robust slugification.
UPDATE categories
SET
    slug = LOWER(
        REPLACE (
                REPLACE (name, ' ', '-'),
                    '&',
                    'and'
            )
    )
WHERE
    slug IS NULL
    OR slug = '';

-- Make slug unique after population
ALTER TABLE categories
MODIFY COLUMN slug VARCHAR(255) NOT NULL UNIQUE;

-- ═══════════════════════════════════════════════════════════════════════════════
-- Performance Optimization: Add missing indexes on hot JOIN/WHERE columns
-- These fix full-table-scan issues on search, item detail, and image lookups.
-- ═══════════════════════════════════════════════════════════════════════════════

-- media.item_id — used by every correlated subquery for preview images
CREATE INDEX IF NOT EXISTS idx_media_item_id ON media (item_id);

-- media: compound index for the most common image lookup pattern
CREATE INDEX IF NOT EXISTS idx_media_item_type ON media (item_id, media_type);

-- media.is_primary — used for primary image ordering (if column exists)
-- Safe: CREATE INDEX IF NOT EXISTS won't error if column is missing on old schemas
CREATE INDEX IF NOT EXISTS idx_media_primary ON media (item_id, is_primary);

-- item_tag.tag_id — used by tag facet counting in SearchEngine
CREATE INDEX IF NOT EXISTS idx_item_tag_tag_id ON item_tag (tag_id);

-- item_category indexes (the PK covers item_id lookups, this covers category_id lookups)
CREATE INDEX IF NOT EXISTS idx_item_category_cat_id ON item_category (category_id);

-- items.is_visible — used to filter public items on every frontend query
CREATE INDEX IF NOT EXISTS idx_items_visible ON items (is_visible);

-- items.year_start, year_end — used by date range facet
CREATE INDEX IF NOT EXISTS idx_items_year_start ON items (year_start);

CREATE INDEX IF NOT EXISTS idx_items_year_end ON items (year_end);

-- items.category_id — legacy column still used in some queries
CREATE INDEX IF NOT EXISTS idx_items_category_id ON items (category_id);

-- ═══════════════════════════════════════════════════════════════════════════════
--RUN BELOW THIS Referential Integrity: Tags Management
-- ═══════════════════════════════════════════════════════════════════════════════

-- Ensure item_tag associations are cleaned up when a tag is deleted
-- Note: These commands may require manual execution depending on existing data integrity.
ALTER TABLE item_tag MODIFY item_id INT NOT NULL;

ALTER TABLE item_tag MODIFY tag_id INT NOT NULL;

-- Remove orphaned entries to allow foreign key creation
DELETE FROM item_tag WHERE tag_id NOT IN( SELECT id FROM tags );

DELETE FROM item_tag WHERE item_id NOT IN( SELECT id FROM items );

-- Add foreign keys (Note: If these fail, check for existing conflicting constraints)
ALTER TABLE item_tag
ADD CONSTRAINT fk_tag_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE;

ALTER TABLE item_tag
ADD CONSTRAINT fk_tag_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE;

-- -------------------------------------------------------------------------------
-- Route Planner Google Maps Integration
-- -------------------------------------------------------------------------------
INSERT INTO
    settings (setting_key, setting_value)
VALUES (
        'route_planner_google_maps_key',
        ''
    )

-- -------------------------------------------------------------------------------
-- Set Manager Module
-- -------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS module_sets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    target_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS module_set_items (
    set_id INT NOT NULL,
    item_id INT NOT NULL,
    PRIMARY KEY (set_id, item_id),
    CONSTRAINT fk_set_id FOREIGN KEY (set_id) REFERENCES module_sets (id) ON DELETE CASCADE,
    CONSTRAINT fk_set_item_id FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- -------------------------------------------------------------------------------
-- Storage Labels Module
-- -------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS module_storage (
    item_id INT PRIMARY KEY,
    album VARCHAR(100),
    page_number VARCHAR(50),
    box_id VARCHAR(100),
    location_notes TEXT,
    CONSTRAINT fk_storage_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- -------------------------------------------------------------------------------
-- Collection Checklist (Sets) - V2 Reimplementation
-- -------------------------------------------------------------------------------

-- Add metadata and visibility flags to sets
ALTER TABLE module_sets
ADD COLUMN IF NOT EXISTS slug VARCHAR(255) AFTER name;

ALTER TABLE module_sets
ADD COLUMN IF NOT EXISTS banner_image VARCHAR(255) AFTER description;

ALTER TABLE module_sets
ADD COLUMN IF NOT EXISTS is_public TINYINT(1) DEFAULT 1 AFTER target_count;

ALTER TABLE module_sets
ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) DEFAULT 0 AFTER is_public;

ALTER TABLE module_sets
ADD COLUMN IF NOT EXISTS query_json TEXT AFTER is_featured;

-- Create structure table to define the 'catalog' of a set (the checklist requirements)
CREATE TABLE IF NOT EXISTS module_set_structure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    set_id INT NOT NULL,
    label VARCHAR(255) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    CONSTRAINT fk_structure_set FOREIGN KEY (set_id) REFERENCES module_sets (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Link actual collection items to specific checklist requirements
ALTER TABLE module_set_items
ADD COLUMN IF NOT EXISTS structure_id INT NULL AFTER item_id;

-- Ensure slugs are populated for existing sets
UPDATE module_sets
SET
    slug = LOWER(
        REPLACE (name, ' ', '-')
    )
WHERE
    slug IS NULL
    OR slug = '';

-- -------------------------------------------------------------------------------
-- Navigation Menu Manager
-- -------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS navigation_menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS navigation_menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    label VARCHAR(255) NOT NULL,
    url VARCHAR(255) NOT NULL,
    slug VARCHAR(50) DEFAULT NULL,
    target_blank TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_visible TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_menu_id FOREIGN KEY (menu_id) REFERENCES navigation_menus (id) ON DELETE CASCADE,
    INDEX idx_sort (sort_order)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

INSERT IGNORE INTO navigation_menus (name, slug) VALUES ('Main Header', 'header');
INSERT IGNORE INTO navigation_menus (name, slug) VALUES ('Main Footer', 'footer');

-- Seed default header links
INSERT INTO navigation_menu_items (menu_id, label, url, slug, sort_order) 
SELECT id, 'Explore Collections', 'search.php', 'explore', 1 FROM navigation_menus WHERE slug = 'header' 
AND NOT EXISTS (SELECT 1 FROM navigation_menu_items WHERE menu_id = (SELECT id FROM navigation_menus WHERE slug = 'header'));

INSERT INTO navigation_menu_items (menu_id, label, url, slug, sort_order) 
SELECT id, 'Visual Gallery', 'gallery.php', 'gallery', 2 FROM navigation_menus WHERE slug = 'header' 
AND (SELECT COUNT(*) FROM navigation_menu_items WHERE menu_id = (SELECT id FROM navigation_menus WHERE slug = 'header')) = 1;

-- -------------------------------------------------------------------------------
-- Thematic Taxonomy Module
-- Add hierarchical collection themes and item-to-theme links for curated subjects
-- such as Space, Postal History, Wildlife, and numismatic eras.
-- -------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS module_themes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    sort_order INT DEFAULT 0,
    is_public TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_module_themes_parent (parent_id),
    INDEX idx_module_themes_public_sort (is_public, sort_order, name),
    CONSTRAINT fk_module_themes_parent FOREIGN KEY (parent_id) REFERENCES module_themes (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS module_theme_item (
    theme_id INT NOT NULL,
    item_id INT NOT NULL,
    PRIMARY KEY (theme_id, item_id),
    INDEX idx_module_theme_item_item (item_id),
    CONSTRAINT fk_module_theme_item_theme FOREIGN KEY (theme_id) REFERENCES module_themes (id) ON DELETE CASCADE,
    CONSTRAINT fk_module_theme_item_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Exhibition Planner: prevent duplicate item assignments inside the same exhibition
CREATE UNIQUE INDEX IF NOT EXISTS uniq_module_exhibition_page_item
ON module_exhibition_items (page_id, item_id);

-- Exhibition Planner: speed up ordered exhibition page rendering and admin sorting screens
CREATE INDEX IF NOT EXISTS idx_module_exhibition_page_sort
ON module_exhibition_items (page_id, sort_order, id);

-- -------------------------------------------------------------------------------
-- Backup Manager Module
-- Seed the webhook token setting used by the /runback endpoint. The module will
-- generate a secure value automatically if this setting is empty on activation.
-- -------------------------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value)
VALUES ('backup_manager_webhook_key', '')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- -------------------------------------------------------------------------------
-- Wishlist Module
-- -------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS module_wishlist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    theme_id INT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    status ENUM('wanted', 'buying', 'collected') DEFAULT 'wanted',
    priority INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wishlist_theme (theme_id),
    INDEX idx_wishlist_status (status)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS module_wishlist_stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wishlist_item_id INT NOT NULL,
    store_name VARCHAR(255),
    store_url VARCHAR(255),
    price VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wishlist_item_store (wishlist_item_id),
    CONSTRAINT fk_wishlist_item_store FOREIGN KEY (wishlist_item_id) REFERENCES module_wishlist_items(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
