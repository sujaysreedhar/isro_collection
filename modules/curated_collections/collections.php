<?php
// collections.php - List all public curated collections
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/ThemeManager.php';

$pageTitle = 'Curated Collections - ' . SITE_TITLE;
$currentMenu = 'collections';

if (!in_array('curated_collections', $activeModulesSlugs)) {
    header("HTTP/1.0 404 Not Found");
    require_once ThemeManager::getHeader();
    echo '<div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-24 text-center"><h1 class="text-4xl font-bold text-gray-900 mb-4">404 - Page Not Found</h1><p class="text-gray-600">The curated collections feature is currently disabled.</p><a href="' . SITE_URL . '" class="mt-6 inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">Go Home</a></div>';
    require_once ThemeManager::getFooter();
    exit;
}

// Pagination
$perPage = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Count total public collections
$countStmt = $pdo->query("SELECT COUNT(*) FROM collections WHERE is_public = 1");
$totalResults = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalResults / $perPage);

// Fetch paginated public collections
$stmt = $pdo->prepare("
    SELECT c.*, COUNT(ci.item_id) as item_count 
    FROM collections c 
    LEFT JOIN collection_items ci ON c.id = ci.collection_id 
    WHERE c.is_public = 1 
    GROUP BY c.id 
    ORDER BY c.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$collections = $stmt->fetchAll();

require_once ThemeManager::getHeader();
?>

<div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-10 text-center">
        <h1 class="text-4xl font-extrabold text-gray-900 serif mb-4">Curated Collections</h1>
        <p class="text-lg text-gray-600 max-w-2xl mx-auto">Explore themed groups of artifacts hand-picked by our curators to tell a specific story or highlight a unique era.</p>
    </div>

    <?php if ($collections): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($collections as $col): ?>
            <a href="<?= SITE_URL ?>/collection.php?slug=<?= urlencode($col['slug']) ?>" 
               class="group block bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-xl hover:border-blue-300 transition-all duration-300 transform hover:-translate-y-1 flex flex-col h-full">
                
                <div class="h-48 bg-gray-100 flex items-center justify-center relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-purple-500/10 opacity-0 group-hover:opacity-100 transition-opacity z-10"></div>
                    <?php if (!empty($col['cover_image'])): ?>
                        <?php $coverUrl = isset($storage) ? $storage->url('display/' . $col['cover_image']) : SITE_URL . '/uploads/display/' . $col['cover_image']; ?>
                        <img src="<?= htmlspecialchars($coverUrl) ?>" alt="<?= htmlspecialchars($col['title']) ?>" class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-700">
                    <?php else: ?>
                        <svg class="h-16 w-16 text-gray-300 group-hover:text-blue-500 group-hover:scale-110 transition-all duration-500 z-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    <?php endif; ?>
                    <div class="absolute bottom-4 right-4 z-20 bg-white/90 backdrop-blur-sm px-3 py-1 rounded-full text-xs font-bold text-gray-600 shadow-sm border border-gray-100">
                        <?= $col['item_count'] ?> items
                    </div>
                </div>

                <div class="p-6 flex flex-col flex-1">
                    <h2 class="text-xl font-bold text-gray-900 group-hover:text-blue-800 transition-colors mb-3 serif"><?= htmlspecialchars($col['title']) ?></h2>
                    <p class="text-sm text-gray-500 line-clamp-3 mb-6 flex-grow"><?= htmlspecialchars($col['description']) ?></p>
                    
                    <div class="pt-4 border-t border-gray-50 flex items-center text-blue-600 text-sm font-bold group-hover:translate-x-1 transition-transform">
                        Explore Collection
                        <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php 
        $currentPage = $page; 
        require ThemeManager::getTemplatePath('partials/pagination.php'); 
    ?>

    <?php else: ?>
    <div class="text-center py-24 bg-white rounded-3xl border border-gray-200 border-dashed">
        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900">No collections public yet</h3>
        <p class="mt-1 text-gray-500">Check back soon for curated selections of artifacts.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once ThemeManager::getFooter(); ?>
