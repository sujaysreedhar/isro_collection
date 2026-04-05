<?php
require 'config/config.php';
global $pdo;

$count = $pdo->query('SELECT COUNT(*) FROM postmark_locations')->fetchColumn();
echo "Database count: $count\n";

$latlonCount = $pdo->query('SELECT COUNT(*) FROM postmark_locations WHERE latitude IS NOT NULL AND longitude IS NOT NULL')->fetchColumn();
echo "With coordinates: $latlonCount\n";
