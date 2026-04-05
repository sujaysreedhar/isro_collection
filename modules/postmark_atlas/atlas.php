<?php
// atlas.php
require_once __DIR__ . '/../../config/config.php';

// Ensure the module is active by querying the schema/settings
$activeModulesJson = $appSettings['active_modules'] ?? '[]';
$activeModules = json_decode($activeModulesJson, true);
$isAtlasActive = is_array($activeModules) && in_array('postmark_atlas', $activeModules);

if (!$isAtlasActive) {
    http_response_code(404);
    die("Postmark Atlas is currently unavailable.");
}

// Fetch all locations with linked item details
$stmt = $pdo->query("
    SELECT pl.*, i.title AS linked_item_title, i.id AS linked_item_id
    FROM postmark_locations pl
    LEFT JOIN items i ON i.id = pl.linked_item_id
");
$locations     = $stmt->fetchAll(PDO::FETCH_ASSOC);
$jsonLocations = json_encode($locations);

?>
<?php require_once ThemeManager::getTemplatePath('atlas.php'); ?>
