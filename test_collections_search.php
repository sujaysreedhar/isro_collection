<?php
require_once 'config/config.php';
require_once 'includes/BaseModule.php';
require_once 'modules/curated_collections/module.php';

// Mocking some system state
$meta = json_decode(file_get_contents('modules/curated_collections/module.json'), true);
$module = new CuratedCollectionsModule($pdo, 'curated_collections', $meta);

echo "Testing Search Integration for 'Apollo'...\n";

$params = ['q' => 'Apollo'];
$results = []; // items results

ob_start();
$module->injectIntoSearch($results, $params);
$output = ob_get_clean();

if (strpos($output, 'Matching Collections') !== false && strpos($output, 'Apollo 11 Highlights') !== false) {
    echo "SUCCESS: Search integration works! Matching collection found and displayed.\n";
} else {
    echo "FAILURE: Search integration did not display matching collection.\n";
    echo $output;
}
?>
