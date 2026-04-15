<?php
// modules/set_manager/module.php

class SetManagerModule extends BaseModule {

    public function boot() {
        // 1. Admin Hooks
        HookRegistry::addFilter('admin_sidebar_links', function($sections) {
            $sections['catalog']['links']['set_manager'] = [
                'url'   => SITE_URL . '/admin/module_page.php?m=set_manager',
                'label' => '📦 Sets / Checklists',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />'
            ];
            return $sections;
        });

        HookRegistry::addAction('admin_page_set_manager', function() {
            require_once __DIR__ . '/admin_page.php';
        });

        HookRegistry::addAction('admin_item_edit_after_fields', function($id, $item) {
            $sets = $this->getAllSets();
            $itemSetId = $this->getItemSetId($id);
            require_once __DIR__ . '/views/admin_fields.php';
        }, 15, 2);

        HookRegistry::addAction('item_saved', function($id) {
            if (isset($_POST['set_manager_id'])) {
                $this->saveItemSet($id, (int)$_POST['set_manager_id']);
            }
        });

        // 2. Frontend Hooks
        HookRegistry::addAction('item_after_content', function($item) {
            $setId = $this->getItemSetId($item['id']);
            if ($setId) {
                $set = $this->getSet($setId);
                $progress = $this->getSetProgress($setId);
                require_once __DIR__ . '/views/frontend_display.php';
            }
        });
    }

    public function activate() {
        ModuleDB::createTable($this->pdo, 'module_sets', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            target_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ");

        ModuleDB::createTable($this->pdo, 'module_set_items', "
            set_id INT NOT NULL,
            item_id INT NOT NULL,
            PRIMARY KEY (set_id, item_id),
            FOREIGN KEY (set_id) REFERENCES module_sets(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ");
    }

    public function getAllSets() {
        return $this->pdo->query("SELECT * FROM module_sets ORDER BY name ASC")->fetchAll();
    }

    public function getSet($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM module_sets WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getItemSetId($itemId) {
        if (!$itemId) return null;
        $stmt = $this->pdo->prepare("SELECT set_id FROM module_set_items WHERE item_id = ?");
        $stmt->execute([$itemId]);
        return $stmt->fetchColumn();
    }

    public function saveItemSet($itemId, $setId) {
        $this->pdo->prepare("DELETE FROM module_set_items WHERE item_id = ?")->execute([$itemId]);
        if ($setId > 0) {
            $this->pdo->prepare("INSERT INTO module_set_items (set_id, item_id) VALUES (?, ?)")->execute([$setId, $itemId]);
        }
    }

    public function getSetProgress($setId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM module_set_items WHERE set_id = ?");
        $stmt->execute([$setId]);
        $count = $stmt->fetchColumn();
        
        $set = $this->getSet($setId);
        $target = $set['target_count'] ?: 1;
        
        return [
            'count' => (int)$count,
            'target' => (int)$target,
            'percent' => min(100, round(($count / $target) * 100))
        ];
    }
}
