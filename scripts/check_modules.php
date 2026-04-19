<?php
require 'config/config.php';
global $pdo;
$modules = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'active_modules'")->fetchColumn();
echo "Active Modules:\n";
print_r(json_decode($modules, true));
