<?php
require 'config/config.php';
require_once 'includes/Csrf.php';
global $pdo;

ob_start();
try {
    include 'modules/postmark_atlas/admin_map.php';
    $output = ob_get_clean();
    echo "Admin map rendered successfully! Total HTML length: " . strlen($output) . " bytes\n";
    if (strpos($output, 'id="atlas-search-input"') !== false) {
        echo "[PASS] Search input found in admin map HTML.\n";
    } else {
        echo "[FAIL] Search input NOT found in admin map HTML.\n";
    }
} catch (\Throwable $e) {
    ob_end_clean();
    echo "[ERROR] Failed to render admin map: " . $e->getMessage() . "\n";
}
