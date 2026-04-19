<?php
require 'config/config.php';
global $pdo;
$themes = $pdo->query("SELECT * FROM settings WHERE setting_key = 'active_theme'")->fetchAll(PDO::FETCH_ASSOC);
print_r($themes);
