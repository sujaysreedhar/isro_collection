<?php

$root = dirname(__DIR__);
$meta = json_decode(file_get_contents($root . '/modules/virtual_museum/module.json'), true);
$admin = file_get_contents($root . '/modules/virtual_museum/admin_exhibitions.php');

if (empty($meta['wide_admin_page'])) {
    fwrite(STDERR, "FAIL: virtual museum admin page is not configured for wide mode.\n");
    exit(1);
}

foreach (['data-step="details"', 'data-step="sources"', 'data-step="curation"', 'data-step="room_publish"',
          'vm-source-search', 'vm-item-search', 'data-layout=', "'grand_hall'", 'museum_duplicate'] as $needle) {
    if (strpos($admin, $needle) === false) {
        fwrite(STDERR, "FAIL: admin builder is missing {$needle}.\n");
        exit(1);
    }
}

if (preg_match('/<select[^>]+multiple/i', $admin)) {
    fwrite(STDERR, "FAIL: legacy multiple select remains in the admin builder.\n");
    exit(1);
}

echo "PASS: virtual museum admin contract\n";
