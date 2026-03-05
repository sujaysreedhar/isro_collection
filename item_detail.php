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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($item['title']) ?> - <?= SITE_TITLE ?></title>

    <!-- Primary SEO -->
    <meta name="description" content="<?= $ogDescription ?>">
    <link rel="canonical" href="<?= $ogUrl ?>">

    <!-- OpenGraph (Facebook / LinkedIn) -->
    <meta property="og:type"        content="article">
    <meta property="og:url"         content="<?= $ogUrl ?>">
    <meta property="og:title"       content="<?= $ogTitle ?>">
    <meta property="og:description" content="<?= $ogDescription ?>">
    <?php if ($ogImage): ?><meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>"><?php endif; ?>
    <meta property="og:site_name"   content="<?= SITE_TITLE ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?= $ogTitle ?>">
    <meta name="twitter:description" content="<?= $ogDescription ?>">
    <?php if ($ogImage): ?><meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>"><?php endif; ?>

    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
<?= $jsonLdJson ?>
    </script>

    <!-- Tailwind CSS included via CDN for template purposes -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Typography System */
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f9fafb; /* light-grey minimal background */
        }
        
        h1, h2, h3, h4, .serif {
            font-family: 'Playfair Display', serif;
            color: #111827; /* bold black headings */
        }
    </style>
