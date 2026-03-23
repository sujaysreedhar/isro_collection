<?php
require_once 'config/config.php';

$activeModulesJson = $appSettings['active_modules'] ?? '[]';
$activeModules = json_decode($activeModulesJson, true);
if (!is_array($activeModules)) $activeModules = [];

if (!in_array('curated_collections', $activeModules)) {
    $activeModules[] = 'curated_collections';
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('active_modules', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([json_encode($activeModules), json_encode($activeModules)]);
    echo "Module 'curated_collections' activated.\n";
} else {
    echo "Module 'curated_collections' already active.\n";
}

// Create a dummy collection if none exist
$stmt = $pdo->query("SELECT COUNT(*) FROM collections");
if ($stmt->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO collections (title, slug, description, is_public) VALUES ('Apollo 11 Highlights', 'apollo-11', 'A curated selection of artifacts from the historic moon mission.', 1)");
    $id = $pdo->lastInsertId();
    // Link first 3 items to it
    $pdo->exec("INSERT IGNORE INTO collection_items (collection_id, item_id, sort_order) SELECT $id, id, 1 FROM items LIMIT 3");
    echo "Dummy collection created.\n";
}
?>
