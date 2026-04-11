<?php
// modules/postmark_atlas/route_planner.php
// Route Planner — Road-Based Extension of Postmark Atlas
require_once __DIR__ . '/../../config/config.php';

// Ensure the Postmark Atlas module is active
$activeModulesJson = $appSettings['active_modules'] ?? '[]';
$activeModules = json_decode($activeModulesJson, true);
$isAtlasActive = is_array($activeModules) && in_array('postmark_atlas', $activeModules);

if (!$isAtlasActive) {
    http_response_code(404);
    die("Postmark Atlas is currently unavailable.");
}

// ── Data Loading ─────────────────────────────────────────────────────────────
// We load ALL postmark locations with coordinates. 
// Since this is for client-side filtering along an actual road route.
$stmt = $pdo->prepare("
    SELECT pl.*, i.title AS linked_item_title, i.id AS item_link_id
    FROM postmark_locations pl
    LEFT JOIN items i ON i.id = pl.linked_item_id
    WHERE pl.latitude != 0 AND pl.longitude != 0
    ORDER BY pl.state ASC, pl.district ASC
");
$stmt->execute();
$allLocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pass to the theme template
$jsonAllLocations = json_encode($allLocations);

// Render the UI
require_once ThemeManager::getTemplatePath('route_planner.php');
