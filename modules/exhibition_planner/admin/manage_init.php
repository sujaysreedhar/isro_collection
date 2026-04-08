<?php
// modules/exhibition_planner/admin/manage_init.php

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $title = trim($_POST['title'] ?? '');
    $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower($title));
    $desc = trim($_POST['description'] ?? '');
    
    if ($id > 0) {
        $stmt = $this->pdo->prepare("UPDATE module_exhibition_pages SET title = ?, slug = ?, description = ? WHERE id = ?");
        $stmt->execute([$title, $slug, $desc, $id]);
    } else {
        $stmt = $this->pdo->prepare("INSERT INTO module_exhibition_pages (title, slug, description) VALUES (?, ?, ?)");
        $stmt->execute([$title, $slug, $desc]);
        $id = $this->pdo->lastInsertId();
    }
    header("Location: module_page.php?m=exhibition_planner&action=edit&id=$id&msg=saved");
    exit;
}

if ($id > 0) {
    // Handle adding item
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item_id'])) {
        $itemId = (int)$_POST['add_item_id'];
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO module_exhibition_items (page_id, item_id) VALUES (?, ?)");
        $stmt->execute([$id, $itemId]);
        header("Location: module_page.php?m=exhibition_planner&action=edit&id=$id&msg=item_added");
        exit;
    }
    
    // Handle removing item
    if (isset($_GET['remove_item_id'])) {
        $itemId = (int)$_GET['remove_item_id'];
        $stmt = $this->pdo->prepare("DELETE FROM module_exhibition_items WHERE page_id = ? AND item_id = ?");
        $stmt->execute([$id, $itemId]);
        header("Location: module_page.php?m=exhibition_planner&action=edit&id=$id&msg=item_removed");
        exit;
    }
}
