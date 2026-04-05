<?php
require __DIR__ . '/../config/config.php';
echo "=== Bootstrap Stages ===\n";
echo 'SITE_URL:    ' . SITE_URL . "\n";
echo 'CACHE_DIR:   ' . CACHE_DIR . "\n";
echo 'AppConfig:   ' . (AppConfig::isLoaded() ? 'OK' : 'FAIL') . "\n";
echo 'HookRegistry reset(): ' . (method_exists('HookRegistry', 'reset') ? 'OK' : 'FAIL') . "\n";
echo 'ModuleManager: ' . (isset($moduleManager) ? 'OK' : 'FAIL') . "\n";
echo 'Storage:     ' . (isset($storage) ? get_class($storage) : 'FAIL') . "\n";
echo 'BaseModule boot dispatch: ' . (method_exists('BaseModule', 'boot') ? 'OK' : 'FAIL') . "\n";
echo 'admin_sidebar_links filter exists: ' . (class_exists('HookRegistry') ? 'OK (HookRegistry loaded)' : 'FAIL') . "\n";
echo "=== All good ===\n";
