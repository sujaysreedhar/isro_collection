<?php
$modJson = __DIR__ . '/../modules/storage_labels/module.json';
$raw = file_get_contents($modJson);
echo "Raw JSON:\n" . $raw . "\n";
$meta = json_decode($raw, true);
echo "\nDecoded:\n";
print_r($meta);
echo "\nwide_admin_page key exists: " . (array_key_exists('wide_admin_page', $meta) ? 'YES' : 'NO') . "\n";
echo "wide_admin_page value: ";
var_dump($meta['wide_admin_page'] ?? 'KEY_MISSING');
echo "wideMode (!empty): ";
var_dump(!empty($meta['wide_admin_page']));
