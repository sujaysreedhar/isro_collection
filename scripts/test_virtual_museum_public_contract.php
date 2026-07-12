<?php

$root = dirname(__DIR__);
$lobby = file_get_contents($root . '/modules/virtual_museum/lobby.php');
$room = file_get_contents($root . '/modules/virtual_museum/room.php');
$css = file_get_contents($root . '/modules/virtual_museum/assets/museum.css');

foreach (['vm-lobby', 'layout_label', 'item_count'] as $needle) {
    if (strpos($lobby, $needle) === false) {
        fwrite(STDERR, "FAIL: lobby is missing {$needle}.\n");
        exit(1);
    }
}

foreach (['museum-2d', 'view=2d', 'museum-welcome', 'museum-reset', 'museum-progress', 'webgl-fallback'] as $needle) {
    if (strpos($room, $needle) === false) {
        fwrite(STDERR, "FAIL: room experience is missing {$needle}.\n");
        exit(1);
    }
}

if (stripos($room, 'user-scalable=no') !== false || stripos($room, 'maximum-scale=1') !== false) {
    fwrite(STDERR, "FAIL: room viewport still disables browser zoom.\n");
    exit(1);
}

foreach ([':focus-visible', 'prefers-reduced-motion', '.museum-2d'] as $needle) {
    if (strpos($css, $needle) === false) {
        fwrite(STDERR, "FAIL: museum CSS is missing {$needle}.\n");
        exit(1);
    }
}

echo "PASS: virtual museum public contract\n";
