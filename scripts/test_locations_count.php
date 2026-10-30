<?php
require 'config/config.php';
global $pdo;
$c = $pdo->query('SELECT COUNT(*) FROM postmark_locations')->fetchColumn();
echo "Total locations: $c\n";
$samples = $pdo->query('SELECT * FROM postmark_locations LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
print_r($samples);
