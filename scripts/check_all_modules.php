<?php
// scripts/check_all_modules.php
require_once __DIR__ . '/../config/config.php';

echo "--- Module System Diagnostic ---\n";

$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'active_modules'");
$activeJson = $stmt->fetchColumn();
echo "Raw active_modules setting: " . ($activeJson ?: 'NULL') . "\n";

$activeSlugs = json_decode($activeJson, true) ?: [];
echo "Decoded Active Slugs: " . implode(', ', $activeSlugs) . "\n";

$modulesDir = __DIR__ . '/../modules';
$dirs = array_filter(glob($modulesDir . '/*'), 'is_dir');

echo "\nDiscovered Modules:\n";
foreach ($dirs as $dir) {
    $slug = basename($dir);
    $isActive = in_array($slug, $activeSlugs);
    $moduleFile = $dir . '/module.php';
    $exists = file_exists($moduleFile);
    
    echo "[$slug] " . ($isActive ? "ACTIVE" : "INACTIVE");
    echo " | File: " . ($exists ? "Exists" : "MISSING");
    
    if ($exists) {
        $className = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug))) . 'Module';
        require_once $moduleFile;
        echo " | Class: " . (class_exists($className) ? "OK ($className)" : "MISSING ($className)");
    }
    echo "\n";
}

echo "\n--- Database Check ---\n";
try {
    $stmt = $pdo->query("DESCRIBE module_storage");
    echo "Table 'module_storage' exists.\n";
} catch (Exception $e) {
    echo "Table 'module_storage' does NOT exist: " . $e->getMessage() . "\n";
}
