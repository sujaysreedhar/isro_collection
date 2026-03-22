<?php
// modules/curated_collections/admin_collections_logic.php

$action = $_GET['action'] ?? 'list';
$colId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'search_ajax') {
    $q = $_GET['q'] ?? '';
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
    $excludeColId = (int)($_GET['exclude_col'] ?? 0);
    
    $searchTerm = trim($q);
    if (empty($searchTerm)) {
        header('Content-Type: application/json');
        echo json_encode(['data' => []]);
        exit;
    }

    $where = "(title LIKE ? OR reg_number LIKE ?)";
    $params = ['%' . $searchTerm . '%', '%' . $searchTerm . '%'];
    
    if ($excludeColId > 0) {
        $where .= " AND id NOT IN (SELECT item_id FROM collection_items WHERE collection_id = ?)";
        $params[] = $excludeColId;
    }

    // Search by title or reg number (admin search)
    $stmt = $this->pdo->prepare("SELECT id, reg_number, title FROM items WHERE $where ORDER BY id DESC LIMIT $limit");
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode(['data' => $items]);
    exit;
}

if ($action === 'add_item_ajax') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        header('Content-Type: application/json', true, 403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    if ($colId <= 0) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['error' => 'Invalid collection ID']);
        exit;
    }
    $itemId = (int)$_POST['item_id'];
    $stmt = $this->pdo->prepare("INSERT IGNORE INTO collection_items (collection_id, item_id, sort_order) SELECT ?, ?, IFNULL(MAX(sort_order)+1, 0) FROM collection_items WHERE collection_id = ?");
    $stmt->execute([$colId, $itemId, $colId]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'remove_item_ajax') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        header('Content-Type: application/json', true, 403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    if ($colId <= 0) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['error' => 'Invalid collection ID']);
        exit;
    }
    $itemId = (int)$_POST['item_id'];
    $stmt = $this->pdo->prepare("DELETE FROM collection_items WHERE collection_id = ? AND item_id = ?");
    $stmt->execute([$colId, $itemId]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'get_collection_items_ajax') {
    if ($colId <= 0) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['error' => 'Invalid collection ID']);
        exit;
    }
    $stmt = $this->pdo->prepare("SELECT i.id, i.title, i.reg_number FROM items i JOIN collection_items ci ON i.id = ci.item_id WHERE ci.collection_id = ? ORDER BY ci.sort_order ASC");
    $stmt->execute([$colId]);
    $items = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode(['data' => $items]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) die('Invalid CSRF token.');

    if (isset($_POST['save_collection'])) {
        $title = trim($_POST['title']);
        $slug = trim($_POST['slug']);
        $description = trim($_POST['description']);
        $is_public = isset($_POST['is_public']) ? 1 : 0;

        if ($colId > 0) {
            $stmt = $this->pdo->prepare("UPDATE collections SET title = ?, slug = ?, description = ?, is_public = ? WHERE id = ?");
            $stmt->execute([$title, $slug, $description, $is_public, $colId]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO collections (title, slug, description, is_public) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $description, $is_public]);
            $colId = $this->pdo->lastInsertId();
        }
        header("Location: " . SITE_URL . "/admin/module_page.php?m=curated_collections&action=edit&id=" . $colId . "&msg=saved");
        exit;
    }

    if (isset($_POST['add_item'])) {
        $itemId = (int)$_POST['item_id'];
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO collection_items (collection_id, item_id, sort_order) SELECT ?, ?, IFNULL(MAX(sort_order)+1, 0) FROM collection_items WHERE collection_id = ?");
        $stmt->execute([$colId, $itemId, $colId]);
        header("Location: " . SITE_URL . "/admin/module_page.php?m=curated_collections&action=edit&id=" . $colId . "#items");
        exit;
    }

    if (isset($_POST['remove_item'])) {
        $itemId = (int)$_POST['item_id'];
        $stmt = $this->pdo->prepare("DELETE FROM collection_items WHERE collection_id = ? AND item_id = ?");
        $stmt->execute([$colId, $itemId]);
        header("Location: " . SITE_URL . "/admin/module_page.php?m=curated_collections&action=edit&id=" . $colId . "#items");
        exit;
    }
}
