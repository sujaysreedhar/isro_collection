<?php
// themes/modern_blue/search.php

$pageTitle = 'Search Collections - ' . SITE_TITLE;
$currentMenu = 'search';

require_once ThemeManager::getHeader();
?>

    <div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 md:py-12">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8 border-b border-slate-200 pb-8">
            <div class="flex-1">
                <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">Search Collections</h1>
                <p class="mt-3 text-lg text-slate-500 max-w-3xl">
                    <?php if ($q): ?>
                        Showing <strong class="text-slate-900 font-semibold"><?= number_format($totalResults) ?></strong> results for <span class="bg-modern-50 text-modern-700 font-medium px-2 py-0.5 rounded-md">"<?= htmlspecialchars($q) ?>"</span>
                    <?php else: ?>
                        Browsing <strong class="text-slate-900 font-semibold"><?= number_format($totalResults) ?></strong> cataloged items. Use the filters to refine.
                    <?php endif; ?>
                </p>
                <?php if (isset($searchStrategy) && $searchStrategy === 'spell_corrected'): ?>
                    <div class="mt-4 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r-lg shadow-sm w-full">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    Showing results for <span class="font-bold">"<?= htmlspecialchars($q) ?>"</span> instead of <a href="<?= SITE_URL ?>/search.php?q=<?= urlencode($_GET['q'] ?? '') ?>&exact=1" class="font-bold underline text-modern-600 hover:text-modern-800">"<?= htmlspecialchars($_GET['q'] ?? '') ?>"</a>.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="w-full md:w-auto">
                <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-slate-400 group-focus-within:text-modern-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                    <!-- Preserve facet selections -->
                    <?php if (!empty($selectedCategories)): ?>
                        <?php foreach($selectedCategories as $cid): ?><input type="hidden" name="category_ids[]" value="<?= htmlspecialchars($cid) ?>"><?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($selectedTags)): ?>
                        <?php foreach($selectedTags as $tid): ?><input type="hidden" name="tag_ids[]" value="<?= htmlspecialchars($tid) ?>"><?php endforeach; ?>
                    <?php endif; ?>
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="block w-full pl-10 pr-20 py-3 border border-slate-300 rounded-xl leading-5 bg-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-modern-500/20 focus:border-modern-500 sm:text-sm shadow-sm transition-all" placeholder="New search...">
                    <button type="submit" class="absolute inset-y-1 right-1 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-sm rounded-lg border border-slate-200 transition-colors">Go</button>
                </form>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            
            <!-- Sidebar Facets -->
            <aside class="w-full lg:w-72 lg:flex-shrink-0">
                <div class="bg-white border text-sm border-slate-200 rounded-2xl shadow-sm p-5 sticky top-24">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-base font-bold text-slate-900 tracking-tight">Refine Results</h2>
                        <?php if ($q || $selectedCategories || $selectedTags): ?>
                            <a href="<?= SITE_URL ?>/search.php" class="text-xs font-semibold text-modern-600 hover:text-modern-800 hover:underline">Clear all</a>
                        <?php endif; ?>
                    </div>

                    <!-- Categories Facet -->
                    <?php if (!empty($facets['categories'])): ?>
                    <div class="mb-8">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">By Category</h3>
                        <ul class="space-y-3">
                            <?php foreach ($facets['categories'] as $cat): ?>
                            <?php $isActive = in_array($cat['id'], $selectedCategories); ?>
                            <li>
                                <a href="<?= buildFilterUrl('category_ids', $cat['id']) ?>" class="group flex items-center justify-between text-slate-600 hover:text-modern-600">
                                    <div class="flex items-center">
                                        <div class="w-5 h-5 border rounded flex items-center justify-center mr-3 <?= $isActive ? 'bg-modern-500 border-modern-500' : 'border-slate-300 group-hover:border-modern-400' ?> transition-colors">
                                            <?php if ($isActive): ?>
                                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                            <?php endif; ?>
                                        </div>
                                        <span class="<?= $isActive ? 'font-semibold text-modern-700' : 'font-medium' ?>"><?= htmlspecialchars($cat['name']) ?></span>
                                    </div>
                                    <span class="bg-slate-100 text-slate-500 py-0.5 px-2.5 rounded-full text-xs font-semibold group-hover:bg-modern-50 group-hover:text-modern-600 transition-colors"><?= $cat['count'] ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Tags Facet -->
                    <?php if (!empty($facets['tags'])): ?>
                    <div>
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">By Tag</h3>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($facets['tags'] as $tag): ?>
                            <?php $isActive = in_array($tag['id'], $selectedTags); ?>
                            <a href="<?= buildFilterUrl('tag_ids', $tag['id']) ?>" 
                               class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors
                                      <?= $isActive ? 'bg-modern-500 text-white border-modern-500 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:border-modern-300 hover:bg-slate-50' ?>">
                                <?= htmlspecialchars($tag['name']) ?> <span class="<?= $isActive ? 'text-white/80' : 'text-slate-400' ?> ml-1.5">(<?= $tag['count'] ?>)</span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (empty($facets['categories']) && empty($facets['tags'])): ?>
                        <p class="text-slate-500 italic text-center py-4 text-sm">No filters available for this request.</p>
                    <?php endif; ?>
                </div>
            </aside>

            <!-- Listing Results -->
            <main class="flex-1 min-w-0">
                
                <!-- Active Filters Chips -->
                <?php if ($selectedCategories || $selectedTags): ?>
                <div class="flex flex-wrap items-center gap-2 mb-6 p-3 bg-modern-50 rounded-xl border border-modern-100">
                    <span class="text-xs font-bold text-modern-600 uppercase tracking-widest mx-2">Active Filters:</span>
                    <?php 
                    foreach ($selectedCategories as $cid) {
                        $name = $catNameMap[$cid] ?? 'Category';
                        echo '<a href="'.buildFilterUrl('category_ids', $cid).'" class="inline-flex items-center py-1 px-3 rounded-md text-sm font-medium bg-white text-slate-700 border border-modern-200 hover:bg-red-50 hover:text-red-700 hover:border-red-200 transition-colors shadow-sm gap-2">'.htmlspecialchars($name).' <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></a>';
                    }
                    foreach ($selectedTags as $tid) {
                        $name = $tagNameMap[$tid] ?? 'Tag';
                        echo '<a href="'.buildFilterUrl('tag_ids', $tid).'" class="inline-flex items-center py-1 px-3 rounded-md text-sm font-medium bg-white text-slate-700 border border-modern-200 hover:bg-red-50 hover:text-red-700 hover:border-red-200 transition-colors shadow-sm gap-2"><span class="text-slate-400">#</span>'.htmlspecialchars($name).' <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></a>';
                    }
                    ?>
                </div>
                <?php endif; ?>

                <?php if (empty($items)): ?>
                    <div class="text-center py-20 bg-white rounded-3xl border border-slate-200 border-dashed">
                        <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-slate-900">No matching items found</h3>
                        <p class="mt-1 text-slate-500">Try adjusting your search query or removing filters.</p>
                        <div class="mt-6">
                            <a href="<?= SITE_URL ?>/search.php" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-medium rounded-lg text-slate-700 bg-white hover:bg-slate-50 transition-colors">
                                Clear search
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 xl:gap-8">
                        <?php foreach ($items as $item): ?>
                            <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="group flex flex-col bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-modern-300 transition-all duration-300 overflow-hidden transform hover:-translate-y-1">
                                <!-- Image Thumbnail -->
                                <div class="w-full h-56 bg-slate-100 relative overflow-hidden flex-shrink-0">
                                    <?php if (!empty($item['primary_media_path'])): ?>
                                        <?php
                                            if (isset($storage)) {
                                                $mediaSrc = $storage->url('display/' . $item['primary_media_path']);
                                            } else {
                                                $mediaSrc = file_exists(realpath(__DIR__ . '/../../uploads/display/' . $item['primary_media_path'])) 
                                                    ? SITE_URL . '/uploads/display/' . rawurlencode($item['primary_media_path'])
                                                    : SITE_URL . '/uploads/originals/' . rawurlencode($item['primary_media_path']);
                                            }
                                            
                                            $isYoutube = ($item['primary_media_type'] === 'youtube');
                                            if ($isYoutube) {
                                                preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $item['primary_media_path'], $matches);
                                                $ytId = $matches[1] ?? '';
                                                $mediaSrc = "https://img.youtube.com/vi/{$ytId}/maxresdefault.jpg";
                                            }
                                        ?>
                                        <img src="<?= $mediaSrc ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-in-out" loading="lazy">
                                    <?php else: ?>
                                        <div class="w-full h-full flex flex-col items-center justify-center text-slate-400">
                                            <svg class="h-10 w-10 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($item['category_name'])): ?>
                                        <div class="absolute top-3 left-3 bg-white/90 backdrop-blur text-slate-800 text-xs font-bold px-2.5 py-1 rounded-md shadow-sm tracking-wide">
                                            <?= htmlspecialchars($item['category_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="p-5 flex flex-col flex-1">
                                    <span class="text-[10px] font-bold tracking-widest text-slate-400 uppercase mb-1 block"><?= htmlspecialchars($item['reg_number']) ?></span>
                                    <h3 class="text-lg font-bold text-slate-900 group-hover:text-modern-600 transition-colors leading-snug mb-2 line-clamp-2"><?= htmlspecialchars($item['title']) ?></h3>
                                    <p class="text-sm text-slate-500 line-clamp-2 mb-4 flex-1"><?= htmlspecialchars(strip_tags($item['physical_description'] ?? '')) ?></p>
                                    
                                    <div class="flex items-center text-xs font-medium text-slate-400 mt-auto pt-4 border-t border-slate-100">
                                        <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        <?= htmlspecialchars($item['production_date'] ?? 'n.d.') ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="mt-12 flex justify-center">
                        <nav class="inline-flex rounded-xl shadow-sm bg-white border border-slate-200 p-1" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a href="<?= buildQuery(['page' => $page - 1]) ?>" class="relative inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">
                                    &larr; Prev
                                </a>
                            <?php else: ?>
                                <span class="relative inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-slate-300 cursor-not-allowed">
                                    &larr; Prev
                                </span>
                            <?php endif; ?>
                            
                            <!-- Simplified Pagination logic -->
                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <?php if ($i === $page): ?>
                                    <span class="relative inline-flex items-center px-4 py-2 text-sm font-bold rounded-lg text-modern-700 bg-modern-50 ring-1 ring-inset ring-modern-200">
                                        <?= $i ?>
                                    </span>
                                <?php else: ?>
                                    <a href="<?= buildQuery(['page' => $i]) ?>" class="relative inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">
                                        <?= $i ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= buildQuery(['page' => $page + 1]) ?>" class="relative inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">
                                    Next &rarr;
                                </a>
                            <?php else: ?>
                                <span class="relative inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-slate-300 cursor-not-allowed">
                                    Next &rarr;
                                </span>
                            <?php endif; ?>
                        </nav>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

<?php require_once ThemeManager::getFooter(); ?>
