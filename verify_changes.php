<?php
require_once __DIR__ . '/config/config.php';

echo "--- Testing Sitemap ---\n";
require_once __DIR__ . '/modules/sitemap/module.php';
require_once __DIR__ . '/modules/seo_meta/module.php';
// Manually trigger the sitemap generation (mimicking the boot check)
$sitemap = new SitemapModule($pdo, 'sitemap', []);
$sitemap->generateSitemap();

echo "\n--- Checking Cache Files ---\n";
$cacheDir = __DIR__ . '/includes/cache/';
if (is_dir($cacheDir)) {
    $files = scandir($cacheDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "Found cache file: $file (" . filesize($cacheDir . $file) . " bytes)\n";
        }
    }
} else {
    echo "Cache directory not found!\n";
}

echo "\n--- Checking Autoloader ---\n";
if (class_exists('SearchEngine')) {
    echo "Autoloader working: SearchEngine class found.\n";
} else {
    echo "Autoloader FAILED: SearchEngine class not found.\n";
}
