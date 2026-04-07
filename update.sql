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
