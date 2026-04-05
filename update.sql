-- =============================================================================
-- CUMULATIVE UPDATE SQL: April 5, 2026
-- =============================================================================

-- POSTMARK ATLAS MODULE UPDATES
-- Add ppc_name column to postmark_locations
ALTER TABLE postmark_locations ADD COLUMN IF NOT EXISTS ppc_name VARCHAR(200) DEFAULT NULL AFTER post_office;
-- Add linked_item_id column to postmark_locations to support item linking
ALTER TABLE postmark_locations ADD COLUMN IF NOT EXISTS linked_item_id INT DEFAULT NULL AFTER is_acquired;
-- Add is_locked column to postmark_locations for manual edit protection
ALTER TABLE postmark_locations ADD COLUMN IF NOT EXISTS is_locked TINYINT(1) DEFAULT 0 AFTER linked_item_id;

-- THEME STUDIO SETTINGS (DEFAULTS)
-- Use INSERT ON DUPLICATE KEY UPDATE to ensure settings exist without overwriting user changes if they exist
INSERT INTO settings (setting_key, setting_value) VALUES 
('theme_studio_color_primary', '#111827'),
('theme_studio_color_accent', '#2563eb'),
('theme_studio_color_accent_dark', '#1d4ed8'),
('theme_studio_color_bg', '#f9fafb'),
('theme_studio_color_hero_bg', '#ffffff'),
('theme_studio_color_surface', '#ffffff'),
('theme_studio_color_border', '#e5e7eb'),
('theme_studio_color_text', '#374151'),
('theme_studio_color_text_muted', '#6b7280'),
('theme_studio_color_footer_bg', '#111827'),
('theme_studio_color_footer_text', '#9ca3af'),
('theme_studio_font_body', 'Inter'),
('theme_studio_font_heading', 'Playfair Display'),
('theme_studio_border_radius', '0.5rem'),
('theme_studio_hero_style', 'split'),
('theme_studio_hero_title', ''),
('theme_studio_hero_text_color', ''),
('theme_studio_hero_tagline_color', ''),
('theme_studio_hero_accent_color', ''),
('theme_studio_hero_overlay_color', '#ffffff'),
('theme_studio_hero_overlay_opacity', '75'),
('theme_studio_grid_cols', '3'),
('theme_studio_show_search', '1'),
('theme_studio_show_stats', '0'),
('theme_studio_featured_count', '6'),
('theme_studio_hero_tagline', ''),
('theme_studio_hero_image', ''),
('theme_studio_footer_text', '')
ON DUPLICATE KEY UPDATE setting_value = IF(setting_value = '' OR setting_value IS NULL, VALUES(setting_value), setting_value);

-- ITEM ANALYTICS & RELATED ITEMS
-- Add view_count to items table for tracking popular items
ALTER TABLE items ADD COLUMN IF NOT EXISTS view_count INT DEFAULT 0;

-- Create item_related table for manual linking between items
CREATE TABLE IF NOT EXISTS item_related (
    item_id INT NOT NULL,
    related_item_id INT NOT NULL,
    PRIMARY KEY (item_id, related_item_id)
);

-- Toggle for "How to Cite" block
INSERT INTO settings (setting_key, setting_value) VALUES ('site_show_citation', '1')
ON DUPLICATE KEY UPDATE setting_value = IF(setting_value = '' OR setting_value IS NULL, '1', setting_value);

-- Add category thumbnails support
ALTER TABLE categories ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL;
