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
    $searchTerm = '%' . $q . '%';

    $hasIsPrimary = AppConfig::get('media_has_is_primary', '0') === '1';
    $orderClause = $hasIsPrimary ? "m.is_primary DESC, m.upload_date ASC" : "m.upload_date ASC";

    // 1. Search items by title or reg_number
    $sqlItems = "
        SELECT i.id, i.title, i.reg_number,
            (SELECT m.file_path FROM media m WHERE m.item_id = i.id AND m.media_type = 'image' ORDER BY {$orderClause} LIMIT 1) as preview_file_path
        FROM items i
        WHERE i.is_visible = 1 AND (i.title LIKE :q1 OR i.reg_number LIKE :q2)
        ORDER BY i.id DESC LIMIT 5
    ";
    $stmt = $pdo->prepare($sqlItems);
    $stmt->execute(['q1' => $searchTerm, 'q2' => $searchTerm]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedResults = [];
    foreach ($items as $item) {
        $previewUrl = '';
        if (!empty($item['preview_file_path'])) {
            $previewUrl = SITE_URL . '/uploads/display/' . rawurlencode($item['preview_file_path']);
        }
        $formattedResults[] = [
            'id' => 'item_' . $item['id'],
            'title' => $item['title'],
            'reg_number' => $item['reg_number'],
            'image_url' => $previewUrl,
            'url' => SITE_URL . '/item/' . $item['id']
        ];
    }

    // 2. Search Blog Posts (if table exists)
    try {
        $sqlBlog = "SELECT id, title, slug, featured_image FROM blog_posts WHERE status = 'published' AND (title LIKE :q1 OR content LIKE :q2) LIMIT 3";
        $stmt = $pdo->prepare($sqlBlog);
        $stmt->execute(['q1' => $searchTerm, 'q2' => $searchTerm]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $post) {
            $formattedResults[] = [
                'id' => 'blog_' . $post['id'],
                'title' => $post['title'],
                'reg_number' => 'Blog Post',
                'image_url' => $post['featured_image'] ? SITE_URL . '/uploads/' . rawurlencode($post['featured_image']) : '',
                'url' => SITE_URL . '/blog/' . $post['slug']
            ];
        }
    } catch (Exception $e) {}

    // 3. Search People (if table exists)
    try {
        $sqlPeople = "SELECT id, name, slug, profile_image FROM people WHERE is_public = 1 AND (name LIKE :q1 OR short_description LIKE :q2) LIMIT 3";
        $stmt = $pdo->prepare($sqlPeople);
        $stmt->execute(['q1' => $searchTerm, 'q2' => $searchTerm]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $person) {
            $formattedResults[] = [
                'id' => 'person_' . $person['id'],
                'title' => $person['name'],
                'reg_number' => 'Biography',
                'image_url' => $person['profile_image'] ? SITE_URL . '/uploads/display/' . rawurlencode($person['profile_image']) : '',
                'url' => SITE_URL . '/person/' . $person['slug']
            ];
        }
    } catch (Exception $e) {}

    header('Content-Type: application/json');
    echo json_encode($formattedResults);

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred.']);
}
