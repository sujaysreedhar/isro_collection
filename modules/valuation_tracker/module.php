<?php
// modules/valuation_tracker/module.php

class ValuationTrackerModule extends BaseModule {

    public function boot() {
        // 1. Inject Fields into Item Editor (Admin Only)
        HookRegistry::addAction('admin_item_edit_after_fields', function($id, $item) {
            $valuation = $this->getValuation($id);
            require_once __DIR__ . '/views/admin_fields.php';
        }, 12, 2);

        // 2. Save Valuation
        HookRegistry::addAction('item_saved', function($id) {
            $this->saveValuation($id, $_POST['item_valuation'] ?? []);
        });

        // 3. Admin Menu: Wealth Report
        HookRegistry::addFilter('admin_sidebar_links', function($links) {
            if (!isset($links['reports'])) {
                $links['reports'] = [
                    'label' => 'Reports',
                    'links' => []
                ];
            }
            $links['reports']['links']['valuation_tracker'] = [
                'url' => SITE_URL . '/admin/module_page.php?m=valuation_tracker',
                'label' => '💰 Wealth Report',
                'icon' => 'bank'
            ];
            return $links;
        });

        HookRegistry::addAction('admin_page_valuation_tracker', function() {
            $report = $this->generateReport();
            require_once __DIR__ . '/views/admin_report.php';
        });
    }

    public function activate() {
        ModuleDB::createTable($this->pdo, 'module_valuations', "
            item_id INT PRIMARY KEY,
            purchase_price DECIMAL(15,2),
            current_value DECIMAL(15,2),
            currency VARCHAR(10) DEFAULT 'INR',
            purchase_date DATE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ");
    }

    public function uninstall() {
        $this->pdo->exec("DROP TABLE IF EXISTS module_valuations");
    }

    private function getValuation($itemId) {
        if (!$itemId) return [];
        $stmt = $this->pdo->prepare("SELECT * FROM module_valuations WHERE item_id = ?");
        $stmt->execute([$itemId]);
        return $stmt->fetch() ?: [];
    }

    private function saveValuation($itemId, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO module_valuations (item_id, purchase_price, current_value, currency, purchase_date)
            VALUES (:id, :pp, :cv, :cur, :pd)
            ON DUPLICATE KEY UPDATE 
                purchase_price = VALUES(purchase_price),
                current_value = VALUES(current_value),
                currency = VALUES(currency),
                purchase_date = VALUES(purchase_date)
        ");
        $stmt->execute([
            ':id'  => $itemId,
            ':pp'  => ($data['purchase_price'] === '') ? null : $data['purchase_price'],
            ':cv'  => ($data['current_value'] === '') ? null : $data['current_value'],
            ':cur' => $data['currency'] ?? 'INR',
            ':pd'  => !empty($data['purchase_date']) ? $data['purchase_date'] : null
        ]);
    }

    private function generateReport() {
        $stmt = $this->pdo->query("
            SELECT i.id, i.reg_number, i.title, v.* 
            FROM items i 
            LEFT JOIN module_valuations v ON i.id = v.item_id 
            ORDER BY v.current_value DESC
        ");
        return $stmt->fetchAll();
    }
}
