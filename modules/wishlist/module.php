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

        HookRegistry::addFilter('admin_ajax_wishlist_bulk_add', function ($handled) {
            $this->ajaxBulkAdd();
            return true;
        });

        HookRegistry::addFilter('admin_ajax_wishlist_export', function ($handled) {
            $this->ajaxExport();
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
        $imageUrl = null;

        // Handle Image Upload
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imageUrl = $this->handleImageUpload($_FILES['image']);
        }

        $stmt = $this->pdo->prepare("INSERT INTO module_wishlist_items (name, theme_id, description, status, priority, image_url) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $themeId, $description, $status, $priority, $imageUrl]);
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

        // Handle Image Upload
        $imageUrl = $_POST['existing_image'] ?? null;
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imageUrl = $this->handleImageUpload($_FILES['image']);
        }

        $stmt = $this->pdo->prepare("UPDATE module_wishlist_items SET name = ?, theme_id = ?, description = ?, status = ?, priority = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$name, $themeId, $description, $status, $priority, $imageUrl, $id]);

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

    private function ajaxBulkAdd() {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            return;
        }

        $items = $_POST['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            echo json_encode(['success' => false, 'message' => 'No items provided']);
            return;
        }

        $themeId = !empty($_POST['theme_id']) ? (int)$_POST['theme_id'] : null;
        $status = $_POST['status'] ?? 'wanted';

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("INSERT INTO module_wishlist_items (name, theme_id, status) VALUES (?, ?, ?)");
            foreach ($items as $name) {
                $name = trim($name);
                if (!$name) continue;
                $stmt->execute([$name, $themeId, $status]);
            }
            $this->pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Bulk add failed: ' . $e->getMessage()]);
        }
    }

    private function ajaxExport() {
        // Fetch all items with stores
        $items = $this->pdo->query("SELECT * FROM module_wishlist_items ORDER BY theme_id, priority DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        $filename = "wishlist_export_" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Theme', 'Status', 'Priority', 'Description', 'Stores & Prices']);
        
        // Map themes
        $themeMap = [];
        try {
            $themes = $this->pdo->query("SELECT id, name FROM module_themes")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($themes as $t) $themeMap[$t['id']] = $t['name'];
        } catch (Exception $e) {}

        foreach ($items as $item) {
            $stmt = $this->pdo->prepare("SELECT store_name, price, store_url FROM module_wishlist_stores WHERE wishlist_item_id = ?");
            $stmt->execute([$item['id']]);
            $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $storeStrings = [];
            foreach ($stores as $s) {
                $storeStrings[] = "{$s['store_name']}: {$s['price']} ({$s['store_url']})";
            }
            
            fputcsv($output, [
                $item['name'],
                $themeMap[$item['theme_id'] ?? 0] ?? 'Uncategorized',
                $item['status'],
                $item['priority'],
                $item['description'],
                implode(' | ', $storeStrings)
            ]);
        }
        
        fclose($output);
        exit;
    }

    private function handleImageUpload($file) {
        $uploadDir = ABSPATH . '/uploads/originals/';
        $thumbDir = ABSPATH . '/uploads/thumbnails/';
        
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'wish_' . uniqid() . '.' . $ext;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Simple thumbnail creation if GD is available
            if (extension_loaded('gd')) {
                $this->createThumbnail($targetPath, $thumbDir . $fileName);
            }
            return $fileName;
        }
        return null;
    }

    private function createThumbnail($src, $dest, $size = 300) {
        $info = getimagesize($src);
        if (!$info) return;
        $mime = $info['mime'];

        switch ($mime) {
            case 'image/jpeg': $img = imagecreatefromjpeg($src); break;
            case 'image/png': $img = imagecreatefrompng($src); break;
            case 'image/webp': $img = imagecreatefromwebp($src); break;
            case 'image/gif': $img = imagecreatefromgif($src); break;
            default: return;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $ratio = max($size / $w, $size / $h);
        $newW = (int)($w * $ratio);
        $newH = (int)($h * $ratio);

        $tmp = imagecreatetruecolor($newW, $newH);
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        imagecopyresampled($tmp, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);

        $final = imagecreatetruecolor($size, $size);
        imagealphablending($final, false);
        imagesavealpha($final, true);
        $x = (int)(($newW - $size) / 2);
        $y = (int)(($newH - $size) / 2);
        imagecopy($final, $tmp, 0, 0, $x, $y, $size, $size);

        switch ($mime) {
            case 'image/jpeg': imagejpeg($final, $dest, 85); break;
            case 'image/png': imagepng($final, $dest, 8); break;
            case 'image/webp': imagewebp($final, $dest, 85); break;
            case 'image/gif': imagegif($final, $dest); break;
        }

        imagedestroy($img);
        imagedestroy($tmp);
        imagedestroy($final);
    }
}
