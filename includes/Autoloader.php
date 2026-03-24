<?php
/**
 * Simple Autoloader for the 'includes' directory.
 */
spl_autoload_register(function ($class) {
    $baseDir = __DIR__ . '/';
    $file = $baseDir . $class . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
