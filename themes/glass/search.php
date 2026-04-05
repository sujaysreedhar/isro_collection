<?php
// themes/glass/search.php
$pageTitle = 'Search Collections - ' . SITE_TITLE;
$currentMenu = 'explore';
$searchParams = $params;
$selectedCategories = $params['category_ids'];
$selectedMaterials = $params['materials'];
$q = $params['q'];
$totalResults = count($results);

require_once ThemeManager::getHeader();
?>

    <div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 md:py-12 relative z-10">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8 border-b border-white/10 pb-8">
            <div class="flex-1">
                <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight drop-shadow-sm">Search Collections</h1>
                <p class="mt-3 text-lg text-slate-300 max-w-3xl">
                    <?php if ($q): ?>
                        Showing <strong class="text-white font-semibold"><?= number_format($totalResults) ?></strong> results for <span class="bg-glass-500/20 text-glass-300 font-medium px-2 py-0.5 rounded-md border border-glass-400/20">"<?= htmlspecialchars($q) ?>"</span>
                    <?php else: ?>
                        Browsing <strong class="text-white font-semibold"><?= number_format($totalResults) ?></strong> cataloged items. Use the filters to refine.
                    <?php endif; ?>
                </p>
                <?php if (!empty($searchMeta['was_corrected'])): ?>
                    <div class="mt-4 bg-yellow-500/10 border-l-4 border-yellow-400/50 p-4 rounded-r-lg shadow-sm w-full backdrop-blur-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-200">
                                    Showing results for <span class="font-bold">"<?= htmlspecialchars($searchMeta['corrected_query']) ?>"</span> instead of <a href="<?= SITE_URL ?>/search.php?q=<?= urlencode($searchMeta['original_query']) ?>&exact=1" class="font-bold underline text-glass-400 hover:text-glass-300">"<?= htmlspecialchars($searchMeta['original_query']) ?>"</a>.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="w-full md:w-auto">
                <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-slate-400 group-focus-within:text-glass-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                    <?php if (!empty($selectedCategories)): ?>
                        <?php foreach($selectedCategories as $cid): ?><input type="hidden" name="category_ids[]" value="<?= htmlspecialchars($cid) ?>"><?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($selectedMaterials)): ?>
                        <?php foreach($selectedMaterials as $m): ?><input type="hidden" name="materials[]" value="<?= htmlspecialchars($m) ?>"><?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($params['tag'])): ?><input type="hidden" name="tags[]" value="<?= htmlspecialchars($params['tag']) ?>"><?php endif; ?>
                    <?php foreach(($params['tags'] ?? []) as $t): ?><input type="hidden" name="tags[]" value="<?= htmlspecialchars($t) ?>"><?php endforeach; ?>
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="block w-full pl-10 pr-20 py-3 border border-white/10 rounded-xl leading-5 bg-white/5 backdrop-blur-sm placeholder-slate-400 text-white focus:outline-none focus:ring-2 focus:ring-glass-500/50 focus:border-glass-400 sm:text-sm shadow-sm transition-all" placeholder="New search...">
                    <button type="submit" class="absolute inset-y-1 right-1 px-4 py-2 bg-white/10 hover:bg-white/20 text-white font-medium text-sm rounded-lg border border-white/10 transition-colors">Go</button>
                </form>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            
            <!-- Sidebar Facets -->
            <aside class="w-full lg:w-72 lg:flex-shrink-0">
                <div class="bg-white/5 backdrop-blur-xl border text-sm border-white/10 rounded-2xl shadow-[0_4px_16px_rgba(0,0,0,0.2)] p-6 sticky top-24">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-base font-bold text-white tracking-tight">Filters</h2>
                    </div>

                    <!-- Date Range Facet -->
                    <div class="mb-8">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Date Range</h3>
                        <form action="<?= SITE_URL ?>/search.php" method="GET" class="flex items-center gap-2">
                            <?php if ($q): ?><input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"><?php endif; ?>
                            <?php if (!empty($params['tag'])): ?><input type="hidden" name="tags[]" value="<?= htmlspecialchars($params['tag']) ?>"><?php endif; ?>
                            <?php foreach(($params['tags'] ?? []) as $t): ?><input type="hidden" name="tags[]" value="<?= htmlspecialchars($t) ?>"><?php endforeach; ?>
                            <?php foreach($selectedCategories as $cid): ?><input type="hidden" name="category_ids[]" value="<?= htmlspecialchars($cid) ?>"><?php endforeach; ?>
                            <?php foreach($selectedMaterials as $m): ?><input type="hidden" name="materials[]" value="<?= htmlspecialchars($m) ?>"><?php endforeach; ?>
                            
                            <input type="number" name="year_start" value="<?= htmlspecialchars($params['year_start'] ?? '') ?>" placeholder="<?= $facets['year_min'] ?? 'Start' ?>" class="w-full min-w-0 border-white/10 bg-white/5 text-white rounded-lg text-sm px-2 py-1.5 focus:border-glass-400 focus:ring-glass-500/30 placeholder-slate-500">
                            <span class="text-slate-500">-</span>
                            <input type="number" name="year_end" value="<?= htmlspecialchars($params['year_end'] ?? '') ?>" placeholder="<?= $facets['year_max'] ?? 'End' ?>" class="w-full min-w-0 border-white/10 bg-white/5 text-white rounded-lg text-sm px-2 py-1.5 focus:border-glass-400 focus:ring-glass-500/30 placeholder-slate-500">
                            <button type="submit" class="bg-glass-600 text-white rounded-lg px-3 py-1.5 text-xs font-bold hover:bg-glass-500 transition-colors">&rarr;</button>
                        </form>
                    </div>

                    <!-- Categories Facet -->
                    <?php if (!empty($facets['categories'])): ?>
                    <div class="mb-8 border-t border-white/10 pt-6">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">By Category</h3>
                        <ul class="space-y-3">
                            <?php foreach ($facets['categories'] as $cat): ?>
                            <?php $isActive = in_array($cat['id'], $selectedCategories); ?>
                            <li>
                                <a href="<?= buildFilterUrl($params, 'category_ids', $cat['id']) ?>" class="group flex items-center justify-between text-slate-300 hover:text-glass-300">
                                    <div class="flex items-center">
                                        <div class="w-5 h-5 border rounded flex items-center justify-center mr-3 <?= $isActive ? 'bg-glass-500 border-glass-500' : 'border-slate-600 group-hover:border-glass-400' ?> transition-colors">
                                            <?php if ($isActive): ?>
                                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                            <?php endif; ?>
                                        </div>
                                        <span class="<?= $isActive ? 'font-semibold text-glass-300' : 'font-medium' ?>"><?= htmlspecialchars($cat['name']) ?></span>
                                    </div>
                                    <span class="bg-white/10 text-slate-400 py-0.5 px-2.5 rounded-full text-xs font-semibold group-hover:bg-glass-500/20 group-hover:text-glass-300 transition-colors"><?= $cat['facet_count'] ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Materials Facet -->
                    <?php if (!empty($facets['materials'])): ?>
                    <div class="mb-8 border-t border-white/10 pt-6">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">By Material</h3>
                        <ul class="space-y-3">
                            <?php foreach ($facets['materials'] as $mat): ?>
                            <?php $isActive = in_array($mat['name'], $selectedMaterials); ?>
                            <li>
                                <a href="<?= buildFilterUrl($params, 'materials', $mat['name']) ?>" class="group flex items-center justify-between text-slate-300 hover:text-glass-300">
                                    <div class="flex items-center">
                                        <div class="w-5 h-5 border rounded flex items-center justify-center mr-3 <?= $isActive ? 'bg-glass-500 border-glass-500' : 'border-slate-600 group-hover:border-glass-400' ?> transition-colors">
                                            <?php if ($isActive): ?>
                                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                            <?php endif; ?>
                                        </div>
                                        <span class="<?= $isActive ? 'font-semibold text-glass-300' : 'font-medium' ?>"><?= htmlspecialchars($mat['name']) ?></span>
                                    </div>
                                    <span class="bg-white/10 text-slate-400 py-0.5 px-2.5 rounded-full text-xs font-semibold group-hover:bg-glass-500/20 group-hover:text-glass-300 transition-colors"><?= $mat['facet_count'] ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Tags Facet -->
                    <?php if (!empty($facets['tags'])): ?>
                    <div class="border-t border-white/10 pt-6">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">By Tag</h3>
                        <div class="flex flex-wrap gap-2">
                            <?php 
                            $activeTagsForFacet = $params['tags'] ?? [];
                            if (!empty($params['tag'])) $activeTagsForFacet[] = $params['tag'];
                            foreach ($facets['tags'] as $tag): 
                                $isActive = in_array($tag['slug'], $activeTagsForFacet);
                            ?>
                            <a href="<?= buildFilterUrl($params, 'tags', $tag['slug']) ?>"
                               class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors
                                      <?= $isActive ? 'bg-glass-500 text-white border-glass-500 shadow-[0_2px_8px_rgba(139,92,246,0.4)]' : 'bg-white/5 text-slate-300 border-white/10 hover:border-glass-400/50 hover:bg-glass-500/10' ?>">
                                <?= htmlspecialchars($tag['name']) ?> <span class="<?= $isActive ? 'text-white/80' : 'text-slate-500' ?> ml-1.5">(<?= $tag['facet_count'] ?>)</span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </aside>

            <!-- Listing Results -->
            <main class="flex-1 min-w-0">
                
                <!-- Active Filters Chips -->
                <?php $hasAnyFilters = $selectedCategories || $selectedMaterials || !empty($params['tag']) || !empty($params['tags']) || $params['year_start'] || $params['year_end']; ?>
                <?php if ($hasAnyFilters): ?>
                <div class="flex flex-wrap items-center gap-2 mb-6 p-3 bg-glass-500/10 rounded-xl border border-glass-400/20 backdrop-blur-sm">
                    <span class="text-xs font-bold text-glass-400 uppercase tracking-widest mx-2">Active Filters:</span>
                    <?php
                    if ($params['year_start'] || $params['year_end']) {
                        $range = ($params['year_start'] ?? '*') . ' - ' . ($params['year_end'] ?? '*');
                        $un = $params; $un['year_start'] = null; $un['year_end'] = null;
                        echo '<a href="'.SITE_URL.'/search.php?'.buildQuery($un).'" class="inline-flex items-center py-1 px-3 rounded-md text-sm font-medium bg-white/10 text-slate-200 border border-white/10 hover:bg-red-500/20 hover:text-red-300 hover:border-red-400/30 transition-colors shadow-sm gap-2">Dates: '.htmlspecialchars($range).' <span class="text-slate-500">&times;</span></a>';
                    }
                    foreach ($selectedCategories as $cid) {
                        $name = $catNameMap[$cid] ?? 'Category';
                        echo '<a href="'.buildFilterUrl($params, 'category_ids', $cid).'" class="inline-flex items-center py-1 px-3 rounded-md text-sm font-medium bg-white/10 text-slate-200 border border-white/10 hover:bg-red-500/20 hover:text-red-300 hover:border-red-400/30 transition-colors shadow-sm gap-2">'.htmlspecialchars($name).' <span class="text-slate-500">&times;</span></a>';
                    }
                    foreach ($selectedMaterials as $mat) {
                        echo '<a href="'.buildFilterUrl($params, 'materials', $mat).'" class="inline-flex items-center py-1 px-3 rounded-md text-sm font-medium bg-white/10 text-slate-200 border border-white/10 hover:bg-red-500/20 hover:text-red-300 hover:border-red-400/30 transition-colors shadow-sm gap-2">Material: '.htmlspecialchars($mat).' <span class="text-slate-500">&times;</span></a>';
                    }
                    $activeTags = $params['tags'] ?? [];
                    if (!empty($params['tag'])) $activeTags[] = $params['tag'];
                    $activeTags = array_unique($activeTags);
                    foreach ($activeTags as $aTag) {
                        $name = $tagNameMap[$aTag] ?? $aTag;
                        echo '<a href="'.buildFilterUrl($params, 'tags', $aTag).'" class="inline-flex items-center py-1 px-3 rounded-md text-sm font-medium bg-white/10 text-slate-200 border border-white/10 hover:bg-red-500/20 hover:text-red-300 hover:border-red-400/30 transition-colors shadow-sm gap-2"><span class="text-slate-500">#</span>'.htmlspecialchars($name).' <span class="text-slate-500">&times;</span></a>';
                    }
                    ?>
                    <a href="<?= SITE_URL ?>/search.php" class="text-xs text-glass-400 hover:text-glass-300 hover:underline ml-auto mr-2 font-medium">Clear All</a>
                </div>
                <?php endif; ?>

                <?php if (class_exists('HookRegistry')) HookRegistry::doAction('search_results_before_items', $results, $params); ?>

                <?php if (empty($results)): ?>
                    <div class="text-center py-24 bg-white/5 backdrop-blur-md rounded-3xl border border-white/10 border-dashed shadow-[0_4px_16px_rgba(0,0,0,0.2)]">
                        <svg class="mx-auto h-12 w-12 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-white">No matching items found</h3>
                        <p class="mt-1 text-slate-400">Try adjusting your search query or removing filters.</p>
                        <div class="mt-6">
                            <a href="<?= SITE_URL ?>/search.php" class="inline-flex items-center px-4 py-2 border border-white/20 shadow-sm text-sm font-medium rounded-lg text-white bg-white/10 hover:bg-white/20 transition-colors backdrop-blur-sm">
                                Clear search & filters
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 xl:gap-8">
                        <?php foreach ($results as $item): ?>
                            <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="group flex flex-col bg-white/5 backdrop-blur-xl rounded-2xl border border-white/10 shadow-sm hover:shadow-[0_8px_32px_rgba(0,0,0,0.4)] hover:border-glass-400/50 hover:bg-white/10 transition-all duration-300 overflow-hidden transform hover:-translate-y-1">
                                <!-- Image Thumbnail -->
                                <div class="w-full h-56 bg-black/20 relative overflow-hidden flex-shrink-0">
                                    <?php if (!empty($item['preview_file_path'])): ?>
                                        <img src="<?= SITE_URL ?>/uploads/display/<?= rawurlencode($item['preview_file_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-in-out opacity-90 group-hover:opacity-100" loading="lazy">
                                    <?php else: ?>
                                        <div class="w-full h-full flex flex-col items-center justify-center text-slate-500">
                                            <svg class="h-10 w-10 text-slate-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="p-5 flex flex-col flex-1">
                                    <span class="text-[10px] font-bold tracking-widest text-slate-500 uppercase mb-1 block"><?= htmlspecialchars($item['reg_number']) ?></span>
                                    <h3 class="text-lg font-bold text-white group-hover:text-glass-300 transition-colors leading-snug mb-2 line-clamp-2"><?= htmlspecialchars($item['title']) ?></h3>
                                    <p class="text-sm text-slate-400 line-clamp-2 mb-4 flex-1"><?= htmlspecialchars(strip_tags($item['physical_description'] ?? '')) ?></p>
                                    
                                    <div class="flex items-center justify-between text-xs font-medium text-slate-500 mt-auto pt-4 border-t border-white/10">
                                        <span class="flex items-center">
                                            <svg class="mr-1.5 h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            <?= htmlspecialchars($item['production_date'] ?? 'n.d.') ?>
                                        </span>
                                        <?php if (!empty($item['material'])): ?>
                                        <span class="bg-white/10 text-slate-300 px-2 py-0.5 rounded"><?= htmlspecialchars($item['material']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php 
                        $currentPage = $page; 
                        require ThemeManager::getTemplatePath('partials/pagination.php'); 
                    ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

<?php require_once ThemeManager::getFooter(); ?>
