<?php
require_once __DIR__ . '/config/config.php';
global $pdo;

$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'active_modules'");
$active = $stmt->fetchColumn();

echo "Active Modules: " . $active . "\n";
if (strpos($active, 'panoramic_viewer') !== false) {
    echo "Panoramic Viewer is ACTIVE.\n";
} else {
    echo "Panoramic Viewer is NOT active.\n";
}
