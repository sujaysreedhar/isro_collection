<?php
// index.php
require_once __DIR__ . '/config/config.php';

// Fetch a few featured/recent items to display on the home page
$stmt = $pdo->prepare("
    SELECT i.*, m.file_path, m.caption 
    FROM items i
    LEFT JOIN media m ON i.id = m.item_id
    GROUP BY i.id
    ORDER BY i.id DESC
    LIMIT 6
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
?>
<?php require_once ThemeManager::getTemplatePath('index.php'); ?>
