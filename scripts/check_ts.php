<?php
require 'config/config.php';
global $appSettings;

$keys = array_filter(array_keys($appSettings), function($k) {
    return strpos($k, 'theme_studio') === 0;
});

echo "Theme Studio settings found:\n";
foreach ($keys as $k) {
    echo $k . " = " . $appSettings[$k] . "\n";
}
echo "Active Theme: " . ($appSettings['active_theme'] ?? 'not set') . "\n";
