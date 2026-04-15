<?php
// modules/storage_labels/module.php

class StorageLabelsModule extends BaseModule {

    public function boot() {
        // 1. Admin Hooks
        HookRegistry::addFilter('admin_sidebar_links', function($sections) {
            $sections['catalog']['links']['storage_labels'] = [
                'url'   => SITE_URL . '/admin/module_page.php?m=storage_labels',
                'label' => 'QR Label Generator',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5zM6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 15.75h.75v.75h-.75v-.75zM15.75 13.5h.75v.75h-.75v-.75zM18 15.75h.75v.75h-.75v-.75zM18 18h.75v.75h-.75v-.75zM15.75 18h.75v.75h-.75v-.75zM18 13.5h.75v.75h-.75v-.75zM15.75 15.75h.75v.75h-.75v-.75z" />'
            ];
            return $sections;
        });

        HookRegistry::addAction('admin_page_storage_labels', function() {
            require_once __DIR__ . '/admin_page.php';
        });

        HookRegistry::addAction('admin_item_edit_after_fields', function($id, $item) {
            $storage = $this->getStorageInfo($id);
            require_once __DIR__ . '/views/admin_fields.php';
        }, 16, 2);

        HookRegistry::addAction('item_saved', function($id) {
            if (isset($_POST['storage_labels'])) {
                $this->saveStorageInfo($id, $_POST['storage_labels']);
            }
        });
    }

    public function activate() {
        ModuleDB::createTable($this->pdo, 'module_storage', "
            item_id INT PRIMARY KEY,
            album VARCHAR(100),
            page_number VARCHAR(50),
            box_id VARCHAR(100),
            location_notes TEXT,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ");
    }

    public function getStorageInfo($itemId) {
        if (!$itemId) return [];
        $stmt = $this->pdo->prepare("SELECT * FROM module_storage WHERE item_id = ?");
        $stmt->execute([$itemId]);
        return $stmt->fetch() ?: [];
    }

    public function saveStorageInfo($itemId, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO module_storage (item_id, album, page_number, box_id, location_notes)
            VALUES (:id, :alb, :pg, :box, :notes)
            ON DUPLICATE KEY UPDATE 
                album = VALUES(album),
                page_number = VALUES(page_number),
                box_id = VALUES(box_id),
                location_notes = VALUES(location_notes)
        ");
        $stmt->execute([
            ':id'    => $itemId,
            ':alb'   => trim($data['album'] ?? ''),
            ':pg'    => trim($data['page_number'] ?? ''),
            ':box'   => trim($data['box_id'] ?? ''),
            ':notes' => trim($data['location_notes'] ?? '')
        ]);
    }
}
