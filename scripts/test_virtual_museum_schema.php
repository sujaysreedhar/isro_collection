<?php

$root = dirname(__DIR__);
$sql = file_get_contents($root . '/update.sql');
$module = file_get_contents($root . '/modules/virtual_museum/module.php');

$requiredSql = [
    'ADD COLUMN IF NOT EXISTS layout',
    'CREATE TABLE IF NOT EXISTS museum_exhibition_sources',
    'ADD COLUMN IF NOT EXISTS selection_state',
    'INSERT IGNORE INTO museum_exhibition_sources',
];

foreach ($requiredSql as $needle) {
    if (stripos($sql, $needle) === false) {
        fwrite(STDERR, "FAIL: update.sql is missing {$needle}\n");
        exit(1);
    }
}

foreach (['museum_exhibition_sources', 'selection_state', 'layout VARCHAR(32)'] as $needle) {
    if (stripos($module, $needle) === false) {
        fwrite(STDERR, "FAIL: activation schema is missing {$needle}\n");
        exit(1);
    }
}

echo "PASS: virtual museum schema definitions\n";