</head>
<body class="text-gray-800 antialiased flex flex-col min-h-screen">

    <!-- Global Header with Prominent Search Bar -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center">
                <a href="<?= SITE_URL ?>" class="text-2xl font-bold serif tracking-tight"><?= SITE_TITLE ?></a>
            </div>
            <div class="flex-1 max-w-2xl ml-8 hidden md:block">
                <form action="/search.php" method="GET" class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <!-- Prominent Search Box -->
                    <input type="text" name="q" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-gray-50 placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-gray-900 focus:border-gray-900 sm:text-sm transition duration-150 ease-in-out" placeholder="Search the collections... (e.g. Steam Engine)">
                </form>
            </div>
            <nav class="hidden lg:flex space-x-8 ml-8 flex-shrink-0">
                <a href="<?= SITE_URL ?>/search.php" class="text-gray-500 hover:text-gray-900 font-medium text-sm">Explore</a>
                <a href="<?= SITE_URL ?>/gallery.php" class="text-gray-500 hover:text-gray-900 font-medium text-sm">Gallery</a>
            </nav>
        </div>
    </header>

    <div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-10 flex flex-col md:flex-row gap-8">
        
        <!-- Main Content Area -->
        <main class="flex-1 min-w-0">
            <!-- Breadcrumbs -->
            <nav class="flex text-sm text-gray-500 mb-8" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center"><a href="<?= SITE_URL ?>" class="hover:text-gray-900">Collections</a></li>
                    <?php if (!empty($item['category_name'])): ?>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                            <a href="/category/<?= urlencode(strtolower(str_replace(' ', '-', $item['category_name']))) ?>" class="hover:text-gray-900"><?= htmlspecialchars($item['category_name']) ?></a>
                        </div>
                    </li>
                    <?php endif; ?>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                            <span class="text-gray-800"><?= htmlspecialchars($item['title']) ?></span>
                        </div>
                    </li>
                </ol>
            </nav>

            <!-- Item Detail Grid -->
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-12">
                
                <!-- Left Column: Media Viewer -->
                <div class="xl:col-span-7 border border-gray-200">
                    <div class="bg-white rounded-t shadow-sm overflow-hidden">
                        <div class="relative bg-gray-100 flex items-center justify-center h-[500px]">
                            <!-- Primary Image Viewer -->
                            <?php if ($primaryMedia): ?>
                                <?php
                                    if (isset($storage)) {
                                        $displaySrc = $storage->url('display/' . $primaryMedia['file_path']);
                                    } else {
                                        $displaySrc = file_exists(__DIR__ . '/uploads/display/' . $primaryMedia['file_path'])
                                            ? SITE_URL . '/uploads/display/' . rawurlencode($primaryMedia['file_path'])
                                            : (SITE_URL . '/uploads/originals/' . rawurlencode($primaryMedia['file_path']));
                                    }
                                ?>
                                <img src="<?= $displaySrc ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="object-cover w-full h-full">
                            <?php else: ?>
                                <div class="flex items-center justify-center h-full w-full text-gray-400 flex-col">
                                    <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <span>No Image Available</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Image Caption & License -->
                    <?php if ($primaryMedia): ?>
                    <div class="bg-gray-50 flex justify-between items-start text-sm text-gray-500 p-4 rounded-b border-t border-gray-200">
                        <p class="italic"><?= htmlspecialchars($primaryMedia['caption'] ?? 'No caption available.') ?></p>
                        <?php if (!empty($primaryMedia['license_type'])): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-800">
                            <?= htmlspecialchars($primaryMedia['license_type']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="bg-white border-b border-x border-gray-200 text-xs text-gray-500 p-3 rounded-b-lg flex flex-wrap gap-x-6 gap-y-2 mb-4">
                        <?php if(!empty($primaryMedia['dimensions'])): ?>
                            <div class="flex items-center gap-1.5"><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg><span class="font-medium text-gray-700"><?= htmlspecialchars($primaryMedia['dimensions']) ?> px</span></div>
                        <?php endif; ?>
                        <?php if(!empty($primaryMedia['file_size'])): ?>
                            <?php
                                $bytes = $primaryMedia['file_size'];
                                $units = ['B', 'KB', 'MB', 'GB'];
                                $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
                                $pow = min($pow, count($units) - 1);
                                $size = round($bytes / pow(1024, $pow), 1) . ' ' . $units[$pow];
                            ?>
                            <div class="flex items-center gap-1.5"><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg><span class="font-medium text-gray-700"><?= $size ?></span></div>
                        <?php endif; ?>
                        <?php if(!empty($primaryMedia['mime_type']) && $primaryMedia['mime_type'] !== 'image/youtube'): ?>
                            <div class="flex items-center gap-1.5"><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg><span class="font-medium text-gray-700"><?= strtoupper(str_replace('image/', '', htmlspecialchars($primaryMedia['mime_type']))) ?></span></div>
                        <?php endif; ?>
                        <?php if(!empty($primaryMedia['upload_date'])): ?>
                            <div class="flex items-center gap-1.5"><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg><span class="font-medium text-gray-700">Added <?= date('M j, Y', strtotime($primaryMedia['upload_date'])) ?></span></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column: Item Information -->
                <div class="xl:col-span-5">
                    <div class="mb-2">
                        <span class="text-sm font-semibold tracking-wider text-gray-500 uppercase">Reg: <?= htmlspecialchars($item['reg_number']) ?></span>
                    </div>
                    <h1 class="text-4xl font-bold mb-4 leading-tight"><?= htmlspecialchars($item['title']) ?></h1>

                    <?php if ($itemTags): ?>
                    <div class="flex flex-wrap gap-2 mb-6">
                        <?php foreach ($itemTags as $tag): ?>
                            <a href="<?= SITE_URL ?>/tag/<?= htmlspecialchars($tag['slug']) ?>"
                               class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-800 hover:text-white transition-colors duration-200">
                                <span class="mr-1 text-gray-400">#</span><?= htmlspecialchars($tag['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="prose prose-gray mb-8 serif text-lg leading-relaxed text-gray-700">
                        <p>
                            <?= nl2br(htmlspecialchars($item['physical_description'] ?? '')) ?>
                        </p>
                    </div>

                    <!-- Structured Specifications Table -->
                    <div class="border-t border-gray-200 pt-6 mb-10">
                        <h3 class="text-lg font-bold mb-4">Specifications</h3>
                        <dl class="divide-y divide-gray-200 text-sm">
                            <?php if (!empty($item['category_name'])): ?>
                            <div class="py-3 flex justify-between">
                                <dt class="text-gray-500 font-medium w-1/3">Category</dt>
                                <dd class="text-gray-900 w-2/3 text-right">
                                    <a href="<?= SITE_URL ?>/search.php?category_ids[]=<?= htmlspecialchars($item['category_id']) ?>" class="text-blue-600 hover:text-blue-800 hover:underline" title="Find more items in this category">
                                        <?= htmlspecialchars($item['category_name']) ?>
                                    </a>
                                </dd>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['production_date'])): ?>
                            <div class="py-3 flex justify-between">
                                <dt class="text-gray-500 font-medium w-1/3">Production Date</dt>
                                <dd class="text-gray-900 w-2/3 text-right">
                                    <a href="<?= SITE_URL ?>/search.php?q=<?= urlencode($item['production_date']) ?>" class="text-blue-600 hover:text-blue-800 hover:underline" title="Find more items from this period">
                                        <?= htmlspecialchars($item['production_date']) ?>
                                    </a>
                                </dd>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['credit_line'])): ?>
                            <div class="py-3 flex justify-between">
                                <dt class="text-gray-500 font-medium w-1/3">Credit Line</dt>
                                <dd class="text-gray-900 w-2/3 text-right">
                                    <a href="<?= SITE_URL ?>/search.php?q=<?= urlencode($item['credit_line']) ?>" class="text-blue-600 hover:text-blue-800 hover:underline" title="Find more items from this collection/donor">
                                        <?= htmlspecialchars($item['credit_line']) ?>
                                    </a>
                                </dd>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($item['historical_significance'])): ?>
                            <div class="py-3 flex flex-col sm:flex-row justify-between">
                                <dt class="text-gray-500 font-medium w-full sm:w-1/3 mb-1 sm:mb-0">Historical Significance</dt>
                                <dd class="text-gray-900 w-full sm:w-2/3 sm:text-right"><?= htmlspecialchars($item['historical_significance']) ?></dd>
                            </div>
                            <?php endif; ?>
                        </dl>
                    </div>

                    <!-- YouTube Videos -->
                    <?php if ($youtubeMedia): ?>
                    <div class="mb-10">
                        <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                            <span class="bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded">▶ VIDEO</span>
                            Video<?= count($youtubeMedia) > 1 ? 's' : '' ?>
                        </h3>
                        <div class="space-y-4">
                            <?php foreach ($youtubeMedia as $yt): ?>
                            <div class="rounded-lg overflow-hidden shadow-sm border border-gray-200">
                                <div class="relative w-full" style="padding-top:56.25%">
                                    <iframe class="absolute inset-0 w-full h-full"
                                            src="https://www.youtube.com/embed/<?= htmlspecialchars($yt['file_path']) ?>"
                                            title="<?= htmlspecialchars($yt['caption'] ?? $item['title']) ?>"
                                            frameborder="0"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                            allowfullscreen></iframe>
                                </div>
                                <?php if (!empty($yt['caption'])): ?>
                                <div class="bg-gray-50 px-4 py-2 text-sm text-gray-500 italic"><?= htmlspecialchars($yt['caption']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- PDF Documents -->
                    <?php if ($pdfMedia): ?>
                    <div class="mb-10">
                        <h3 class="text-lg font-bold mb-4">📄 Documents</h3>
                        <div class="space-y-2">
                            <?php foreach ($pdfMedia as $pdf): ?>
                            <a href="<?= SITE_URL ?>/uploads/pdfs/<?= rawurlencode($pdf['file_path']) ?>"
                               target="_blank"
                               class="flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-red-400 hover:shadow-sm transition group">
                                <svg class="w-8 h-8 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                <div>
                                    <p class="text-sm font-medium text-gray-800 group-hover:text-red-700"><?= htmlspecialchars($pdf['caption'] ?: basename($pdf['file_path'])) ?></p>
                                    <?php if (!empty($pdf['file_size'])): ?>
                                    <p class="text-xs text-gray-400"><?= round($pdf['file_size'] / 1024, 1) ?> KB · PDF</p>
                                    <?php endif; ?>
                                </div>
                                <svg class="w-4 h-4 text-gray-400 ml-auto group-hover:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Related Stories (Narratives) -->
                    <?php if (count($stories) > 0): ?>
                    <div class="mb-10 bg-white p-6 shadow-sm border border-gray-200 border-l-4 border-l-gray-800">
                        <h3 class="text-lg font-bold mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            Related Stories
                        </h3>
                        <ul class="space-y-4">
                            <?php foreach ($stories as $story): ?>
                            <li>
                                <a href="<?= SITE_URL ?>/story/<?= (int)$story['id'] ?>" class="group block">
                                    <h4 class="text-md font-semibold text-blue-800 group-hover:underline transition-colors"><?= htmlspecialchars($story['title']) ?></h4>
                                    <p class="text-sm text-gray-600 mt-1 line-clamp-2">
                                        <?= htmlspecialchars(substr(strip_tags($story['content_body']), 0, 150)) ?>...
                                    </p>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- How to Cite -->
                    <div class="border border-gray-200 rounded p-4 bg-white">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-2">How to Cite</h3>
                        <div class="bg-gray-50 p-3 border border-gray-200 font-mono text-xs text-gray-800 break-words cursor-text select-all">
                            <?= $citation ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </main>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white mt-12 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center text-sm text-gray-400">
            <p>&copy; <?= $currentYear ?> <?= SITE_TITLE ?>. All rights reserved.</p>
            <div class="flex space-x-6">
                <a href="#" class="hover:text-white transition-colors">Privacy</a>
                <a href="#" class="hover:text-white transition-colors">Terms</a>
            </div>
        </div>
    </footer>

</body>
</html>
