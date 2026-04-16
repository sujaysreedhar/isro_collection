<?php
// modules/set_manager/frontend_controller.php

class SetManagerFrontend {
    private $module;
    private $pdo;

    public function __construct($module) {
        $this->module = $module;
        $this->pdo = $module->getPdo();
    }

    public function listSets() {
        $sets = $this->module->getAllSets(true); // only public
        $pageTitle = "Collection Checklists";
        $pageDescription = "Track your collection progress with our curated checklists and sets.";
        
        require_once __DIR__ . '/views/frontend_list.php';
    }

    public function viewSet($slug) {
        $set = $this->module->getSetBySlug($slug);
        if (!$set) {
            http_response_code(404);
            require_once ThemeManager::getTemplatePath('404.php');
            return;
        }

        $structure = $this->module->getSetStructure($set['id']);
        $progress = $this->module->getSetProgress($set['id']);
        
        // Get items already in this set
        $stmt = $this->pdo->prepare("
            SELECT i.*, msi.structure_id 
            FROM items i
            JOIN module_set_items msi ON i.id = msi.item_id
            WHERE msi.set_id = ?
        ");
        $stmt->execute([$set['id']]);
        $ownedItems = $stmt->fetchAll();
        
        // Map owned items to structure
        $ownedByStructure = [];
        foreach ($ownedItems as $item) {
            if ($item['structure_id']) {
                $ownedByStructure[$item['structure_id']] = $item;
            } else {
                $ownedByStructure['unmapped'][] = $item;
            }
        }

        $pageTitle = $set['name'] . " Checklist";
        $pageDescription = $set['description'];
        
        require_once __DIR__ . '/views/frontend_detail.php';
    }
}
