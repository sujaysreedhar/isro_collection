<?php

$root = dirname(__DIR__) . '/modules/virtual_museum/assets/';
$entry = file_get_contents($root . 'museum.js');
$modules = ['layouts.js', 'controls.js', 'scene.js', 'artwork.js', 'ui.js'];
foreach ($modules as $module) {
    if (!is_file($root . $module)) {
        fwrite(STDERR, "FAIL: renderer module {$module} is missing.\n");
        exit(1);
    }
    if (strpos($entry, "'./{$module}'") === false) {
        fwrite(STDERR, "FAIL: museum.js does not consume {$module}.\n");
        exit(1);
    }
}
echo "PASS: virtual museum renderer module boundaries\n";
