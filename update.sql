-- Create item_category pivot table for many-to-many relationship
CREATE TABLE IF NOT EXISTS item_category (
    item_id INT(11) NOT NULL,
    category_id INT(11) NOT NULL,
    PRIMARY KEY (item_id, category_id),
    CONSTRAINT fk_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    CONSTRAINT fk_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Migrate existing single category links to the pivot table
INSERT IGNORE INTO item_category (item_id, category_id)
SELECT id, category_id FROM items WHERE category_id IS NOT NULL;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add slug to categories for clean URLs
ALTER TABLE categories ADD COLUMN slug VARCHAR(255) AFTER name;

-- Migrate existing categories to have slugs (lowercase, dash-separated)
-- Note: This is a simple migration. Production environments might need more robust slugification.
UPDATE categories SET slug = LOWER(REPLACE(REPLACE(name, ' ', '-'), '&', 'and')) WHERE slug IS NULL OR slug = '';

-- Make slug unique after population
ALTER TABLE categories MODIFY COLUMN slug VARCHAR(255) NOT NULL UNIQUE;

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
