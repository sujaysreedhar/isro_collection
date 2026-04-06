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

    $params = [];
    if (str_starts_with($searchTerm, '#')) {
        $tagQuery = ltrim($searchTerm, '#');
        $where = "i.id IN (SELECT it.item_id FROM item_tag it JOIN tags t ON it.tag_id = t.id WHERE t.name LIKE ?)";
        $params[] = '%' . $tagQuery . '%';
    } else {
        $where = "(i.title LIKE ? OR i.reg_number LIKE ?)";
        $params[] = '%' . $searchTerm . '%';
        $params[] = '%' . $searchTerm . '%';
    }
    
    if ($excludeColId > 0) {
        $where .= " AND i.id NOT IN (SELECT item_id FROM collection_items WHERE collection_id = ?)";
        $params[] = $excludeColId;
    }

    // Search by title, reg number, or tag
    $stmt = $this->pdo->prepare("SELECT i.id, i.reg_number, i.title FROM items i WHERE $where ORDER BY i.id DESC LIMIT $limit");
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
        global $storage;
        $title = trim($_POST['title']);
        $slug = trim($_POST['slug']);
        $description = trim($_POST['description']);
        $is_public = isset($_POST['is_public']) ? 1 : 0;

        $cover_image = null;
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['cover_image'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            
            if (in_array($mime, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
                $ext = match($mime) {
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/gif'  => 'gif',
                    'image/webp' => 'webp',
                    default      => 'jpg'
                };
                $baseName = 'col_' . substr(md5(uniqid('', true)), 0, 10) . '.' . $ext;
                
                if (isset($storage) && $storage) {
                    $storage->put('display/' . $baseName, $file['tmp_name'], $mime);
                } else {
                    $destDir = __DIR__ . '/../../uploads/display';
                    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
                    move_uploaded_file($file['tmp_name'], $destDir . '/' . $baseName);
                }
                $cover_image = $baseName;
            }
        }

        if ($colId > 0) {
            if ($cover_image) {
                $stmt = $this->pdo->prepare("UPDATE collections SET title = ?, slug = ?, description = ?, is_public = ?, cover_image = ? WHERE id = ?");
                $stmt->execute([$title, $slug, $description, $is_public, $cover_image, $colId]);
            } else {
                $stmt = $this->pdo->prepare("UPDATE collections SET title = ?, slug = ?, description = ?, is_public = ? WHERE id = ?");
                $stmt->execute([$title, $slug, $description, $is_public, $colId]);
            }
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO collections (title, slug, description, is_public, cover_image) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $description, $is_public, $cover_image]);
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
