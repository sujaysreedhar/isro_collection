<?php
// home.php
require_once __DIR__ . '/../../config/config.php';
global $pdo;

// Respect Theme Studio featured item count (defaults to 6)
global $appSettings;
$featuredLimit = (int) ($appSettings['theme_studio_featured_count'] ?? 6);
if ($featuredLimit < 1 || $featuredLimit > 24) $featuredLimit = 6;

// Fetch a few featured/recent items to display on the home page
$stmt = $pdo->prepare("
    SELECT i.*, m.file_path, m.caption 
    FROM items i
    LEFT JOIN media m ON i.id = m.item_id
    GROUP BY i.id
    ORDER BY i.id DESC
    LIMIT " . $featuredLimit . "
");
$stmt->execute();
$featuredItems = $stmt->fetchAll();


// Fetch tags for all featured items in one query
$featuredTags = []; // item_id => [tag, tag, ...]
if ($featuredItems) {
    $fids = array_column($featuredItems, 'id');
    $placeholders = implode(',', array_fill(0, count($fids), '?'));
    $tStmt = $pdo->prepare("
        SELECT it.item_id, t.name, t.slug
        FROM item_tag it
        INNER JOIN tags t ON it.tag_id = t.id
        WHERE it.item_id IN ({$placeholders})
        ORDER BY t.name ASC
    ");
    $tStmt->execute($fids);
    foreach ($tStmt->fetchAll() as $row) {
        $featuredTags[$row['item_id']][] = $row;
    }
}

// Fetch Categories for discovery section (global for all themes)
$stmtCats = $pdo->query("SELECT id, name, image_path FROM categories WHERE image_path IS NOT NULL AND image_path != '' ORDER BY id ASC LIMIT 8");
$homeCategories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once ThemeManager::getTemplatePath('index.php'); ?>
