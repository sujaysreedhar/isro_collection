<?php
// modules/panoramic_viewer/module.php

class PanoramicViewerModule extends BaseModule {

    public function boot() {
        // Register Admin Hooks
        HookRegistry::addAction('admin_item_edit_after_fields', [$this, 'renderAdminFields'], 10, 2);
        HookRegistry::addAction('item_saved', [$this, 'handleSave'], 10, 1);

        // Register Frontend Hooks
        HookRegistry::addAction('frontend_head', [$this, 'injectAssets']);
        HookRegistry::addAction('item_before_content', [$this, 'renderViewer'], 5, 1);
    }

    public function activate() {
        // Ensure upload directory exists
        $uploadDir = ABSPATH . '/uploads/panoramics';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Schema is handled via update.sql in this project's workflow,
        // but we could also use ModuleDB::createTable here if needed.
    }

    public function renderAdminFields($id, $item) {
        $pdo = $this->pdo;
        $panoramics = [];
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM item_panoramics WHERE item_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$id]);
            $panoramics = $stmt->fetchAll();
        }
        require __DIR__ . '/admin_fields.php';
    }

    public function handleSave($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $pdo = $this->pdo;

        // 1. Handle Deletions
        $deleteIds = $_POST['delete_panoramic'] ?? [];
        if (!empty($deleteIds)) {
            $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
            // Fetch file paths to delete physical files
            $stmt = $pdo->prepare("SELECT file_path FROM item_panoramics WHERE id IN ($placeholders) AND item_id = ?");
            $stmt->execute(array_merge($deleteIds, [$id]));
            $toDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($toDelete as $file) {
                $filePath = ABSPATH . '/uploads/panoramics/' . $file;
                if (file_exists($filePath)) unlink($filePath);
            }

            $stmtDel = $pdo->prepare("DELETE FROM item_panoramics WHERE id IN ($placeholders) AND item_id = ?");
            $stmtDel->execute(array_merge($deleteIds, [$id]));
        }

        // 2. Handle New Uploads
        if (isset($_FILES['panoramic_files'])) {
            $files = $this->reIndexFiles($_FILES['panoramic_files']);
            $captions = $_POST['panoramic_captions'] ?? [];
            
            foreach ($files as $i => $file) {
                if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($ext, $allowed)) continue;

                $newFileName = 'pano_' . $id . '_' . time() . '_' . $i . '.' . $ext;
                $targetPath = ABSPATH . '/uploads/panoramics/' . $newFileName;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $caption = $captions[$i] ?? '';
                    $stmtIns = $pdo->prepare("INSERT INTO item_panoramics (item_id, file_path, caption) VALUES (?, ?, ?)");
                    $stmtIns->execute([$id, $newFileName, $caption]);
                }
            }
        }
    }

    public function injectAssets() {
        // Pannellum CDN
        echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css"/>' . "\n";
        echo '<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>' . "\n";
        echo '<style>
            .pano-container { width: 100%; height: 450px; background: #000; border-radius: 12px; overflow: hidden; margin-bottom: 2rem; }
            .pano-nav { display: flex; gap: 0.5rem; margin-bottom: 1rem; overflow-x: auto; padding-bottom: 5px; }
            .pano-nav-btn { padding: 0.5rem 1rem; background: #f3f4f6; border-radius: 9999px; font-size: 0.875rem; font-weight: 500; cursor: pointer; white-space: nowrap; transition: all 0.2s; border: 1px solid transparent; }
            .pano-nav-btn.active { background: #111827; color: #fff; }
            .pano-nav-btn:hover:not(.active) { background: #e5e7eb; }
        </style>' . "\n";
    }

    public function renderViewer($item) {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("SELECT * FROM item_panoramics WHERE item_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$item['id']]);
        $panoramics = $stmt->fetchAll();

        if (empty($panoramics)) return;

        require __DIR__ . '/viewer_template.php';
    }

    private function reIndexFiles($files) {
        $out = [];
        if (!is_array($files['name'])) {
            return [$files];
        }
        foreach ($files['name'] as $i => $name) {
            $out[] = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
        }
        return $out;
    }
}
