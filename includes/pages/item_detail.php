<?php
// Include database connection
require_once __DIR__ . '/../../config/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
global $pdo;

// Get and validate the Item ID from the URL (rewritten by .htaccess)
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(404);
    die("Item ID not provided or invalid.");
}

// 1. Fetch Item and Category
$itemStmt = $pdo->prepare("
    SELECT i.*, c.name AS category_name 
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.id = :id
");
$itemStmt->execute([':id' => $id]);
$item = $itemStmt->fetch();

if (!$item) {
    http_response_code(404);
    die("Item not found.");
}

// 1b. Increment View Count (once per session)
try {
    if (!isset($_SESSION['viewed_items'])) $_SESSION['viewed_items'] = [];
    if (!in_array($id, $_SESSION['viewed_items'])) {
        $pdo->prepare("UPDATE items SET view_count = view_count + 1 WHERE id = :id")->execute([':id' => $id]);
        $_SESSION['viewed_items'][] = $id;
    }
} catch (\Exception $e) {}

// 2. Fetch Media for the item — split by type
$hasIsPrimary = false;
try {
    $columnStmt = $pdo->query("SHOW COLUMNS FROM media LIKE 'is_primary'");
    $hasIsPrimary = (bool) $columnStmt->fetch();
} catch (\PDOException) {
    $hasIsPrimary = false;
}

$mediaStmt = $pdo->prepare("SELECT * FROM media WHERE item_id = :id ORDER BY " . ($hasIsPrimary ? "is_primary DESC, " : "") . "upload_date ASC");
$mediaStmt->execute([':id' => $id]);
$mediaItems   = $mediaStmt->fetchAll();
$imageMedia   = array_values(array_filter($mediaItems, fn($m) => ($m['media_type'] ?? 'image') === 'image'));
$pdfMedia     = array_values(array_filter($mediaItems, fn($m) => ($m['media_type'] ?? '') === 'pdf'));
$youtubeMedia = array_values(array_filter($mediaItems, fn($m) => ($m['media_type'] ?? '') === 'youtube'));
$primaryMedia = $imageMedia[0] ?? null;

// 3. Fetch Related Stories (Narratives)
$narrativeStmt = $pdo->prepare("
    SELECT n.id, n.title, n.content_body 
    FROM narratives n
    INNER JOIN item_narrative inv ON n.id = inv.narrative_id
    WHERE inv.item_id = :id
");
$narrativeStmt->execute([':id' => $id]);
$stories = $narrativeStmt->fetchAll();

// 4b. Fetch Tags
$tagStmt = $pdo->prepare("
    SELECT t.id, t.name, t.slug
    FROM tags t
    INNER JOIN item_tag it ON t.id = it.tag_id
    WHERE it.item_id = :id
    ORDER BY t.name ASC
");
$tagStmt->execute([':id' => $id]);
$itemTags = $tagStmt->fetchAll();

// 4. Generate Citation
$currentYear = date('Y');
$citationUrl = SITE_URL . "/item/" . htmlspecialchars($item['id']);
$citation = SITE_TITLE . ". (n.d.). \"" . htmlspecialchars($item['title']) . ".\" Reg: " . htmlspecialchars($item['reg_number']) . ". Retrieved " . date('F j, Y') . ", from " . $citationUrl;

// 5. SEO Engine: OpenGraph + Schema.org JSON-LD
$rawDescription = strip_tags($item['physical_description'] ?? '');
$descriptionSnippet = !empty($rawDescription) ? $rawDescription : 'View this artifact in our collection.';
$itemCategory = $item['category_name'] ?? 'Uncategorized';
$itemReg = $item['reg_number'] ?? 'No Reg';

// Combine for a richer meta description
$fullOgDescription = $descriptionSnippet;
if (strlen($fullOgDescription) < 120) {
    $fullOgDescription .= " — Part of our {$itemCategory} collection (Registration: {$itemReg}).";
}
$ogDescription = htmlspecialchars(substr($fullOgDescription, 0, 160), ENT_QUOTES);

$ogTitle = htmlspecialchars($item['title'] . ' — ' . $itemReg, ENT_QUOTES);
$ogUrl   = SITE_URL . '/item/' . $id;
$ogImage = '';

if ($primaryMedia) {
    if (isset($storage)) {
        $ogImage = $storage->url('display/' . $primaryMedia['file_path']);
    } else {
        // Correct the display path verification
        $displayFile = $primaryMedia['file_path'];
        $absDisplayPath = ABSPATH . '/uploads/display/' . $displayFile;
        $ogImage = (file_exists($absDisplayPath))
            ? SITE_URL . '/uploads/display/'   . rawurlencode($displayFile)
            : SITE_URL . '/uploads/originals/' . rawurlencode($displayFile);
    }
}

$keywords = array_column($itemTags ?? [], 'name');

$jsonLd = array_filter([
    '@context'    => 'https://schema.org',
    '@type'       => (!empty($item['category_name']) && stripos($item['category_name'], 'art') !== false) ? 'VisualArtwork' : 'ArchiveComponent',
    'name'        => $item['title'],
    'identifier'  => $item['reg_number'],
    'description' => $rawDescription,
    'url'         => $ogUrl,
    'image'       => $ogImage ?: null,
    'dateCreated' => $item['production_date'] ?? null,
    'creditText'  => $item['credit_line'] ?? null,
    'keywords'    => !empty($keywords) ? implode(', ', $keywords) : null,
    'isPartOf'    => [
        '@type' => 'ArchiveOrganization', 
        'name' => SITE_TITLE,
        'url'  => SITE_URL
    ],
]);
$jsonLdJson = json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// 6. Fetch Related Items (Manual Links + Category Fallback)
$relatedItems = [];
$manualIds = [];
try {
    $orderClause = $hasIsPrimary ? "is_primary DESC, upload_date ASC" : "upload_date ASC";
    $manualStmt = $pdo->prepare("
        SELECT i.id, i.title, i.reg_number, 
               (SELECT file_path FROM media WHERE item_id = i.id AND media_type = 'image' ORDER BY {$orderClause} LIMIT 1) as thumb
        FROM items i
        JOIN item_related r ON i.id = r.related_item_id
        WHERE r.item_id = :id
    ");
    $manualStmt->execute([':id' => $id]);
    $relatedItems = $manualStmt->fetchAll();
    $manualIds = array_column($relatedItems, 'id');
} catch (\Exception $e) {}

// Stage B: Support with Auto-Suggestions (same category) if < 4
if (count($relatedItems) < 4) {
    $limit = 4 - count($relatedItems);
    $excludeIds = array_merge([$id], $manualIds);
    $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
    
    $orderClause = $hasIsPrimary ? "is_primary DESC, upload_date ASC" : "upload_date ASC";
    $autoStmt = $pdo->prepare("
        SELECT id, title, reg_number, 
               (SELECT file_path FROM media WHERE item_id = items.id AND media_type = 'image' ORDER BY {$orderClause} LIMIT 1) as thumb
        FROM items
        WHERE category_id = ? AND id NOT IN ($placeholders)
        ORDER BY id DESC
        LIMIT " . (int)$limit
    );
    
    $params = array_merge([$item['category_id'] ?? 0], $excludeIds);
    $autoStmt->execute($params);
    $autoResults = $autoStmt->fetchAll();
    $relatedItems = array_merge($relatedItems, $autoResults);
}

?>
<?php require_once ThemeManager::getTemplatePath('item_detail.php'); ?>
