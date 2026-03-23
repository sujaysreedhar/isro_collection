<?php
// modules/people/admin_people_logic.php

$action = $_GET['action'] ?? 'list';
$personId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'search_items_ajax') {
    $q = $_GET['q'] ?? '';
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
    $excludePersonId = (int)($_GET['exclude_person'] ?? 0);
    
    $searchTerm = trim($q);
    if (empty($searchTerm)) {
        header('Content-Type: application/json');
        echo json_encode(['data' => []]);
        exit;
    }

    $where = "(title LIKE ? OR reg_number LIKE ?)";
    $params = ['%' . $searchTerm . '%', '%' . $searchTerm . '%'];
    
    if ($excludePersonId > 0) {
        $where .= " AND id NOT IN (SELECT item_id FROM item_people WHERE person_id = ?)";
        $params[] = $excludePersonId;
    }

    $stmt = $this->pdo->prepare("SELECT id, reg_number, title FROM items WHERE $where ORDER BY id DESC LIMIT $limit");
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode(['data' => $items]);
    exit;
}

if ($action === 'link_item_ajax') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        header('Content-Type: application/json', true, 403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    if ($personId <= 0) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['error' => 'Invalid Person ID']);
        exit;
    }
    $itemId = (int)$_POST['item_id'];
    $role = 'Associated'; 
    
    $stmt = $this->pdo->prepare("INSERT IGNORE INTO item_people (person_id, item_id, role) VALUES (?, ?, ?)");
    $stmt->execute([$personId, $itemId, $role]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'webhook_get_linked_items_ajax' || $action === 'get_linked_items_ajax') {
    if ($personId <= 0) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['error' => 'Invalid Person ID']);
        exit;
    }
    $stmt = $this->pdo->prepare("SELECT i.id, i.title, i.reg_number, ip.role FROM items i JOIN item_people ip ON i.id = ip.item_id WHERE ip.person_id = ? ORDER BY i.id DESC");
    $stmt->execute([$personId]);
    $items = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode(['data' => $items]);
    exit;
}

if ($action === 'unlink_item_ajax') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        header('Content-Type: application/json', true, 403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    $itemId = (int)$_POST['item_id'];
    $stmt = $this->pdo->prepare("DELETE FROM item_people WHERE person_id = ? AND item_id = ?");
    $stmt->execute([$personId, $itemId]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_person'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) die('Invalid CSRF token.');

    global $storage;
    $name = trim($_POST['name']);
    $slug = trim($_POST['slug']);
    $birth_date = trim($_POST['birth_date']);
    $death_date = trim($_POST['death_date']);
    $short_description = trim($_POST['short_description']);
    $biography = trim($_POST['biography']);
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    // Handle Infobox Data (JSON)
    $infoKeys = $_POST['info_key'] ?? [];
    $infoVals = $_POST['info_val'] ?? [];
    $infobox = [];
    foreach ($infoKeys as $idx => $key) {
        $key = trim($key);
        $val = trim($infoVals[$idx] ?? '');
        if ($key !== '') {
            $infobox[] = ['label' => $key, 'value' => $val];
        }
    }
    $infobox_json = json_encode($infobox);

    // Handle Profile Image
    $profile_image = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_image'];
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
            $baseName = 'person_' . substr(md5(uniqid('', true)), 0, 10) . '.' . $ext;
            
            if (isset($storage) && $storage) {
                // Using display folder for profile pics too for simplicity in modules
                $storage->put('display/' . $baseName, $file['tmp_name'], $mime);
            } else {
                $destDir = __DIR__ . '/../../uploads/display';
                if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
                move_uploaded_file($file['tmp_name'], $destDir . '/' . $baseName);
            }
            $profile_image = $baseName;
        }
    }

    if ($personId > 0) {
        $sql = "UPDATE people SET name = ?, slug = ?, birth_date = ?, death_date = ?, short_description = ?, biography = ?, infobox_data = ?, is_public = ?";
        $params = [$name, $slug, $birth_date, $death_date, $short_description, $biography, $infobox_json, $is_public];
        if ($profile_image) {
            $sql .= ", profile_image = ?";
            $params[] = $profile_image;
        }
        $sql .= " WHERE id = ?";
        $params[] = $personId;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $this->pdo->prepare("INSERT INTO people (name, slug, birth_date, death_date, short_description, biography, infobox_data, profile_image, is_public) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $birth_date, $death_date, $short_description, $biography, $infobox_json, $profile_image, $is_public]);
        $personId = $this->pdo->lastInsertId();
    }

    header("Location: " . SITE_URL . "/admin/module_page.php?m=people&action=edit&id=" . $personId . "&msg=saved");
    exit;
}

if ($action === 'delete' && $personId > 0) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? null)) die('Invalid CSRF token.');
    
    // First unlink from items
    $this->pdo->prepare("DELETE FROM item_people WHERE person_id = ?")->execute([$personId]);
    // Then delete person
    $this->pdo->prepare("DELETE FROM people WHERE id = ?")->execute([$personId]);
    
    header("Location: " . SITE_URL . "/admin/module_page.php?m=people&msg=deleted");
    exit;
}
