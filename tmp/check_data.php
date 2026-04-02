<?php
// f:\xampp\htdocs\collection\tmp\check_data.php
require_once __DIR__ . '/../config/config.php';

function checkTable($pdo, $name, $query) {
    try {
        $stmt = $pdo->query($query);
        $count = $stmt->fetchColumn();
        echo "$name: $count\n";
    } catch (Exception $e) {
        echo "$name: Table Error (" . $e->getMessage() . ")\n";
    }
}

echo "--- DATA COUNTS ---\n";
checkTable($pdo, "Collections (Public)", "SELECT COUNT(*) FROM collections WHERE is_public = 1");
checkTable($pdo, "Blog Posts (Published)", "SELECT COUNT(*) FROM blog_posts WHERE status = 'published'");
checkTable($pdo, "People (Public)", "SELECT COUNT(*) FROM people WHERE is_public = 1");
