<?php
/**
 * admin/ajax.php
 * 
 * Handles all AJAX requests for the Admin Panel.
 * Returns JSON responses only.
 */
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

// Validate we have an action parameter
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    
    // --- DataTables Server-Side Processing ---
    case 'datatable_items':
        $draw   = (int)($_GET['draw'] ?? 1);
        $start  = (int)($_GET['start'] ?? 0);
        $length = (int)($_GET['length'] ?? 20);
        $search = trim($_GET['search']['value'] ?? '');
        
        // Total count of items (unfiltered)
        $totalRecords = (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
        
        // Base query
        $sql = "
            SELECT i.id, i.reg_number, i.title, i.production_date, i.is_visible, c.name AS category_name,
                   (SELECT COUNT(*) FROM media m WHERE m.item_id = i.id) AS media_count
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
        ";
        $params = [];
        $totalFiltered = $totalRecords;
        
        if ($search !== '') {
            $sql .= " WHERE (i.title LIKE :search OR i.reg_number LIKE :search OR c.name LIKE :search)";
            $params[':search'] = '%' . $search . '%';
            
            // Count filtered records
            $countSql = "SELECT COUNT(*) FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE (i.title LIKE :search OR i.reg_number LIKE :search OR c.name LIKE :search)";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute([':search' => '%' . $search . '%']);
            $totalFiltered = (int)$countStmt->fetchColumn();
        }
        
        $sql .= " ORDER BY i.id DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        
        // Format data for DataTables
        $data = array_map(function($item) {
            $visClass = $item['is_visible'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500';
            $visText  = $item['is_visible'] ? 'Visible' : 'Hidden';
            $toggleLabel = $item['is_visible'] ? 'Hide' : 'Show';
            $editUrl  = SITE_URL . '/admin/edit_item.php?id=' . $item['id'];

            return [
                "<span class='text-gray-400 font-mono text-xs'>{$item['reg_number']}</span>",
                "<span class='font-medium text-gray-900'>" . htmlspecialchars($item['title']) . "</span>",
                "<span class='text-sm text-gray-600'>" . htmlspecialchars($item['category_name'] ?? '—') . "</span>",
                "<span class='text-xs text-gray-500'>" . htmlspecialchars($item['production_date'] ?? '—') . "</span>",
                "<span class='inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {$visClass}'>{$visText}</span>",
                "<span class='text-gray-500'>{$item['media_count']}</span>",
                // Actions cell
                "<div class='flex items-center justify-end gap-3 text-sm font-medium'>
                    <a href='{$editUrl}' class='text-blue-600 hover:text-blue-800'>Edit</a>
                    <button onclick='toggleVisibility({$item['id']}, this)' data-visible='{$item['is_visible']}' class='text-yellow-600 hover:text-yellow-800'>{$toggleLabel}</button>
                    <button onclick='confirmDelete({$item['id']})' class='text-red-600 hover:text-red-800'>Delete</button>
                </div>"
            ];
        }, $items);
        
        echo json_encode([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data'            => $data,
        ]);
        break;
    
    // --- AJAX Toggle Visibility ---
    case 'toggle_visibility':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success' => false]); exit; }
        
        // Fetch current state and flip it
        $current = (int)$pdo->prepare("SELECT is_visible FROM items WHERE id = ?")->execute([$id]) && ($row = $pdo->query("SELECT is_visible FROM items WHERE id = {$id}")->fetchColumn());
        $stmt = $pdo->prepare("SELECT is_visible FROM items WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $current = (int)$stmt->fetchColumn();
        $newState = $current === 1 ? 0 : 1;
        
        $pdo->prepare("UPDATE items SET is_visible = :v WHERE id = :id")
            ->execute([':v' => $newState, ':id' => $id]);
        
        echo json_encode(['success' => true, 'is_visible' => $newState]);
        break;
    
    // --- AJAX Bulk Delete ---
    case 'bulk_delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        $ids = $_POST['ids'] ?? [];
        
        if (!is_array($ids) || empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No items selected.']);
            exit;
        }
        
        // Sanitize every ID
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, fn($id) => $id > 0);
        
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'Invalid IDs provided.']);
            exit;
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM items WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        
        echo json_encode(['success' => true, 'deleted' => count($ids)]);
        break;
    
    // --- Items Search (for Select2 in Narrative editor) ---
    case 'search_items':
        $q = trim($_GET['q'] ?? '');
        $sql = "SELECT id, title as text FROM items";
        $params = [];
        if ($q !== '') {
            $sql .= " WHERE title LIKE :q OR reg_number LIKE :q";
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= " ORDER BY title ASC LIMIT 30";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['results' => $stmt->fetchAll()]);
        break;
    
    // --- Narratives Search (for Select2) ---
    case 'search_narratives':
        $q = trim($_GET['q'] ?? '');
        $sql = "SELECT id, title as text FROM narratives";
        $params = [];
        if ($q !== '') {
            $sql .= " WHERE title LIKE :q";
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= " ORDER BY title ASC LIMIT 30";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['results' => $stmt->fetchAll()]);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action.']);
}
