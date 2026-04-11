<?php
require_once __DIR__ . '/../config/config.php';
try {
    $stmt = $pdo->query("DESCRIBE items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($columns, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
