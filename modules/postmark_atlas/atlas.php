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
require_once __DIR__ . '/PublicLocations.php';
$locations = PublicLocations::all($pdo, false, true);
foreach ($locations as &$location) {
    $location['preview_url'] = !empty($location['preview_file_path'])
        ? MediaProcessor::url($location['preview_file_path'], 'thumbnails', 'image', $storage ?? null)
        : null;
    unset($location['preview_file_path']);
}
unset($location);

?>
<?php require_once ThemeManager::getTemplatePath('atlas.php'); ?>
