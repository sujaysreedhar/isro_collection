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
