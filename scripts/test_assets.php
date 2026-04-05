<?php
require 'config/config.php';
try {
    $url = AssetManager::renderStyles(['themes/default/style.css']);
    echo "Generated URL: " . $url . PHP_EOL;
    $cacheDir = __DIR__ . '/includes/cache/assets';
    echo "Cache Dir exists: " . (is_dir($cacheDir) ? 'Yes' : 'No') . PHP_EOL;
    echo "Cache Dir writable: " . (is_writable($cacheDir) ? 'Yes' : 'No') . PHP_EOL;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
