<?php
require 'config/config.php';
global $pdo;

// Simulate active modules with postmark_atlas
$appSettings['active_modules'] = json_encode(['postmark_atlas']);

ob_start();
try {
    include 'modules/postmark_atlas/atlas.php';
    $output = ob_get_clean();
    echo "Atlas rendered successfully! Total HTML length: " . strlen($output) . " bytes\n";
    if (strpos($output, 'id="atlas-search-input"') !== false) {
        echo "[PASS] Search input found in rendered HTML.\n";
    } else {
        echo "[FAIL] Search input NOT found in rendered HTML.\n";
    }
    if (strpos($output, 'id="atlas-search-dropdown"') !== false) {
        echo "[PASS] Search dropdown found in rendered HTML.\n";
    } else {
        echo "[FAIL] Search dropdown NOT found in rendered HTML.\n";
    }
} catch (\Throwable $e) {
    ob_end_clean();
    echo "[ERROR] Failed to render atlas: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
