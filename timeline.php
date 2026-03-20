<?php
// timeline.php (Root level Controller)
require_once __DIR__ . '/config/config.php';

// Fetch all items that have a valid year_start, ordered chronologically
// Also fetch their primary image for the timeline thumbnail
$sql = "
    SELECT i.id, i.title, i.reg_number, i.year_start, i.year_end, i.physical_description,
           (SELECT file_path FROM media WHERE item_id = i.id AND media_type = 'image' ORDER BY id ASC LIMIT 1) as preview_file_path
    FROM items i
    WHERE i.is_visible = 1 AND i.year_start IS NOT NULL
    ORDER BY i.year_start ASC, i.id ASC
";

$stmt = $pdo->query($sql);
$timelineItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group items by year_start
$timelineData = [];
foreach ($timelineItems as $item) {
    if (!isset($timelineData[$item['year_start']])) {
        $timelineData[$item['year_start']] = [];
    }
    $timelineData[$item['year_start']][] = $item;
}

// If no template is provided by the active theme, fallback to default
if (!ThemeManager::getTemplatePath('timeline.php', false)) {
    // We will ensure our standard themes have timeline.php
}

require_once ThemeManager::getTemplatePath('timeline.php');
