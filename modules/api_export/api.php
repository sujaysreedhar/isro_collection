<?php
// api.php - Public REST API endpoint
require_once __DIR__ . '/../../config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// API Authentication Check
function getRequestApiKey() {
    $key = $_SERVER['HTTP_X_API_KEY'] ?? null;
    if (!$key && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $key = $matches[1];
        }
    }
    return $key ?: ($_GET['api_key'] ?? null);
}

$apiKey = getRequestApiKey();
$isAuthorized = false;

// 1. Check for API key
if ($apiKey) {
    $authStmt = $pdo->prepare("SELECT 1 FROM api_keys WHERE key_value = ? AND is_active = 1");
    $authStmt->execute([$apiKey]);
    if ($authStmt->fetch()) {
        $isAuthorized = true;
    }
}

// 2. Fallback: Check if user is logged in as Admin (allowing internal AJAX calls)
if (!$isAuthorized && isset($_SESSION['admin_id'])) {
    $isAuthorized = true;
}

if (!$isAuthorized) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid or missing API key.'], JSON_PRETTY_PRINT);
    exit;
}

$action = $_GET['action'] ?? 'items';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20))); // Max 100 per page
$offset = ($page - 1) * $limit;

$response = [
    'status' => 'success',
    'endpoint' => $action,
    'page' => $page,
    'limit' => $limit,
    'data' => null
];

try {
    if ($action === 'items') {
        // Build base query for items
        $showHidden = isset($_SESSION['admin_id']) && ($_GET['include_hidden'] ?? '0') === '1';
        $where = [$showHidden ? "1=1" : "is_visible = 1"];
        $params = [];

        // Filters
        if (!empty($_GET['category'])) {
            $where[] = "category_id = ?";
            $params[] = (int)$_GET['category'];
        }
        if (!empty($_GET['year'])) {
            $where[] = "? BETWEEN year_start AND year_end";
            $params[] = (int)$_GET['year'];
        }
        if (!empty($_GET['search']) || !empty($_GET['q'])) {
            $searchTerm = $_GET['search'] ?? $_GET['q'];
            $where[] = "MATCH(title, physical_description, historical_context) AGAINST(? IN BOOLEAN MODE)";
            // Basic boolean mode mapping (very simplified)
            $params[] = '*' . trim($searchTerm) . '*'; 
        }

        $whereClause = implode(' AND ', $where);

        // Count total
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE $whereClause");
        $countStmt->execute($params);
        $response['total'] = (int)$countStmt->fetchColumn();

        // Fetch data
        $paramTypes = [];
        $sql = "SELECT id, reg_number, title, production_date, year_start, year_end, material, physical_description FROM items WHERE $whereClause ORDER BY id DESC LIMIT $limit OFFSET $offset";
        
        // Emulate prepares for LIMIT issue, or just use string interp for LIMIT/OFFSET
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Basic clean up
        foreach ($items as &$item) {
            $item['physical_description'] = strip_tags($item['physical_description']);
            $item['url'] = SITE_URL . '/item/' . $item['id'];
        }
        $response['data'] = $items;
        
        $response['total_pages'] = ceil($response['total'] / $limit);

    } elseif ($action === 'item' && !empty($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT id, reg_number, title, production_date, year_start, year_end, material, physical_description, historical_context, credit_line FROM items WHERE id = ? AND is_visible = 1");
        $stmt->execute([(int)$_GET['id']]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            $item['physical_description'] = strip_tags($item['physical_description']);
            $item['historical_context'] = strip_tags($item['historical_context']);
            
            // Get media
            $mStmt = $pdo->prepare("SELECT file_path, media_type, caption FROM media WHERE item_id = ? ORDER BY is_primary DESC, id ASC");
            $mStmt->execute([$item['id']]);
            $item['media'] = $mStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($item['media'] as &$m) {
                if ($m['media_type'] === 'image') {
                    $m['url_thumbs'] = SITE_URL . '/uploads/thumbs/' . $m['file_path'];
                    $m['url_display'] = SITE_URL . '/uploads/display/' . $m['file_path'];
                } elseif ($m['media_type'] === 'pdf') {
                    $m['url'] = SITE_URL . '/uploads/pdfs/' . $m['file_path'];
                }
            }
            
            $response['data'] = $item;
        } else {
            http_response_code(404);
            $response['status'] = 'error';
            $response['message'] = 'Item not found';
        }
    } else {
        http_response_code(400);
        $response['status'] = 'error';
        $response['message'] = 'Unknown action or missing parameters.';
    }

} catch (Exception $e) {
    http_response_code(500);
    $response['status'] = 'error';
    $response['message'] = 'Server exception: ' . $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
