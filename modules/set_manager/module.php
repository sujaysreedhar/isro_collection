<?php
// modules/set_manager/module.php

class SetManagerModule extends BaseModule {

    public function boot() {
        // 1. Admin Hooks
        HookRegistry::addFilter('admin_sidebar_links', function($sections) {
            $sections['catalog']['links']['set_manager'] = [
                'url'   => SITE_URL . '/admin/module_page.php?m=set_manager',
                'label' => 'Sets / Checklists',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />'
            ];
            return $sections;
        });

        HookRegistry::addAction("admin_init_{$this->slug}", function() {
            $this->handleAdminSubmit();
        });

        HookRegistry::addAction('admin_page_set_manager', function() {
            require_once __DIR__ . '/admin_page.php';
        });

        HookRegistry::addAction('admin_item_edit_after_fields', function($id, $item) {
            $sets = $this->getAllSets();
            $setDetails = $this->getItemSetDetails($id);
            require_once __DIR__ . '/views/admin_fields.php';
        }, 15, 2);

        HookRegistry::addAction('item_saved', function($id) {
            if (isset($_POST['set_manager_id'])) {
                $this->saveItemSet($id, (int)$_POST['set_manager_id'], (int)($_POST['set_manager_structure_id'] ?? 0));
            }
        });

        // 2. Frontend Hooks & Routing
        HookRegistry::addFilter('route_request', function($handled, $uri) {
            if ($uri === 'checklists') {
                require_once __DIR__ . '/frontend_controller.php';
                (new SetManagerFrontend($this))->listSets();
                return true;
            }
            if (preg_match('#^checklist/([a-zA-Z0-9_-]+)/?$#', $uri, $matches)) {
                require_once __DIR__ . '/frontend_controller.php';
                (new SetManagerFrontend($this))->viewSet($matches[1]);
                return true;
            }
            return $handled;
        }, 10, 2);

        // AJAX Handlers
        HookRegistry::addFilter('admin_ajax_get_set_structure', function($handled) {
            $setId = (int)($_GET['set_id'] ?? 0);
            $structure = $this->getSetStructure($setId);
            echo json_encode(['success' => true, 'structure' => $structure]);
            return true;
        });

        HookRegistry::addAction('item_after_content', function($item) {
            $setDetails = $this->getItemSetDetails($item['id']);
            if ($setDetails) {
                $set = $this->getSet($setDetails['set_id']);
                $progress = $this->getSetProgress($setDetails['set_id']);
                require_once __DIR__ . '/views/frontend_display.php';
            }
        });
    }

