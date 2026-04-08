<?php
// modules/grading_certs/module.php

class GradingCertsModule extends BaseModule {

    public function boot() {
        // 1. Inject Fields into Item Editor
        HookRegistry::addAction('admin_item_edit_after_fields', function($id, $item) {
            $grading = $this->getItemGrading($id);
            require_once __DIR__ . '/views/admin_fields.php';
        }, 11, 2); // Priority 11 to show after ItemSpecs

        // 2. Save Grading when Item is Saved
        HookRegistry::addAction('item_saved', function($id) {
            $this->saveItemGrading($id, $_POST['item_grading'] ?? []);
        });

        // 3. Display Grading on Frontend
        HookRegistry::addAction('item_after_content', function($item) {
            $grading = $this->getItemGrading($item['id']);
            if (!empty($grading) && !empty($grading['grade'])) {
                require_once __DIR__ . '/views/frontend_display.php';
            }
        });
    }

    public function activate() {
        ModuleDB::createTable($this->pdo, 'module_grading', "
            item_id INT PRIMARY KEY,
            grade VARCHAR(50),
            cert_number VARCHAR(100),
            cert_authority VARCHAR(100),
            cert_date DATE,
            cert_image VARCHAR(255),
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ");
    }

    public function uninstall() {
        $this->pdo->exec("DROP TABLE IF EXISTS module_grading");
    }

    private function getItemGrading($itemId) {
        if (!$itemId) return [];
        $stmt = $this->pdo->prepare("SELECT * FROM module_grading WHERE item_id = ?");
        $stmt->execute([$itemId]);
        return $stmt->fetch() ?: [];
    }

    private function saveItemGrading($itemId, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO module_grading (item_id, grade, cert_number, cert_authority, cert_date)
            VALUES (:id, :g, :n, :a, :d)
            ON DUPLICATE KEY UPDATE 
                grade = VALUES(grade),
                cert_number = VALUES(cert_number),
                cert_authority = VALUES(cert_authority),
                cert_date = VALUES(cert_date)
        ");
        $stmt->execute([
            ':id' => $itemId,
            ':g'  => trim($data['grade'] ?? ''),
            ':n'  => trim($data['cert_number'] ?? ''),
            ':a'  => trim($data['cert_authority'] ?? ''),
            ':d'  => !empty($data['cert_date']) ? $data['cert_date'] : null
        ]);
        
        // Note: Image handling (uploading a certificate scan) would ideally use MediaProcessor 
        // but for now we'll focus on the data. For 1.0, we can use a URL or a simple upload.
    }
}
