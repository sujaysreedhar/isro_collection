<?php
require 'config/config.php';
global $appSettings;
ob_start();
if (($appSettings['active_theme'] ?? 'default') !== 'custom') { echo "Not custom theme"; exit; }
$s = static fn(string $k, string $d) => htmlspecialchars($appSettings[$k] ?? $d, ENT_QUOTES, 'UTF-8');
echo "Primary: " . $s('theme_studio_color_primary', '#111827') . "\n";
echo "Accent: " . $s('theme_studio_color_accent', '#2563eb') . "\n";
$out = ob_get_clean();
echo $out;
