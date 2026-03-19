<?php
// scripts/find_webp.php
require_once __DIR__ . '/../config/config.php';

$stmt = $pdo->query("SELECT id, item_id, file_path FROM media WHERE file_path LIKE '%.webp'");
$webpFiles = $stmt->fetchAll();

echo "--- WebP Files in Database ---\n";
foreach ($webpFiles as $row) {
    $path = __DIR__ . '/../uploads/original/' . $row['file_path'];
    $exists = file_exists($path) ? "EXISTS" : "MISSING";
    echo "ID: {$row['id']} | File: {$row['file_path']} | Status: $exists | Full Path: $path\n";
}

$stmt = $pdo->query("SELECT id, item_id, file_path FROM media WHERE file_path LIKE '%.png'");
$pngFiles = $stmt->fetchAll();
echo "\n--- PNG Files in Database (Reference) ---\n";
foreach ($pngFiles as $row) {
    echo "ID: {$row['id']} | File: {$row['file_path']}\n";
}
