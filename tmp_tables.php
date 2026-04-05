<?php
require 'f:\xampp\htdocs\collection\includes\db.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
$result = [];
foreach ($tables as $t) {
    $stmt = $pdo->query("DESCRIBE $t");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $result[$t] = $cols;
}
echo json_encode($result, JSON_PRETTY_PRINT);
