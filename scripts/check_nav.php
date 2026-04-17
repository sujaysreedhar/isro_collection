<?php
require 'config/config.php';
global $pdo;
try {
    $r = $pdo->query('SELECT id, name, slug FROM navigation_menus')->fetchAll();
    echo "Menus found: " . count($r) . "\n";
    print_r($r);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
