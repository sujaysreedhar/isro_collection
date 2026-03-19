<?php
// Include database connection
require_once __DIR__ . '/config/config.php';

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
$primaryMedia = $imageMedia[0] ?? null;   // primary is always image-type

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
$ogTitle       = htmlspecialchars($item['title'], ENT_QUOTES);
$ogDescription = htmlspecialchars(substr(strip_tags($item['physical_description'] ?? 'View this artifact in the collection.'), 0, 160), ENT_QUOTES);
$ogUrl         = SITE_URL . '/item/' . $id;
$ogImage       = '';
if ($primaryMedia) {
    if (isset($storage)) {
        $ogImage = $storage->url('display/' . $primaryMedia['file_path']);
    } else {
        $displayPath = __DIR__ . '/uploads/display/' . $primaryMedia['file_path'];
        $ogImage = file_exists($displayPath)
            ? SITE_URL . '/uploads/display/'   . rawurlencode($primaryMedia['file_path'])
            : SITE_URL . '/uploads/originals/' . rawurlencode($primaryMedia['file_path']);
    }
}
$jsonLd = array_filter([
    '@context'    => 'https://schema.org',
    '@type'       => (!empty($item['category_name']) && stripos($item['category_name'], 'art') !== false) ? 'VisualArtwork' : 'ArchiveComponent',
    'name'        => $item['title'],
    'identifier'  => $item['reg_number'],
    'description' => strip_tags($item['physical_description'] ?? ''),
    'url'         => $ogUrl,
    'image'       => $ogImage ?: null,
    'dateCreated' => $item['production_date'] ?? null,
    'creditText'  => $item['credit_line'] ?? null,
    'isPartOf'    => ['@type' => 'ArchiveOrganization', 'name' => SITE_TITLE],
]);
$jsonLdJson = json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

?>require_once ThemeManager::getTemplatePath('item_detail.php');
</html>
