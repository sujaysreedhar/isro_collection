<?php
// modules/item_specs/module.php

class ItemSpecsModule extends BaseModule {

    public function boot() {
        // 1. Inject Fields into Item Editor
        HookRegistry::addAction('admin_item_edit_after_fields', function($id, $item) {
            $mapping = $this->getSpecsMapping();
            $specs = $this->getItemSpecs($id);
            require_once __DIR__ . '/views/admin_fields.php';
        }, 10, 2);

        // 2. Save Specs when Item is Saved
        HookRegistry::addAction('item_saved', function($id) {
            $this->saveItemSpecs($id, $_POST['item_specs'] ?? []);
        });

        // 3. Display Specs on Frontend
        HookRegistry::addAction('item_after_content', function($item) {
            $specs = $this->getItemSpecs($item['id']);
            if (!empty($specs)) {
                require_once __DIR__ . '/views/frontend_display.php';
            }
        });

        // 4. Admin Settings Menu
        HookRegistry::addFilter('admin_sidebar_links', function($links) {
            $links['system']['links']['item_specs'] = [
                'url' => SITE_URL . '/admin/module_page.php?m=item_specs',
                'label' => 'Item Specs Mapping',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.59c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.295 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l1.295 2.247a1.125 1.125 0 01-1.37.49l-1.218-.456c-.354-.133-.75-.072-1.075.124a6.57 6.57 0 01-.22.127c-.332.183-.582.495-.645.869l-.213 1.281c-.09.543-.56.941-1.11.941h-2.59c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.222-.127c-.324-.196-.72-.257-1.075-.124l-1.217.456a1.125 1.125 0 01-1.37-.49l-1.296-2.247a1.125 1.125 0 01.26-1.431l1.003-.827c.293-.24.438-.613.431-.992a6.718 6.718 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.49l1.217.456c.354.133.75.072 1.075-.124.072-.044.146-.087.22-.127.332-.183.582-.495.645-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />'
            ];
            return $links;
        });

        HookRegistry::addAction('admin_page_item_specs', function() {
            require_once __DIR__ . '/views/admin_settings.php';
        });
    }

    public function activate() {
        // Data table
        ModuleDB::createTable($this->pdo, 'module_item_specs', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            spec_name VARCHAR(100) NOT NULL,
            spec_value VARCHAR(255) NOT NULL,
            UNIQUE KEY (item_id, spec_name),
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ");

        // Configuration table (Isolated settings)
        ModuleDB::createTable($this->pdo, 'module_item_specs_config', "
            category_id INT PRIMARY KEY,
            profile_name ENUM('philately', 'numismatics', 'banknotes', 'postcard') NOT NULL,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        ");
    }

    public function uninstall() {
        $this->pdo->exec("DROP TABLE IF EXISTS module_item_specs_config");
        $this->pdo->exec("DROP TABLE IF EXISTS module_item_specs");
    }

    public function getSpecsMapping() {
        $stmt = $this->pdo->query("SELECT category_id, profile_name FROM module_item_specs_config");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function saveSpecsMapping($mapping) {
        $this->pdo->exec("DELETE FROM module_item_specs_config");
        if (empty($mapping)) return;

        $stmt = $this->pdo->prepare("INSERT INTO module_item_specs_config (category_id, profile_name) VALUES (?, ?)");
        foreach ($mapping as $catId => $profile) {
            if ($profile) {
                $stmt->execute([$catId, $profile]);
            }
        }
    }

    private function getItemSpecs($itemId) {
        if (!$itemId) return [];
        $stmt = $this->pdo->prepare("SELECT spec_name, spec_value FROM module_item_specs WHERE item_id = ?");
        $stmt->execute([$itemId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    private function saveItemSpecs($itemId, $specs) {
        // Clear existing to avoid complexity
        $this->pdo->prepare("DELETE FROM module_item_specs WHERE item_id = ?")->execute([$itemId]);
        
        if (empty($specs)) return;

        $stmt = $this->pdo->prepare("INSERT INTO module_item_specs (item_id, spec_name, spec_value) VALUES (?, ?, ?)");
        foreach ($specs as $name => $value) {
            $value = trim($value);
            if ($value !== '') {
                $stmt->execute([$itemId, $name, $value]);
            }
        }
    }
}
