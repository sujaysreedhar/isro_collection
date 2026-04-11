<?php
$pdo = new PDO('mysql:host=localhost;dbname=eish;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$indexes = [
    'CREATE INDEX idx_media_item_id ON media (item_id)',
    'CREATE INDEX idx_media_item_type ON media (item_id, media_type)',
    'CREATE INDEX idx_item_tag_tag_id ON item_tag (tag_id)',
    'CREATE INDEX idx_item_category_cat_id ON item_category (category_id)',
    'CREATE INDEX idx_items_visible ON items (is_visible)',
    'CREATE INDEX idx_items_category_id ON items (category_id)',
];

foreach ($indexes as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: $sql\n";
    } catch (Exception $e) {
        echo "SKIP (exists): " . substr($sql, 13, 50) . "\n";
    }
}

// year columns may not exist on all schemas
foreach (['year_start', 'year_end'] as $col) {
    try {
        $pdo->exec("CREATE INDEX idx_items_{$col} ON items ({$col})");
        echo "OK: idx_items_{$col}\n";
    } catch (Exception $e) {
        echo "SKIP: idx_items_{$col} ({$e->getMessage()})\n";
    }
}

// is_primary may not exist
try {
    $pdo->exec('CREATE INDEX idx_media_primary ON media (item_id, is_primary)');
    echo "OK: idx_media_primary\n";
} catch (Exception $e) {
    echo "SKIP: idx_media_primary ({$e->getMessage()})\n";
}

echo "\nDone.\n";
