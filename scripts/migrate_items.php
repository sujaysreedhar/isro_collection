<?php
require_once __DIR__ . '/../config/config.php';

try {
    $pdo->exec("ALTER TABLE items ADD COLUMN material VARCHAR(255) DEFAULT NULL;");
    echo "Added 'material' column.\n";
} catch (PDOException $e) {
    echo "Column 'material' might already exist or error: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE items ADD COLUMN year_start INT(11) DEFAULT NULL;");
    echo "Added 'year_start' column.\n";
} catch (PDOException $e) {
    echo "Column 'year_start' might already exist or error: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE items ADD COLUMN year_end INT(11) DEFAULT NULL;");
    echo "Added 'year_end' column.\n";
} catch (PDOException $e) {
    echo "Column 'year_end' might already exist or error: " . $e->getMessage() . "\n";
}

echo "Migration completed.\n";
