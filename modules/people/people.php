<?php
// modules/people/people.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/ThemeManager.php';

global $pdo, $activeModulesSlugs;

$pageTitle = 'People & Biographies - ' . SITE_TITLE;
$currentMenu = 'people';

if (!in_array('people', $activeModulesSlugs)) {
    header("HTTP/1.0 404 Not Found");
    require_once ThemeManager::getHeader();
    echo '<div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-24 text-center"><h1 class="text-4xl font-bold text-gray-900 mb-4">404 - Page Not Found</h1><p class="text-gray-600">This feature is currently disabled.</p></div>';
    require_once ThemeManager::getFooter();
    exit;
}

// Pagination
$perPage = 16;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Count total public people
$countStmt = $pdo->query("SELECT COUNT(*) FROM people WHERE is_public = 1");
$totalResults = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalResults / $perPage);

// Fetch paginated public people
$stmt = $pdo->prepare("SELECT * FROM people WHERE is_public = 1 ORDER BY name ASC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$people = $stmt->fetchAll();

require_once ThemeManager::getHeader();
?>

<div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-12 text-center">
        <h1 class="text-4xl md:text-5xl font-extrabold text-slate-900 serif mb-4 tracking-tight">People & Biographies</h1>
        <p class="text-lg text-slate-500 max-w-2xl mx-auto">Discover the historical figures, creators, and subjects whose stories are preserved in our collection.</p>
    </div>

    <?php if ($people): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
            <?php foreach ($people as $p): ?>
                <a href="<?= SITE_URL ?>/person/<?= urlencode($p['slug']) ?>" 
                   class="group block bg-white rounded-2xl border border-slate-200 overflow-hidden hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    
                    <div class="aspect-[4/5] bg-slate-50 relative overflow-hidden">
                        <?php if ($p['profile_image']): ?>
                            <img src="<?= SITE_URL ?>/uploads/display/<?= htmlspecialchars($p['profile_image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-700">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-slate-200">
                                <svg class="w-20 h-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-6">
                            <span class="text-white text-sm font-bold flex items-center gap-2">View Biography <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg></span>
                        </div>
                    </div>

                    <div class="p-6 text-center">
                        <h2 class="text-xl font-bold text-slate-900 group-hover:text-blue-600 transition-colors serif mb-1"><?= htmlspecialchars($p['name']) ?></h2>
                        <?php if ($p['birth_date'] || $p['death_date']): ?>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">
                                <?= htmlspecialchars($p['birth_date'] ?? '?') ?> &ndash; <?= htmlspecialchars($p['death_date'] ?? 'Present') ?>
                            </p>
                        <?php endif; ?>
                        <p class="text-sm text-slate-500 line-clamp-2 italic"><?= htmlspecialchars($p['short_description']) ?></p>
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
        <div class="text-center py-24 bg-slate-50 rounded-3xl border border-dashed border-slate-200">
            <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            <h3 class="mt-4 text-lg font-medium text-slate-900">No biographies published yet</h3>
            <p class="mt-1 text-slate-500">Check back later for historical profiles.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once ThemeManager::getFooter(); ?>
