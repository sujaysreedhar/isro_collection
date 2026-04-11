<?php
// home.php
global $pdo;

// Respect Theme Studio featured item count (defaults to 6)
global $appSettings;
$featuredLimit = (int) ($appSettings['theme_studio_featured_count'] ?? 6);
if ($featuredLimit < 1 || $featuredLimit > 24)
    $featuredLimit = 6;

$hasIsPrimary = AppConfig::get('media_has_is_primary', '0') === '1';
$orderClause = $hasIsPrimary ? "m.is_primary DESC, m.upload_date ASC" : "m.upload_date ASC";

// Fetch a few featured/recent items to display on the home page
$stmt = $pdo->prepare("
    SELECT i.*, 
        (SELECT m.file_path FROM media m WHERE m.item_id = i.id AND m.media_type = 'image' ORDER BY {$orderClause} LIMIT 1) as file_path,
        (SELECT m.caption FROM media m WHERE m.item_id = i.id AND m.media_type = 'image' ORDER BY {$orderClause} LIMIT 1) as caption
    FROM items i
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
?>
<?php require_once ThemeManager::getTemplatePath('index.php'); ?>