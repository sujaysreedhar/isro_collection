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
