<?php
require_once __DIR__ . '/config/config.php';

echo "--- Testing AssetManager ---\n";

$cssFiles = ['themes/default/style.css'];
$jsFiles  = ['themes/dark/timeline.js'];

$cssTag = AssetManager::renderStyles($cssFiles);
$jsTag  = AssetManager::renderScripts($jsFiles);

echo "CSS Tag: " . htmlspecialchars($cssTag);
echo "JS Tag:  " . htmlspecialchars($jsTag);

$cacheDir = __DIR__ . '/includes/cache/assets/';
if (is_dir($cacheDir)) {
    $files = scandir($cacheDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $content = file_get_contents($cacheDir . $file);
            echo "\nFound cache file: $file (" . strlen($content) . " bytes)\n";
            echo "Snippet: " . substr($content, 0, 50) . "...\n";
            
            if (strpos($content, '/*') !== false && strpos($content, '*/') !== false) {
                echo "WARNING: Block comments found in minified file!\n";
            }
        }
    }
} else {
    echo "Cache directory not found!\n";
}
