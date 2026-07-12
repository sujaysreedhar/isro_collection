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
