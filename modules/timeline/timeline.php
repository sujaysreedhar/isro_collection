<?php
// timeline.php (Root level Controller)
require_once __DIR__ . '/../../config/config.php';

// Fetch categories for filtering
$stmtCats = $pdo->query("SELECT DISTINCT c.id, c.name FROM categories c JOIN items i ON c.id = i.category_id WHERE i.year_start IS NOT NULL ORDER BY c.name ASC");
$categories = $stmtCats->fetchAll();

$catId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$searchTerm = trim($_GET['q'] ?? '');

$where = ["i.is_visible = 1", "i.year_start IS NOT NULL"];
$params = [];

if ($catId > 0) {
    $where[] = "i.category_id = ?";
    $params[] = $catId;
}

if (!empty($searchTerm)) {
    $where[] = "(i.title LIKE ? OR i.physical_description LIKE ? OR i.reg_number LIKE ?)";
    $like = "%$searchTerm%";
    array_push($params, $like, $like, $like);
}

$sql = "
    SELECT i.id, i.title, i.reg_number, i.year_start, i.year_end, i.physical_description,
           (SELECT file_path FROM media WHERE item_id = i.id AND media_type = 'image' ORDER BY id ASC LIMIT 1) as preview_file_path
    FROM items i
    WHERE " . implode(" AND ", $where) . "
    ORDER BY i.year_start ASC, i.id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$timelineItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Era logic (Hardcoded for now as semi-curated groups)
$eras = [
    ['name' => 'Early Records', 'start' => -9999, 'end' => 1800, 'color' => 'amber'],
    ['name' => 'Colonial Era', 'start' => 1801, 'end' => 1947, 'color' => 'blue'],
    ['name' => 'Modern Era', 'start' => 1948, 'end' => 9999, 'color' => 'emerald'],
];

// Group items by year_start
$timelineData = [];
foreach ($timelineItems as $item) {
    if (!isset($timelineData[$item['year_start']])) {
        $timelineData[$item['year_start']] = [];
    }
    $timelineData[$item['year_start']][] = $item;
}

// Map items to eras
$eraGroups = [];
foreach ($eras as $era) {
    $eraItems = array_filter($timelineItems, function($i) use ($era) {
        return $i['year_start'] >= $era['start'] && $i['year_start'] <= $era['end'];
    });
    if (!empty($eraItems)) {
        $eraGroups[] = [
            'name' => $era['name'],
            'color' => $era['color'],
            'items' => $eraItems
        ];
    }
}

// If no template is provided by the active theme, fallback to default
if (!ThemeManager::getTemplatePath('timeline.php', false)) {
    // We will ensure our standard themes have timeline.php
}

require_once ThemeManager::getTemplatePath('timeline.php');
