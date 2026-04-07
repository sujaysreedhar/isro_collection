<?php
require_once __DIR__ . '/../config/config.php';

try {
    $sql = file_get_contents(__DIR__ . '/../update.sql');
    if (!$sql) {
        die("Could not read update.sql\n");
    }

    echo "Running migration...\n";
    $pdo->exec($sql);
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
