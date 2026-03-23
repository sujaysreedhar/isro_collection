<?php
require_once 'config/config.php';
function describeTable($pdo, $table) {
    echo "--- $table ---\n";
    $stmt = $pdo->query("DESCRIBE $table");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        printf("%-20s %-20s %-10s\n", $row['Field'], $row['Type'], $row['Null']);
    }
    echo "\n";
}

describeTable($pdo, 'items');
describeTable($pdo, 'postmark_locations');
describeTable($pdo, 'categories');
describeTable($pdo, 'media');

echo "--- Categories List ---\n";
$stmt = $pdo->query("SELECT * FROM categories");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "--- Items Sample ---\n";
$stmt = $pdo->query("SELECT id, title FROM items LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "--- Migration Counts ---\n";
$itemsCount = $pdo->query("SELECT COUNT(*) FROM items WHERE category_id = 10")->fetchColumn();
$locCount = $pdo->query("SELECT COUNT(*) FROM postmark_locations")->fetchColumn();
$mediaCount = $pdo->query("SELECT COUNT(*) FROM media WHERE item_id IN (SELECT id FROM items WHERE category_id = 10)")->fetchColumn();
echo "Migrated Items: $itemsCount\n";
echo "Postmark Locations: $locCount\n";
echo "Migration Media: $mediaCount\n";
