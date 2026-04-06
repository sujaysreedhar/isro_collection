<?php
// collection.php - Show a single curated collection
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/ThemeManager.php';

$slug = $_GET['slug'] ?? '';
if (!in_array('curated_collections', $activeModulesSlugs)) {
    header("HTTP/1.0 404 Not Found");
    require_once ThemeManager::getHeader();
    echo '<div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-24 text-center"><h1 class="text-4xl font-bold text-gray-900 mb-4">404 - Page Not Found</h1><p class="text-gray-600">The curated collections feature is currently disabled.</p><a href="' . SITE_URL . '" class="mt-6 inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">Go Home</a></div>';
    require_once ThemeManager::getFooter();
    exit;
}
if (!$slug) {
    header("Location: " . SITE_URL . "/collections.php");
    exit;
}

// Fetch collection
$stmt = $pdo->prepare("SELECT * FROM collections WHERE slug = ? AND is_public = 1");
$stmt->execute([$slug]);
$collection = $stmt->fetch();

if (!$collection) {
    die("Collection not found.");
}

$pageTitle = $collection['title'] . ' - Curated Collection';
$currentMenu = 'collections';

// Fetch items in this collection
$stmt = $pdo->prepare("
    SELECT i.*, 
        (SELECT file_path FROM media WHERE item_id = i.id AND media_type = 'image' ORDER BY id ASC LIMIT 1) as preview_file_path
    FROM items i 
    JOIN collection_items ci ON i.id = ci.item_id 
    WHERE ci.collection_id = ? 
    ORDER BY ci.sort_order ASC
");
$stmt->execute([$collection['id']]);
$results = $stmt->fetchAll();

require_once ThemeManager::getHeader();
?>

<div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-12">
    <!-- Header -->
    <!-- Header / Hero Section -->
    <div class="mb-16">
        <?php if (!empty($collection['cover_image'])): ?>
            <?php $coverUrl = isset($storage) ? $storage->url('display/' . $collection['cover_image']) : SITE_URL . '/uploads/display/' . $collection['cover_image']; ?>
            <div class="relative w-full h-[400px] md:h-[500px] rounded-[2.5rem] overflow-hidden mb-12 shadow-2xl group border border-gray-200">
                <img src="<?= htmlspecialchars($coverUrl) ?>" alt="<?= htmlspecialchars($collection['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-[2s]">
                <div class="absolute inset-0 bg-gradient-to-t from-gray-900/90 via-gray-900/40 to-transparent"></div>
                <div class="absolute bottom-0 left-0 p-8 md:p-12 w-full">
                    <div class="max-w-4xl">
                        <span class="inline-block px-4 py-1.5 bg-blue-600 text-white text-[10px] font-bold uppercase tracking-widest rounded-full mb-6 shadow-lg shadow-blue-500/30">Curated Collection</span>
                        <h1 class="text-4xl md:text-6xl font-extrabold text-white serif mb-6 leading-tight"><?= htmlspecialchars($collection['title']) ?></h1>
                        <div class="prose prose-invert prose-lg max-w-none text-gray-200/90 leading-relaxed line-clamp-3 md:line-clamp-none">
                            <?= nl2br(htmlspecialchars($collection['description'])) ?>
                        </div>
                        
                        <div class="mt-8 flex flex-wrap items-center gap-6">
                            <a href="<?= SITE_URL ?>/collections.php" class="inline-flex items-center gap-2.5 text-sm font-bold text-white/80 hover:text-white transition-colors group/back">
                                <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center group-hover/back:bg-white/20 transition-colors">
                                    <svg class="w-4 h-4 group-hover/back:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                </div>
                                Back to All Collections
                            </a>
                            <div class="h-4 w-px bg-white/20 hidden md:block"></div>
                            <span class="text-white/60 text-sm font-medium flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                <?= count($results) ?> Artifacts
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="max-w-4xl">
                <span class="inline-block px-4 py-1.5 bg-blue-50 text-blue-600 text-[10px] font-bold uppercase tracking-widest rounded-full mb-6">Curated Collection</span>
                <h1 class="text-4xl md:text-6xl font-extrabold text-gray-900 serif mb-8 leading-tight"><?= htmlspecialchars($collection['title']) ?></h1>
                <div class="prose prose-blue prose-lg max-w-none text-gray-600 leading-relaxed mb-8">
                    <?= nl2br(htmlspecialchars($collection['description'])) ?>
                </div>
                <a href="<?= SITE_URL ?>/collections.php" class="inline-flex items-center gap-2.5 text-sm font-bold text-blue-600 hover:text-blue-800 transition-colors group/back">
                    <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center group-hover/back:bg-blue-100 transition-colors">
                        <svg class="w-4 h-4 group-hover/back:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    </div>
                    Back to All Collections
                </a>
            </div>
            <div class="h-px bg-gray-100 w-full my-12"></div>
        <?php endif; ?>
    </div>

    <!-- Items Grid Section -->
    <?php if ($results): ?>
        <div class="flex items-center justify-between mb-10 pb-4 border-b border-gray-100">
            <h2 class="text-xl font-bold text-gray-900 serif flex items-center gap-3">
                <span class="w-2 h-8 bg-blue-600 rounded-full"></span>
                Collection Items
            </h2>
            <p class="text-sm font-medium text-gray-400"><?= count($results) ?> artifacts found</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
            <?php foreach ($results as $item): ?>
                <?php
                    $previewPath = trim((string)($item['preview_file_path'] ?? ''));
                    $previewUrl = '';
                    if ($previewPath !== '') {
                        if (isset($storage)) {
                            $previewUrl = $storage->url('display/' . $previewPath);
                        } else {
                            $displayPath = realpath(__DIR__ . '/../../uploads/display/' . $previewPath);
                            $previewUrl = $displayPath && file_exists($displayPath)
                                ? SITE_URL . '/uploads/display/' . rawurlencode($previewPath)
                                : SITE_URL . '/uploads/originals/' . rawurlencode($previewPath);
                        }
                    }
                ?>
                <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>"
                   class="group block bg-white rounded-3xl border border-gray-100 overflow-hidden hover:shadow-2xl hover:shadow-blue-500/10 hover:border-blue-200 transition-all duration-500 transform hover:-translate-y-2">
                    <div class="h-64 bg-gray-50 flex items-center justify-center overflow-hidden">
                        <?php if ($previewUrl): ?>
                            <img src="<?= htmlspecialchars($previewUrl) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="object-cover w-full h-full group-hover:scale-110 transition-transform duration-700">
                        <?php else: ?>
                            <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center text-gray-300 shadow-sm">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-[9px] font-bold uppercase tracking-wider rounded-md"><?= htmlspecialchars($item['reg_number']) ?></span>
                        </div>
                        <h3 class="font-bold text-gray-900 group-hover:text-blue-600 transition-colors line-clamp-2 leading-snug mb-3"><?= htmlspecialchars($item['title']) ?></h3>
                        <div class="flex items-center justify-between mt-auto pt-2 border-t border-gray-50">
                            <span class="text-[10px] font-medium text-gray-400"><?= htmlspecialchars($item['production_date'] ?? 'n.d.') ?></span>
                            <svg class="w-4 h-4 text-blue-500 opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-20 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
            <p class="text-gray-500">This collection is currently being assembled. Check back soon!</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once ThemeManager::getFooter(); ?>
