<?php
// atlas.php
require_once __DIR__ . '/config/config.php';

// Ensure the module is active by querying the schema/settings
$activeModulesJson = $appSettings['active_modules'] ?? '[]';
$activeModules = json_decode($activeModulesJson, true);
$isAtlasActive = is_array($activeModules) && in_array('postmark_atlas', $activeModules);

if (!$isAtlasActive) {
    http_response_code(404);
    die("Postmark Atlas is currently unavailable.");
}

// Fetch only ACQUIRED locations for the public frontend, or optionally all
// Often frontends only show what is collected. We will show all but visually distinguish.
$stmt = $pdo->query("SELECT * FROM postmark_locations");
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output as JSON for Leaflet
$jsonLocations = json_encode($locations);
?>
<?php require_once ThemeManager::getTemplatePath('atlas.php'); ?>
