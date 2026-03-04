/*! Database Architecture (MySQL) for Museum Collection Portal */

CREATE DATABASE IF NOT EXISTS museum_collection DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE museum_collection;

-- Categories Table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);

-- Items Table
-- Note: Added category_id to establish relation with categories
CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    reg_number VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    physical_description TEXT,
    historical_significance TEXT,
    production_date VARCHAR(100),
    credit_line VARCHAR(255),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);

-- FULLTEXT Index on items for search optimization
ALTER TABLE items ADD FULLTEXT(title, physical_description);

-- Media Table
CREATE TABLE media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    caption VARCHAR(255),
    license_type VARCHAR(100), -- e.g., CC BY, Public Domain
    is_primary BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- Admin Users Table
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert a default admin for testing (password: admin123)
-- Hash generated using password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO admins (username, password_hash) VALUES ('admin', '$2y$10$8.j6KpwO1iI0E.V9K/T.XOXOhO9m7x/a.rC7x.oP1L2.bA7rN/A1O');

-- Narratives Table (for long-form stories)
CREATE TABLE narratives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content_body TEXT NOT NULL
);

-- Item_Narrative Pivot Table
CREATE TABLE item_narrative (
    item_id INT NOT NULL,
    narrative_id INT NOT NULL,
    PRIMARY KEY (item_id, narrative_id),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (narrative_id) REFERENCES narratives(id) ON DELETE CASCADE
);
