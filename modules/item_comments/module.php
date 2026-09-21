<?php
// modules/item_comments/module.php

if (!class_exists('BaseModule')) {
    require_once __DIR__ . '/../../includes/BaseModule.php';
}

class ItemCommentsModule extends BaseModule {

    public function activate() {
        try {
            if (class_exists('ModuleDB')) {
                ModuleDB::createTable($this->pdo, 'item_comments', "
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    item_id INT(11) NOT NULL,
                    author_name VARCHAR(255) NOT NULL,
                    author_email VARCHAR(255) NOT NULL,
                    comment TEXT NOT NULL,
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
                ");
            } else {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS item_comments (
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    item_id INT(11) NOT NULL,
                    author_name VARCHAR(255) NOT NULL,
                    author_email VARCHAR(255) NOT NULL,
                    comment TEXT NOT NULL,
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }
            return true;
        } catch (PDOException $e) {
            error_log("Failed to activate Item Comments module: " . $e->getMessage());
            return false;
        }
    }

    public function deactivate() {
        return true;
    }

    public function registerAdminMenu() {
        $pdo = $this->pdo;

        // 1. Add admin sidebar menu entry
        HookRegistry::addFilter('admin_sidebar_links', function(array $sections) use ($pdo) {
            $pending = 0;
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM item_comments WHERE status = 'pending'");
                $pending = (int)$stmt->fetchColumn();
            } catch (\PDOException) {}
            $badge = $pending > 0 ? " ({$pending})" : "";

            $sections['catalog']['links']['item_comments'] = [
                'url'   => SITE_URL . '/admin/module_page.php?m=item_comments',
                'label' => 'Comments' . $badge,
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>',
            ];
            return $sections;
        });

        // 2. Register admin moderation page route
        HookRegistry::addAction('admin_page_item_comments', function() {
            require_once __DIR__ . '/admin_comments.php';
        });
    }

    public function registerHooks() {
        $pdo = $this->pdo;

        // 3. Inject comments UI onto the frontend item detail page
        HookRegistry::addAction('frontend_item_detail_bottom', function($item) use ($pdo) {
            if (!$item || empty($item['id'])) {
                return;
            }

            // Fetch approved comments
            $stmt = $pdo->prepare("SELECT author_name, comment, created_at FROM item_comments WHERE item_id = ? AND status = 'approved' ORDER BY created_at DESC");
            $stmt->execute([$item['id']]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Render UI
            require __DIR__ . '/comments_ui.php';
        });
    }
}

// Procedural backward compatibility wrappers
function item_comments_activate() {
    global $pdo;
    $module = new ItemCommentsModule($pdo, 'item_comments', []);
    return $module->activate();
}

function item_comments_deactivate() {
    return true;
}
