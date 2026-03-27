<?php
// ajax_search.php
require_once __DIR__ . '/config/config.php';

$q = trim($_GET['q'] ?? '');

if (empty($q) || strlen($q) < 2) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

try {
    // We want to search items by title or reg_number
    $searchTerm = '%' . $q . '%';
    
    // Optimized query: join with media directly to get the first image
    $sql = "
        SELECT 
            i.id, 
            i.title, 
            i.reg_number,
            (
                SELECT m.file_path 
                FROM media m 
                WHERE m.item_id = i.id AND m.media_type = 'image' 
                ORDER BY m.id ASC 
                LIMIT 1
            ) as preview_file_path
        FROM items i
        WHERE i.is_visible = 1 
          AND (i.title LIKE :q1 OR i.reg_number LIKE :q2)
        ORDER BY i.id DESC
        LIMIT 5
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['q1' => $searchTerm, 'q2' => $searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the results to inject full URLs instead of just paths
    $formattedResults = [];
    foreach ($results as $item) {
        $previewUrl = '';
        if (!empty($item['preview_file_path'])) {
            if (isset($storage)) {
                $previewUrl = $storage->url('display/' . $item['preview_file_path']);
            } else {
                $previewUrl = SITE_URL . '/uploads/display/' . rawurlencode($item['preview_file_path']);
            }
        }
        
        $formattedResults[] = [
            'id' => $item['id'],
            'title' => $item['title'],
            'reg_number' => $item['reg_number'],
            'image_url' => $previewUrl,
            'url' => SITE_URL . '/item/' . $item['id']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($formattedResults);

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred.']);
}
