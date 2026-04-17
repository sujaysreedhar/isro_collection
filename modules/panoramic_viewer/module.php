<?php
// modules/panoramic_viewer/module.php

class PanoramicViewerModule extends BaseModule
{

    public function boot()
    {
        // Register Admin Hooks
        HookRegistry::addAction('admin_item_edit_after_fields', [$this, 'renderAdminFields'], 10, 2);
        HookRegistry::addAction('item_saved', [$this, 'handleSave'], 10, 1);

        // Register Frontend Hooks
        HookRegistry::addAction('frontend_head', [$this, 'injectAssets']);
        HookRegistry::addAction('item_after_content', [$this, 'renderViewer'], 5, 1);
        
        // New Hooks per user request
        HookRegistry::addAction('item_card_badge', [$this, 'renderCardBadge'], 10, 1);
        HookRegistry::addAction('item_gallery_thumbnails', [$this, 'renderGalleryThumbnails'], 10, 1);
        
        // Cleanup on bulk delete
        HookRegistry::addAction('before_bulk_delete', [$this, 'handleBulkDelete'], 10, 1);
    }

    public function activate()
    {
        $uploadDir = ABSPATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'panoramics';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
    }

    public function renderAdminFields($id, $item)
    {
        $pdo = $this->pdo;
        $panoramics = [];
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM item_panoramics WHERE item_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$id]);
            $panoramics = $stmt->fetchAll();
        }
        require __DIR__ . DIRECTORY_SEPARATOR . 'admin_fields.php';
    }

    public function handleSave($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            return;

        $pdo = $this->pdo;

        // 1. Handle Deletions
        $deleteIds = $_POST['delete_panoramic'] ?? [];
        if (!empty($deleteIds)) {
            $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
            $stmt = $pdo->prepare("SELECT file_path FROM item_panoramics WHERE id IN ($placeholders) AND item_id = ?");
            $stmt->execute(array_merge($deleteIds, [$id]));
            $toDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($toDelete as $file) {
                $filePath = ABSPATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'panoramics' . DIRECTORY_SEPARATOR . $file;
                if (file_exists($filePath))
                    unlink($filePath);
            }

            $stmtDel = $pdo->prepare("DELETE FROM item_panoramics WHERE id IN ($placeholders) AND item_id = ?");
            $stmtDel->execute(array_merge($deleteIds, [$id]));
        }

        // 2. Handle Caption Updates for Existing
        $existingCaptions = $_POST['existing_panoramic_captions'] ?? [];
        foreach ($existingCaptions as $panoId => $newCaption) {
            $stmtUpd = $pdo->prepare("UPDATE item_panoramics SET caption = ? WHERE id = ? AND item_id = ?");
            $stmtUpd->execute([$newCaption, $panoId, $id]);
        }

        // 3. Handle New Uploads
        if (isset($_FILES['panoramic_files'])) {
            $files = $this->reIndexFiles($_FILES['panoramic_files']);
            $captions = $_POST['panoramic_captions'] ?? [];

            foreach ($files as $i => $file) {
                if ($file['error'] === UPLOAD_ERR_NO_FILE)
                    continue;

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($ext, $allowed))
                    continue;

                // 25 MB Max Size
                if ($file['size'] > 25 * 1024 * 1024)
                    continue;

                $newFileName = 'pano_' . $id . '_' . time() . '_' . $i . '.' . $ext;
                $targetPath = ABSPATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'panoramics' . DIRECTORY_SEPARATOR . $newFileName;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $caption = $captions[$i] ?? '';
                    $stmtIns = $pdo->prepare("INSERT INTO item_panoramics (item_id, file_path, caption) VALUES (?, ?, ?)");
                    $stmtIns->execute([$id, $newFileName, $caption]);
                }
            }
        }
    }

    public function renderCardBadge($item) {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM item_panoramics WHERE item_id = ?");
        $stmt->execute([$item['id']]);
        $hasPano = (bool) $stmt->fetchColumn();

        if ($hasPano) {
            echo '<div class="absolute top-2 right-2 px-2 py-1 bg-black/60 backdrop-blur-md rounded text-[10px] font-bold text-white uppercase tracking-wider flex items-center gap-1.5 shadow-lg border border-white/10 z-10">
                    <svg class="w-3 h-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    360°
                  </div>';
        }
    }

    public function renderGalleryThumbnails($item) {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("SELECT * FROM item_panoramics WHERE item_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$item['id']]);
        $panos = $stmt->fetchAll();

        foreach ($panos as $p) {
            $url = SITE_URL . '/uploads/panoramics/' . $p['file_path'];
            $caption = htmlspecialchars($p['caption'] ?: '360° View');
            echo '<div class="relative group cursor-pointer pano-thumbnail" onclick="switchPanorama(this, \'' . $url . '\', \'' . addslashes($caption) . '\')">
                    <img src="'.$url.'" class="w-20 h-14 object-cover rounded-lg border-2 border-transparent hover:border-blue-500 transition-all opacity-80 hover:opacity-100 shadow-sm" alt="360 view">
                    <div class="absolute inset-0 flex items-center justify-center bg-black/20 rounded-lg group-hover:bg-transparent transition-all">
                        <span class="text-white text-[10px] font-bold">360°</span>
                    </div>
                  </div>';
        }
    }

    public function injectAssets()
    {
        echo '<script src="https://cdn.jsdelivr.net/npm/marzipano@0.10.2/dist/marzipano.min.js"></script>' . "\n";
        echo '<style>
            .pano-container { width: 100%; height: 500px; background: #111; border-radius: 16px; overflow: hidden; margin-bottom: 2rem; position: relative; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
            .pano-nav { display: flex; gap: 0.75rem; margin-bottom: 1.25rem; overflow-x: auto; padding-bottom: 8px; scrollbar-width: none; }
            .pano-nav::-webkit-scrollbar { display: none; }
            .pano-nav-btn { padding: 0.625rem 1.25rem; background: #fff; border-radius: 12px; font-size: 0.813rem; font-weight: 600; cursor: pointer; white-space: nowrap; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid #e2e8f0; color: #475569; position: relative; overflow: hidden; }
            .pano-nav-btn.active { background: #1e293b; color: #fff; border-color: #1e293b; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            .pano-nav-btn:hover:not(.active) { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
            
            .gallery-thumbnail { cursor: pointer; width: 80px; height: 60px; object-cover; border-radius: 8px; border: 2px solid transparent; transition: all 0.2s; }
            .gallery-thumbnail.active { border-color: #3b82f6; }
        </style>' . "\n";
    }

    public function renderViewer($item)
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("SELECT * FROM item_panoramics WHERE item_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$item['id']]);
        $panoramics = $stmt->fetchAll();

        if (empty($panoramics))
            return;

        require __DIR__ . DIRECTORY_SEPARATOR . 'viewer_template.php';
    }

    private function reIndexFiles($files)
    {
        $out = [];
        if (!is_array($files['name'])) {
            return [$files];
        }
        foreach ($files['name'] as $i => $name) {
            $out[] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
        }
        return $out;
    }

    public function handleBulkDelete($ids)
    {
        if (empty($ids)) return;
        $pdo = $this->pdo;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            $stmt = $pdo->prepare("SELECT file_path FROM item_panoramics WHERE item_id IN ($placeholders)");
            $stmt->execute($ids);
            $files = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($files as $file) {
                $filePath = ABSPATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'panoramics' . DIRECTORY_SEPARATOR . $file;
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            $stmtDel = $pdo->prepare("DELETE FROM item_panoramics WHERE item_id IN ($placeholders)");
            $stmtDel->execute($ids);
        } catch (\PDOException $e) {
            // Table might not exist or other DB error
        }
    }
}
