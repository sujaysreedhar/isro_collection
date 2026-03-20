<?php
// modules/item_comments/module.php

function item_comments_activate() {
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS item_comments (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            item_id INT(11) NOT NULL,
            author_name VARCHAR(255) NOT NULL,
            author_email VARCHAR(255) NOT NULL,
            comment TEXT NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return true;
    } catch (PDOException $e) {
        error_log("Failed to activate Item Comments module: " . $e->getMessage());
        return false;
    }
}

function item_comments_deactivate() {
    // Optionally drop the table, but better to leave it to preserve user data
    return true;
}

// 1. Add admin menu entry
HookRegistry::addFilter('admin_menu', function($menu) {
    global $pdo;
    // Count pending comments
    $stmt = $pdo->query("SELECT COUNT(*) FROM item_comments WHERE status = 'pending'");
    $pending = $stmt->fetchColumn();
    $badge = $pending > 0 ? " <span class='bg-red-500 text-white text-xs px-2 py-0.5 rounded-full ml-auto'>{$pending}</span>" : "";
    
    $menu[] = [
        'url' => 'module_page.php?m=item_comments',
        'icon' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>',
        'label' => 'Comments' . $badge,
        'active' => isset($_GET['m']) && $_GET['m'] === 'item_comments'
    ];
    return $menu;
});

// 2. Register moderation page route
HookRegistry::addFilter('admin_module_page_item_comments', function($file) {
    return __DIR__ . '/admin_comments.php';
});

// 3. Inject comments UI onto the frontend item detail page
HookRegistry::addAction('frontend_item_detail_bottom', function($item) {
    global $pdo;

    // Fetch approved comments
    $stmt = $pdo->prepare("SELECT author_name, comment, created_at FROM item_comments WHERE item_id = ? AND status = 'approved' ORDER BY created_at DESC");
    $stmt->execute([$item['id']]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Render UI (Requires tailwind)
    require __DIR__ . '/comments_ui.php';
});