    public function activate() {
        ModuleDB::createTable($this->pdo, 'module_sets', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE,
            description TEXT,
            banner_image VARCHAR(255),
            target_count INT DEFAULT 0,
            is_public TINYINT(1) DEFAULT 1,
            is_featured TINYINT(1) DEFAULT 0,
            query_json TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ");

        ModuleDB::createTable($this->pdo, 'module_set_items', "
            set_id INT NOT NULL,
            item_id INT NOT NULL,
            structure_id INT NULL,
            PRIMARY KEY (set_id, item_id),
            FOREIGN KEY (set_id) REFERENCES module_sets(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ");

        ModuleDB::createTable($this->pdo, 'module_set_structure', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            set_id INT NOT NULL,
            label VARCHAR(255) NOT NULL,
            description TEXT,
            sort_order INT DEFAULT 0,
            FOREIGN KEY (set_id) REFERENCES module_sets(id) ON DELETE CASCADE
        ");
    }

    public function getAllSets($onlyPublic = false) {
        $where = $onlyPublic ? "WHERE is_public = 1" : "";
        return $this->pdo->query("SELECT * FROM module_sets $where ORDER BY name ASC")->fetchAll();
    }

    public function getFeaturedSets() {
        return $this->pdo->query("SELECT * FROM module_sets WHERE is_featured = 1 AND is_public = 1 ORDER BY name ASC")->fetchAll();
    }

    public function getSet($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM module_sets WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getSetBySlug($slug) {
        $stmt = $this->pdo->prepare("SELECT * FROM module_sets WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    public function getSetStructure($setId) {
        $stmt = $this->pdo->prepare("SELECT * FROM module_set_structure WHERE set_id = ? ORDER BY sort_order ASC, label ASC");
        $stmt->execute([$setId]);
        return $stmt->fetchAll();
    }

    public function getItemSetDetails($itemId) {
        if (!$itemId) return null;
        $stmt = $this->pdo->prepare("SELECT set_id, structure_id FROM module_set_items WHERE item_id = ?");
        $stmt->execute([$itemId]);
        return $stmt->fetch();
    }

    public function saveItemSet($itemId, $setId, $structureId = null) {
        $this->pdo->prepare("DELETE FROM module_set_items WHERE item_id = ?")->execute([$itemId]);
        if ($setId > 0) {
            $this->pdo->prepare("INSERT INTO module_set_items (set_id, item_id, structure_id) VALUES (?, ?, ?)")->execute([$setId, $itemId, $structureId ?: null]);
        }
    }

    public function getSetProgress($setId) {
        // Count actual items
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM module_set_items WHERE set_id = ?");
        $stmt->execute([$setId]);
        $count = $stmt->fetchColumn();
        
        $set = $this->getSet($setId);
        
        // Target can be manual or based on structure count
        $structCount = $this->pdo->query("SELECT COUNT(*) FROM module_set_structure WHERE set_id = " . (int)$setId)->fetchColumn();
        $target = $structCount > 0 ? $structCount : ($set['target_count'] ?: 1);
        
        return [
            'count' => (int)$count,
            'target' => (int)$target,
            'percent' => min(100, round(($count / $target) * 100))
        ];
    }

    public function handleAdminSubmit() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['action'])) return;

        $setId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $action = $_GET['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            if (!$slug) $slug = strtolower(str_replace(' ', '-', $name));
            
            $desc = trim($_POST['description'] ?? '');
            $banner = trim($_POST['banner_image'] ?? '');
            $target = (int)($_POST['target_count'] ?? 0);
            $is_public = isset($_POST['is_public']) ? 1 : 0;
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;

            if ($setId > 0) {
                $stmt = $this->pdo->prepare("UPDATE module_sets SET name = ?, slug = ?, description = ?, banner_image = ?, target_count = ?, is_public = ?, is_featured = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $desc, $banner, $target, $is_public, $is_featured, $setId]);
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO module_sets (name, slug, description, banner_image, target_count, is_public, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $desc, $banner, $target, $is_public, $is_featured]);
                $setId = $this->pdo->lastInsertId();
            }

            // Handle Structure Updates
            if (isset($_POST['structure'])) {
                $this->pdo->prepare("DELETE FROM module_set_structure WHERE set_id = ?")->execute([$setId]);
                $structStmt = $this->pdo->prepare("INSERT INTO module_set_structure (set_id, label, description, sort_order) VALUES (?, ?, ?, ?)");
                foreach ($_POST['structure'] as $index => $row) {
                    if (trim($row['label'] ?? '')) {
                        $structStmt->execute([$setId, $row['label'], $row['description'] ?? '', (int)($row['sort_order'] ?? $index)]);
                    }
                }
            }

            header("Location: " . SITE_URL . "/admin/module_page.php?m=set_manager&action=edit&id=$setId&msg=saved");
            exit;
        }

        if ($action === 'delete' && $setId > 0) {
            $this->pdo->prepare("DELETE FROM module_sets WHERE id = ?")->execute([$setId]);
            header("Location: " . SITE_URL . "/admin/module_page.php?m=set_manager&msg=deleted");
            exit;
        }
    }

    public function getPdo() {
        return $this->pdo;
    }
}
