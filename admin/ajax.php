<?php
/**
 * admin/ajax.php
 * 
 * Handles all AJAX requests for the Admin Panel.
 * Returns JSON responses only.
 */
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

// Central whitelist for settings keys that modules may persist through admin AJAX handlers.
$GLOBALS['admin_settings_whitelist'] = [
    'backup_manager_webhook_key',
];

// Validate we have an action parameter
$action = $_REQUEST['action'] ?? '';

$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

switch ($action) {

    // --- DataTables Server-Side Processing ---
    case 'datatable_items':
        $draw = (int) ($_GET['draw'] ?? 1);
        $start = (int) ($_GET['start'] ?? 0);
        $length = (int) ($_GET['length'] ?? 20);
        $search = trim($_GET['search']['value'] ?? '');

        // Total count of items (unfiltered)
        $totalRecords = (int) $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();

        $categoryId = (int)($_GET['category_id'] ?? 0);

        // Base query
        $sql = "
            SELECT i.id, i.reg_number, i.title, i.production_date, i.is_visible, c.name AS category_name,
                   (SELECT COUNT(*) FROM media m WHERE m.item_id = i.id) AS media_count
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
        ";
        $params = [];
        $totalFiltered = $totalRecords;
        
        $whereClauses = [];
        
        if ($categoryId > 0) {
            $whereClauses[] = "i.id IN (SELECT item_id FROM item_category WHERE category_id = :cat)";
            $params[':cat'] = $categoryId;
        }

        if ($search !== '') {
            $whereClauses[] = "(i.title LIKE :search1 OR i.reg_number LIKE :search2 OR c.name LIKE :search3)";
            $params[':search1'] = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
            $params[':search3'] = '%' . $search . '%';
        }
        
        if (!empty($whereClauses)) {
            $whereSql = " WHERE " . implode(' AND ', $whereClauses);
            $sql .= $whereSql;

            // Count filtered records
            $countSql = "SELECT COUNT(i.id) FROM items i LEFT JOIN categories c ON i.category_id = c.id" . $whereSql;
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $totalFiltered = (int) $countStmt->fetchColumn();
        }

        $columnsMap = [
            1 => 'i.reg_number',
            2 => 'i.title',
            3 => 'c.name',
            4 => 'i.production_date',
            5 => 'i.is_visible'
        ];
        
        $orderColumnIndex = (int) ($_GET['order'][0]['column'] ?? -1);
        
        if (!isset($columnsMap[$orderColumnIndex])) {
            $orderColSql = 'i.id';
            $orderDir = 'DESC';
        } else {
            $orderColSql = $columnsMap[$orderColumnIndex];
            $orderDir = strtolower($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        }

        $sql .= " ORDER BY " . $orderColSql . " " . $orderDir . " LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        // Format data for DataTables
        $data = array_map(function ($item) {
            $visClass = $item['is_visible'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500';
            $visText = $item['is_visible'] ? 'Visible' : 'Hidden';
            $toggleLabel = $item['is_visible'] ? 'Hide' : 'Show';
            $editUrl = SITE_URL . '/admin/edit_item.php?id=' . $item['id'];
            $liveUrl = SITE_URL . '/item/' . $item['id'];

            return [
                "<span class='text-gray-400 font-mono text-xs'>{$item['reg_number']}</span>",
                "<a href='{$liveUrl}' target='_blank' class='font-medium text-gray-900 hover:text-blue-600 hover:underline transition-colors'>" . htmlspecialchars($item['title']) . "</a>",
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
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
        break;

    // --- AJAX Toggle Visibility ---
    case 'toggle_visibility':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }
        if (!verifyCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid item id.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT is_visible FROM items WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $current = $stmt->fetchColumn();

        if ($current === false) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
            exit;
        }

        $newState = ((int) $current) === 1 ? 0 : 1;

        $pdo->prepare("UPDATE items SET is_visible = :v WHERE id = :id")
            ->execute([':v' => $newState, ':id' => $id]);

        echo json_encode(['success' => true, 'is_visible' => $newState]);
        break;

    // --- AJAX Bulk Delete ---
    case 'bulk_delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }
        if (!verifyCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            exit;
        }

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

        // Trigger hook before core deletion logic handles common media and DB records
        if (class_exists('HookRegistry')) {
            HookRegistry::doAction('before_bulk_delete', $ids);
        }

        // 1. Fetch & Delete Media Files
        $mStmt = $pdo->prepare("SELECT file_path, media_type FROM media WHERE item_id IN ({$placeholders})");
        $mStmt->execute($ids);
        foreach ($mStmt->fetchAll() as $file) {
            if ($file['media_type'] === 'image') {
                @unlink(__DIR__ . '/../uploads/originals/' . $file['file_path']);
                @unlink(__DIR__ . '/../uploads/display/' . $file['file_path']);
                @unlink(__DIR__ . '/../uploads/thumbnails/' . $file['file_path']);
            } elseif ($file['media_type'] === 'pdf') {
                @unlink(__DIR__ . '/../uploads/pdfs/' . $file['file_path']);
            }
        }
        $pdo->prepare("DELETE FROM media WHERE item_id IN ({$placeholders})")->execute($ids);


        // 3. Delete Relationships
        $pdo->prepare("DELETE FROM item_category WHERE item_id IN ({$placeholders})")->execute($ids);
        $pdo->prepare("DELETE FROM item_tag WHERE item_id IN ({$placeholders})")->execute($ids);
        $pdo->prepare("DELETE FROM item_narrative WHERE item_id IN ({$placeholders})")->execute($ids);
        $pdo->prepare("DELETE FROM item_related WHERE item_id IN ({$placeholders})")->execute($ids);
        $pdo->prepare("DELETE FROM item_related WHERE related_item_id IN ({$placeholders})")->execute($ids);

        // 4. Finally Delete Items
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
            $sql .= " WHERE title LIKE :q1 OR reg_number LIKE :q2";
            $params[':q1'] = '%' . $q . '%';
            $params[':q2'] = '%' . $q . '%';
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


    // ── Orphaned Media Scanner ───────────────────────────────────────────────
    case 'scan_orphans':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }
        if (!verifyCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            exit;
        }

        $validFiles = [];

        // 1. Media table (originals, display, thumbnails, pdfs)
        $stmt = $pdo->query("SELECT file_path FROM media");
        while ($row = $stmt->fetch()) {
            $path = ltrim(str_replace('\\', '/', $row['file_path']), '/');
            $validFiles['originals/' . $path] = true;
            $validFiles['display/' . $path] = true;
            $validFiles['thumbnails/' . $path] = true;
            $validFiles['pdfs/' . $path] = true;
        }

        // 2. Panoramics
        try {
            $stmt = $pdo->query("SELECT file_path FROM item_panoramics");
            while ($row = $stmt->fetch()) {
                $path = ltrim(str_replace('\\', '/', $row['file_path']), '/');
                $validFiles['panoramics/' . $path] = true;
            }
        } catch (\Exception $e) {
        }

        $uploadsDir = realpath(__DIR__ . '/../uploads');
        $orphans = [];

        if ($uploadsDir && is_dir($uploadsDir)) {
            $dirIterator = new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = new RecursiveIteratorIterator($dirIterator);

            foreach ($iterator as $file) {
                if (!$file->isFile())
                    continue;
                $filename = $file->getFilename();

                // Ignore system files
                if ($filename === 'index.html' || $filename === 'index.php' || strpos($filename, '.') === 0) {
                    continue;
                }

                $absPath = $file->getRealPath();
                $relPath = str_replace('\\', '/', substr($absPath, strlen($uploadsDir) + 1));

                // ONLY scan core media silos. This prevents flagging valid module assets (blog, people, categories) as orphans.
                $isCoreSilo = false;
                foreach (['originals/', 'display/', 'thumbnails/', 'pdfs/', 'panoramics/'] as $prefix) {
                    if (strpos($relPath, $prefix) === 0) {
                        $isCoreSilo = true;
                        break;
                    }
                }

                if (!$isCoreSilo) {
                    continue;
                }

                if (!isset($validFiles[$relPath])) {
                    $bytes = $file->getSize();
                    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
                    $pow = min($pow, count($units) - 1);
                    $formattedSize = round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];

                    $orphans[] = [
                        'path' => $relPath,
                        'size' => $bytes,
                        'size_formatted' => $formattedSize
                    ];
                }
            }
        }

        echo json_encode(['success' => true, 'orphans' => $orphans]);
        break;

    // ── Orphaned Media Deletion ──────────────────────────────────────────────
    case 'delete_orphans':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }
        if (!verifyCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            exit;
        }

        $paths = $_POST['paths'] ?? [];
        if (!is_array($paths) || empty($paths)) {
            echo json_encode(['success' => false, 'message' => 'No files selected']);
            exit;
        }

        $uploadsBase = realpath(__DIR__ . '/../uploads');
        $deleted = 0;

        foreach ($paths as $p) {
            // Prevent directory traversal
            if (strpos($p, '..') !== false)
                continue;

            $target = realpath($uploadsBase . DIRECTORY_SEPARATOR . $p);

            // Strictly assure the resolved path is within uploads directory
            if ($target && strpos($target, $uploadsBase) === 0 && is_file($target)) {
                if (@unlink($target)) {
                    $deleted++;
                }
            }
        }

        echo json_encode(['success' => true, 'deleted' => $deleted, 'message' => "$deleted file(s) deleted."]);
        break;


    default:
        // Give modules a chance to handle their own custom AJAX actions.
        if (class_exists('HookRegistry')) {
            $handled = HookRegistry::applyFilters('admin_ajax_' . $action, false);
            if ($handled) {
                exit; // A module processed the request and echoed its JSON.
            }
        }

        http_response_code(400);
        echo json_encode(['error' => 'Unknown action.']);
}

