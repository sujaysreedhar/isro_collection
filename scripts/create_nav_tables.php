<?php
require 'config/config.php';
global $pdo;

$sql = "
CREATE TABLE IF NOT EXISTS navigation_menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS navigation_menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    label VARCHAR(150) NOT NULL,
    url VARCHAR(500) DEFAULT '',
    slug VARCHAR(100) DEFAULT '',
    target_blank TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_visible TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO navigation_menus (name, slug) VALUES ('Main Header', 'header');
INSERT IGNORE INTO navigation_menus (name, slug) VALUES ('Main Footer', 'footer');

INSERT INTO navigation_menu_items (menu_id, label, url, slug, sort_order)
SELECT id, 'Explore Collections', 'search.php', 'explore', 1 FROM navigation_menus WHERE slug = 'header'
AND NOT EXISTS (SELECT 1 FROM navigation_menu_items WHERE menu_id = (SELECT id FROM navigation_menus WHERE slug = 'header'));

INSERT INTO navigation_menu_items (menu_id, label, url, slug, sort_order)
SELECT id, 'Visual Gallery', 'gallery.php', 'gallery', 2 FROM navigation_menus WHERE slug = 'header'
AND (SELECT COUNT(*) FROM navigation_menu_items WHERE menu_id = (SELECT id FROM navigation_menus WHERE slug = 'header')) = 1;
";

$statements = array_filter(array_map('trim', explode(';', $sql)));
foreach ($statements as $stmt) {
    if (empty($stmt)) continue;
    try {
        $pdo->exec($stmt);
        echo "OK: " . substr($stmt, 0, 60) . "...\n";
    } catch (Exception $e) {
        echo "ERR: " . $e->getMessage() . "\n";
    }
}
echo "\nDone. Menus now:\n";
$rows = $pdo->query('SELECT id, name, slug FROM navigation_menus')->fetchAll();
print_r($rows);
