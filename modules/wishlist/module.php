<?php
require_once __DIR__ . '/WishlistService.php';

class WishlistModule extends BaseModule {
    protected function registerAdminMenu() {
        HookRegistry::addFilter('admin_sidebar_links', function (array $sections) {
            $sections['catalog']['links']['wishlist'] = [
                'url' => SITE_URL . '/admin/module_page.php?m=' . $this->slug,
                'label' => 'Wantlist & sellers',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />',
            ];
            return $sections;
        });
        HookRegistry::addAction('admin_page_' . $this->slug, function () { require __DIR__ . '/admin_wishlist.php'; });
    }

    protected function registerHooks() {
        foreach (['add','edit','delete','update_status','migrate','bulk_add','export'] as $operation) {
            HookRegistry::addFilter('admin_ajax_wishlist_' . $operation, function ($handled) use ($operation) { $this->dispatch($operation); return true; });
        }
    }

    public function activate() {
        ModuleDB::createTable($this->pdo, 'module_wishlist_items', "
            id INT AUTO_INCREMENT PRIMARY KEY, theme_id INT NULL, name VARCHAR(255) NOT NULL,
            description TEXT, image_url VARCHAR(255),
            status ENUM('wanted','buying','collected','searching','shortlisted','ordered','acquired') DEFAULT 'searching',
            priority INT DEFAULT 0, item_type VARCHAR(16) NOT NULL DEFAULT 'other',
            catalogue_ref VARCHAR(120) NULL, target_price DECIMAL(14,2) NULL, target_currency CHAR(3) NULL,
            collection_item_id INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wishlist_theme (theme_id), INDEX idx_wishlist_status (status)
        ");
        ModuleDB::createTable($this->pdo, 'module_wishlist_stores', "
            id INT AUTO_INCREMENT PRIMARY KEY, wishlist_item_id INT NOT NULL,
            store_name VARCHAR(255), store_url VARCHAR(2000), price VARCHAR(100), notes TEXT,
            amount DECIMAL(14,2) NULL, currency CHAR(3) NULL, shipping DECIMAL(14,2) NULL,
            availability ENUM('available','unknown','unavailable','sold') NOT NULL DEFAULT 'unknown',
            last_checked DATE NULL, archived TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wishlist_item_store (wishlist_item_id),
            CONSTRAINT fk_wishlist_item_store FOREIGN KEY (wishlist_item_id) REFERENCES module_wishlist_items(id) ON DELETE CASCADE
        ");
        ModuleDB::createTable($this->pdo, 'module_wishlist_price_history', "
            id INT AUTO_INCREMENT PRIMARY KEY, store_id INT NOT NULL,
            amount DECIMAL(14,2) NULL, currency CHAR(3) NULL, shipping DECIMAL(14,2) NULL,
            availability ENUM('available','unknown','unavailable','sold') NOT NULL DEFAULT 'unknown',
            checked_on DATE NULL, recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wishlist_history_store (store_id, recorded_at),
            CONSTRAINT fk_wishlist_history_store FOREIGN KEY (store_id) REFERENCES module_wishlist_stores(id) ON DELETE CASCADE
        ");
    }

    private function dispatch(string $operation): void {
        try {
            if (empty($_SESSION['admin_id'])) { http_response_code(403); throw new DomainException('Please sign in to the admin panel.'); }
            if ($operation !== 'export' && (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verifyCsrfToken(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null))) {
                http_response_code(403); throw new DomainException('Security check failed. Reload this page and try again.');
            }
            $service = new WishlistService($this->pdo);
            if (!$service->schemaReady()) throw new DomainException('Run the database update to enable seller price tracking.');
            if ($operation === 'export') { $this->export($service); return; }
            if ($operation === 'add' || $operation === 'edit') {
                WishlistService::validateItem($_POST); WishlistService::validateOffers($_POST['stores'] ?? []);
                $image = $this->uploadImage($_FILES['image'] ?? null);
                try { $id = $service->save($_POST, $image); }
                catch (Throwable $e) { if ($image) { @unlink(ABSPATH . '/uploads/originals/' . $image); @unlink(ABSPATH . '/uploads/thumbnails/' . $image); } throw $e; }
                echo json_encode(['success' => true, 'id' => $id]); return;
            }
            if ($operation === 'bulk_add') { $this->bulkAdd(); return; }
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) throw new DomainException('Choose a valid wanted item.');
            if ($operation === 'delete') {
                $this->pdo->prepare('DELETE FROM module_wishlist_items WHERE id=?')->execute([$id]);
                echo json_encode(['success' => true]); return;
            }
            if ($operation === 'update_status') {
                $status = WishlistService::status(is_string($_POST['status'] ?? null) ? $_POST['status'] : '');
                if (!isset(WishlistService::STATUSES[$status])) throw new DomainException('Choose a valid status.');
                $this->pdo->prepare('UPDATE module_wishlist_items SET status=? WHERE id=?')->execute([$status, $id]);
                echo json_encode(['success' => true]); return;
            }
            if ($operation === 'migrate') { $this->migrate($id); return; }
        } catch (DomainException $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('Wishlist action failed: ' . $e->getMessage());
            http_response_code(500); echo json_encode(['success' => false, 'message' => 'The wanted item could not be saved. Please try again.']);
        }
    }

    private function bulkAdd(): void {
        $names = $_POST['items'] ?? [];
        if (!is_array($names) || !$names || count($names) > 100) throw new DomainException('Enter between 1 and 100 item names.');
        $rows = [];
        foreach ($names as $name) {
            if (!is_string($name)) throw new DomainException('Invalid item name.');
            if (trim($name) === '') continue;
            $rows[] = WishlistService::validateItem(['name' => $name, 'theme_id' => $_POST['theme_id'] ?? '', 'status' => $_POST['status'] ?? 'searching', 'item_type' => $_POST['item_type'] ?? 'stamp']);
        }
        if (!$rows) throw new DomainException('Enter at least one item name.');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('INSERT INTO module_wishlist_items (name,description,theme_id,status,item_type,priority,catalogue_ref,target_price,target_currency) VALUES (?,?,?,?,?,?,?,?,?)');
            foreach ($rows as $row) $stmt->execute(array_values($row));
            $this->pdo->commit(); echo json_encode(['success' => true, 'count' => count($rows)]);
        } catch (Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    private function migrate(int $id): void {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM module_wishlist_items WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) throw new DomainException('Wanted item not found.');
            if (!empty($item['collection_item_id'])) throw new DomainException('This wanted item is already linked to your collection.');
            // Acquisition notes stay in the private wantlist, never copied into a publishable description.
            $this->pdo->prepare('INSERT INTO items (title,physical_description,reg_number,is_visible) VALUES (?,?,?,0)')->execute([$item['name'], '', 'WISH-' . $id . '-' . date('YmdHis')]);
            $newId = (int)$this->pdo->lastInsertId();
            if (!empty($item['theme_id']) && $this->pdo->query("SHOW TABLES LIKE 'module_theme_item'")->fetchColumn()) $this->pdo->prepare('INSERT IGNORE INTO module_theme_item (theme_id,item_id) VALUES (?,?)')->execute([$item['theme_id'], $newId]);
            $this->pdo->prepare("UPDATE module_wishlist_items SET status='acquired',collection_item_id=? WHERE id=?")->execute([$newId, $id]);
            $this->pdo->commit();
            echo json_encode(['success' => true, 'new_item_id' => $newId, 'redirect' => SITE_URL . '/admin/edit_item.php?id=' . $newId]);
        } catch (Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    private function export(WishlistService $service): void {
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="wantlist-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Item','Type','Catalogue reference','Status','Target price','Target currency','Seller','URL','Price','Currency','Shipping','Availability','Last checked','Private notes','Archived']);
        foreach ($service->all() as $item) {
            foreach ($item['stores'] ?: [null] as $offer) {
                $row = [$item['name'],$item['item_type'],$item['catalogue_ref'],$item['status'],$item['target_price'],$item['target_currency'],$offer['store_name'] ?? '',$offer['store_url'] ?? '',$offer['amount'] ?? ($offer['price'] ?? ''),$offer['currency'] ?? '',$offer['shipping'] ?? '',$offer['availability'] ?? '',$offer['last_checked'] ?? '',$offer['notes'] ?? '',$offer['archived'] ?? ''];
                $row = array_map(static function ($value) { $text = (string)($value ?? ''); return preg_match('/^[\s]*[=+@-]/', $text) ? "'" . $text : $text; }, $row);
                fputcsv($out, $row);
            }
        }
        fclose($out);
    }

    private function uploadImage(?array $file): ?string {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (!extension_loaded('gd')) throw new DomainException('Image uploads require the GD extension.');
        require_once ABSPATH . '/includes/ImageUploadValidator.php';
        $info = ImageUploadValidator::uploaded($file);
        $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$info['mime']];
        $name = 'wish_' . bin2hex(random_bytes(16)) . '.' . $ext;
        foreach (['originals','thumbnails'] as $folder) if (!is_dir(ABSPATH . '/uploads/' . $folder) && !mkdir(ABSPATH . '/uploads/' . $folder, 0755, true)) throw new DomainException('The image folder is not writable.');
        $original = ABSPATH . '/uploads/originals/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $original)) throw new DomainException('The reference image could not be saved.');
        try {
            $decoder = ['image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/gif'=>'imagecreatefromgif','image/webp'=>'imagecreatefromwebp'][$info['mime']];
            $source = $decoder($original);
            $ratio = min(1, 400 / $info['width'], 400 / $info['height']);
            $thumb = imagecreatetruecolor(max(1, (int)round($info['width'] * $ratio)), max(1, (int)round($info['height'] * $ratio)));
            imagealphablending($thumb, false); imagesavealpha($thumb, true);
            $clear = imagecolorallocatealpha($thumb, 255, 255, 255, 127); imagefill($thumb, 0, 0, $clear);
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, imagesx($thumb), imagesy($thumb), $info['width'], $info['height']);
            $dest = ABSPATH . '/uploads/thumbnails/' . $name;
            $saved = match ($info['mime']) { 'image/jpeg' => imagejpeg($thumb, $dest, 88), 'image/png' => imagepng($thumb, $dest, 6), 'image/gif' => imagegif($thumb, $dest), 'image/webp' => imagewebp($thumb, $dest, 88) };
            imagedestroy($source); imagedestroy($thumb);
            if (!$saved) throw new RuntimeException('Thumbnail write failed.');
            return $name;
        } catch (Throwable $e) { @unlink($original); @unlink(ABSPATH . '/uploads/thumbnails/' . $name); throw $e; }
    }
}
