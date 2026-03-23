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
    <div class="mb-12 border-b border-gray-200 pb-8">
        <a href="<?= SITE_URL ?>/collections.php" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1 mb-6 font-medium group w-max">
            <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to All Collections
        </a>
        
        <?php if (!empty($collection['cover_image'])): ?>
            <?php $coverUrl = isset($storage) ? $storage->url('display/' . $collection['cover_image']) : SITE_URL . '/uploads/display/' . $collection['cover_image']; ?>
            <div class="w-full h-64 md:h-80 lg:h-96 rounded-2xl overflow-hidden mb-8 shadow-sm border border-gray-200">
                <img src="<?= htmlspecialchars($coverUrl) ?>" alt="<?= htmlspecialchars($collection['title']) ?>" class="w-full h-full object-cover">
            </div>
        <?php endif; ?>

        <h1 class="text-4xl font-extrabold text-gray-900 serif mb-4"><?= htmlspecialchars($collection['title']) ?></h1>
        <div class="prose prose-blue max-w-4xl text-gray-600">
            <?= nl2br(htmlspecialchars($collection['description'])) ?>
        </div>
    </div>

    <!-- Items Grid -->
    <div class="mb-6 flex justify-between items-center text-sm">
        <h2 class="font-bold text-gray-900 uppercase tracking-widest"><?= count($results) ?> Artifacts</h2>
    </div>

    <?php if ($results): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
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
                   class="group block bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg hover:border-blue-300 transition-all">
                    <div class="h-48 bg-gray-50 flex items-center justify-center p-4">
                        <?php if ($previewUrl): ?>
                            <img src="<?= htmlspecialchars($previewUrl) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-500">
                        <?php else: ?>
                            <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <?php endif; ?>
                    </div>
                    <div class="p-4">
                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-1"><?= htmlspecialchars($item['reg_number']) ?></p>
                        <h3 class="font-bold text-gray-900 group-hover:text-blue-700 transition line-clamp-2"><?= htmlspecialchars($item['title']) ?></h3>
                        <p class="text-xs text-gray-500 mt-2"><?= htmlspecialchars($item['production_date'] ?? 'n.d.') ?></p>
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
