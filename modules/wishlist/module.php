<?php

class WishlistModule extends BaseModule {

    protected function registerAdminMenu() {
        HookRegistry::addFilter('admin_sidebar_links', function (array $sections) {
            $sections['catalog']['links']['wishlist'] = [
                'url' => SITE_URL . '/admin/module_page.php?m=' . $this->slug,
                'label' => 'Wishlist',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />'
            ];
            return $sections;
        });

        HookRegistry::addAction('admin_page_' . $this->slug, function () {
            require_once __DIR__ . '/admin_wishlist.php';
        });
    }

    protected function registerHooks() {
        // Custom AJAX handlers for wishlist
        HookRegistry::addFilter('admin_ajax_wishlist_add', function ($handled) {
            $this->ajaxAdd();
            return true;
        });

        HookRegistry::addFilter('admin_ajax_wishlist_edit', function ($handled) {
            $this->ajaxEdit();
            return true;
        });

        HookRegistry::addFilter('admin_ajax_wishlist_delete', function ($handled) {
            $this->ajaxDelete();
            return true;
        });

        HookRegistry::addFilter('admin_ajax_wishlist_update_status', function ($handled) {
            $this->ajaxUpdateStatus();
            return true;
        });

        HookRegistry::addFilter('admin_ajax_wishlist_migrate', function ($handled) {
            $this->ajaxMigrate();
            return true;
        });
    }

    public function activate() {
        ModuleDB::createTable($this->pdo, 'module_wishlist_items', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            theme_id INT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            image_url VARCHAR(255),
            status ENUM('wanted', 'buying', 'collected') DEFAULT 'wanted',
            priority INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wishlist_theme (theme_id),
            INDEX idx_wishlist_status (status)
        ");

        ModuleDB::createTable($this->pdo, 'module_wishlist_stores', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            wishlist_item_id INT NOT NULL,
            store_name VARCHAR(255),
            store_url VARCHAR(255),
            price VARCHAR(100),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wishlist_item_store (wishlist_item_id),
            CONSTRAINT fk_wishlist_item_store FOREIGN KEY (wishlist_item_id) REFERENCES module_wishlist_items(id) ON DELETE CASCADE
        ");
    }

    public function deactivate() {
        // We keep the data even if module is deactivated
    }

    // --- AJAX Handlers ---

    private function ajaxAdd() {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Name is required']);
            return;
        }

        $themeId = !empty($_POST['theme_id']) ? (int)$_POST['theme_id'] : null;
        $description = $_POST['description'] ?? '';
        $status = $_POST['status'] ?? 'wanted';
        $priority = (int)($_POST['priority'] ?? 0);

        $stmt = $this->pdo->prepare("INSERT INTO module_wishlist_items (name, theme_id, description, status, priority) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $themeId, $description, $status, $priority]);
        $itemId = $this->pdo->lastInsertId();

        // Handle stores
        if (!empty($_POST['stores']) && is_array($_POST['stores'])) {
            $storeStmt = $this->pdo->prepare("INSERT INTO module_wishlist_stores (wishlist_item_id, store_name, store_url, price, notes) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['stores'] as $store) {
                if (empty($store['name']) && empty($store['url'])) continue;
                $storeStmt->execute([$itemId, $store['name'], $store['url'], $store['price'], $store['notes']]);
            }
        }

        echo json_encode(['success' => true, 'id' => $itemId]);
    }

    private function ajaxEdit() {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            return;
        }

        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Name is required']);
            return;
        }

        $themeId = !empty($_POST['theme_id']) ? (int)$_POST['theme_id'] : null;
        $description = $_POST['description'] ?? '';
        $status = $_POST['status'] ?? 'wanted';
        $priority = (int)($_POST['priority'] ?? 0);

        $stmt = $this->pdo->prepare("UPDATE module_wishlist_items SET name = ?, theme_id = ?, description = ?, status = ?, priority = ? WHERE id = ?");
        $stmt->execute([$name, $themeId, $description, $status, $priority, $id]);

        // Sync stores
        $this->pdo->prepare("DELETE FROM module_wishlist_stores WHERE wishlist_item_id = ?")->execute([$id]);
        if (!empty($_POST['stores']) && is_array($_POST['stores'])) {
            $storeStmt = $this->pdo->prepare("INSERT INTO module_wishlist_stores (wishlist_item_id, store_name, store_url, price, notes) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['stores'] as $store) {
                if (empty($store['name']) && empty($store['url'])) continue;
                $storeStmt->execute([$id, $store['name'], $store['url'], $store['price'], $store['notes']]);
            }
        }

        echo json_encode(['success' => true]);
    }

    private function ajaxDelete() {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            return;
        }

        $id = (int)$_POST['id'];
        $this->pdo->prepare("DELETE FROM module_wishlist_items WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    }

    private function ajaxUpdateStatus() {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            return;
        }

        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        $this->pdo->prepare("UPDATE module_wishlist_items SET status = ? WHERE id = ?")->execute([$status, $id]);
        echo json_encode(['success' => true]);
    }

    private function ajaxMigrate() {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            return;
        }

        $id = (int)$_POST['id'];
        
        // Fetch wishlist item
        $stmt = $this->pdo->prepare("SELECT * FROM module_wishlist_items WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Item not found']);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            // Create new item in main 'items' table
            $ins = $this->pdo->prepare("INSERT INTO items (title, physical_description, reg_number) VALUES (?, ?, ?)");
            // Generate a temporary reg number if none exists?
            $regNumber = "WISH-" . $id . "-" . date('Ymd');
            $ins->execute([$item['name'], $item['description'], $regNumber]);
            $newItemId = $this->pdo->lastInsertId();

            // Link to theme if thematic_taxonomy is present
            if ($item['theme_id']) {
                // Check if module_theme_item exists
                $themeCheck = $this->pdo->query("SHOW TABLES LIKE 'module_theme_item'")->fetch();
                if ($themeCheck) {
                    $this->pdo->prepare("INSERT IGNORE INTO module_theme_item (theme_id, item_id) VALUES (?, ?)")->execute([$item['theme_id'], $newItemId]);
                }
            }

            // Update status to 'collected'
            $this->pdo->prepare("UPDATE module_wishlist_items SET status = 'collected' WHERE id = ?")->execute([$id]);

            $this->pdo->commit();

            echo json_encode([
                'success' => true, 
                'new_item_id' => $newItemId,
                'redirect' => SITE_URL . '/admin/edit_item.php?id=' . $newItemId
            ]);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()]);
        }
    }
}
