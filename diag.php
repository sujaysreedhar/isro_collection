<?php
require_once __DIR__ . '/config/config.php';
global $pdo;

echo "--- Database Diagnosis ---\n";

// 1. Check if item_related table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'item_related'");
    $exists = $stmt->fetch();
    if ($exists) {
        echo "[OK] Table 'item_related' exists.\n";
    } else {
        echo "[ERROR] Table 'item_related' DOES NOT exist.\n";
    }
} catch (Exception $e) {
    echo "[ERROR] Error checking table: " . $e->getMessage() . "\n";
}

// 2. Check for item 98 relations
try {
    $stmt = $pdo->prepare("SELECT * FROM item_related WHERE item_id = 98");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    if (count($rows) > 0) {
        echo "[INFO] Item 98 has " . count($rows) . " related items in DB.\n";
        print_r($rows);
    } else {
        echo "[INFO] Item 98 has NO related items in DB.\n";
    }
} catch (Exception $e) {
    echo "[ERROR] Error fetching relations: " . $e->getMessage() . "\n";
}

// 3. Check for view_count column
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM items LIKE 'view_count'");
    $col = $stmt->fetch();
    if ($col) {
        echo "[OK] Column 'view_count' exists in 'items' table.\n";
    } else {
        echo "[ERROR] Column 'view_count' DOES NOT exist in 'items' table.\n";
    }
} catch (Exception $e) {
    echo "[ERROR] Error checking column: " . $e->getMessage() . "\n";
}
