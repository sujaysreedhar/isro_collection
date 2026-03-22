<?php
// themes/dark/search.php
$pageTitle = 'Search Results - ' . SITE_TITLE;
$currentMenu = 'explore';
$searchParams = $params;
$selectedCategories = $params['category_ids'];
$selectedMaterials = $params['materials'];
$q = $params['q'];
$totalResults = count($results);

require_once ThemeManager::getHeader();
?>
    <div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-10 flex flex-col md:flex-row gap-8">

        <!-- Sidebar: Facets -->
        <aside class="w-full md:w-64 flex-shrink-0">
            <div class="sticky top-24">
                <h3 class="text-sm font-bold uppercase tracking-wider text-gray-300 mb-4">Refine Search</h3>

                <!-- Active Filters -->
                <?php $hasActiveFilters = $q || $selectedCategories || $selectedMaterials || !empty($params['tag']) || $params['year_start'] || $params['year_end'] || $params['has_images']; ?>
                <?php if ($hasActiveFilters): ?>
                <div class="mb-5 bg-gray-800 p-3 rounded border border-gray-700 text-sm">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-semibold text-gray-300">Active Filters</h4>
                        <a href="<?= SITE_URL ?>/search.php" class="text-xs text-gray-500 hover:text-red-400 transition">Clear all</a>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <?php if ($q): ?>
                            <?php $noQ = $params; $noQ['q'] = ''; ?>
                            <a href="<?= SITE_URL ?>/search.php?<?= buildQuery($noQ) ?>" class="inline-flex items-center px-2 py-1 rounded bg-purple-600 text-white text-xs hover:bg-red-600 transition">
                                "<?= htmlspecialchars($q) ?>" <span class="ml-1 leading-none">&times;</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($params['year_start'] || $params['year_end']): ?>
                            <?php $noDates = $params; $noDates['year_start'] = null; $noDates['year_end'] = null; ?>
                            <a href="<?= SITE_URL ?>/search.php?<?= buildQuery($noDates) ?>" class="inline-flex items-center px-2 py-1 rounded bg-purple-600 text-white text-xs hover:bg-red-600 transition">
                                Dates: <?= ($params['year_start'] ?? '*') . ' - ' . ($params['year_end'] ?? '*') ?> <span class="ml-1 leading-none">&times;</span>
                            </a>
                        <?php endif; ?>
                        <?php foreach ($selectedCategories as $activeCid): ?>
                            <?php $label = $catNameMap[$activeCid] ?? "Category"; $withoutThis = $params; $withoutThis['category_ids'] = array_values(array_filter($params['category_ids'], fn($id) => $id !== $activeCid)); ?>
                            <a href="<?= SITE_URL ?>/search.php?<?= buildQuery($withoutThis) ?>" class="inline-flex items-center px-2 py-1 rounded bg-purple-600 text-white text-xs hover:bg-red-600 transition">
                                <?= htmlspecialchars($label) ?> <span class="ml-1 leading-none">&times;</span>
                            </a>
                        <?php endforeach; ?>
                        <?php foreach ($selectedMaterials as $mat): ?>
                            <a href="<?= buildFilterUrl($params, 'materials', $mat) ?>" class="inline-flex items-center px-2 py-1 rounded bg-purple-600 text-white text-xs hover:bg-red-600 transition">
                                <?= htmlspecialchars($mat) ?> <span class="ml-1 leading-none">&times;</span>
                            </a>
                        <?php endforeach; ?>
                        <?php if ($params['has_images']): ?>
                            <?php $noImg = $params; $noImg['has_images'] = false; ?>
                            <a href="<?= SITE_URL ?>/search.php?<?= buildQuery($noImg) ?>" class="inline-flex items-center px-2 py-1 rounded bg-purple-600 text-white text-xs hover:bg-red-600 transition">
                                Has Images <span class="ml-1 leading-none">&times;</span>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($params['tag'])): ?>
                            <a href="<?= buildFilterUrl($params, 'tag', $params['tag']) ?>" class="inline-flex items-center px-2 py-1 rounded bg-purple-600 text-white text-xs hover:bg-red-600 transition">
                                #<?= htmlspecialchars($tagNameMap[$params['tag']] ?? 'Tag') ?> <span class="ml-1 leading-none">&times;</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Date Range -->
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-300 mb-2 border-b border-gray-700 pb-2">Production Year</h4>
                    <form action="<?= SITE_URL ?>/search.php" method="GET" class="flex items-center gap-2 mt-3">
                        <?php if ($q): ?><input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"><?php endif; ?>
                        <?php if (!empty($params['tag'])): ?><input type="hidden" name="tag" value="<?= htmlspecialchars($params['tag']) ?>"><?php endif; ?>
                        <?php foreach($selectedCategories as $cid): ?><input type="hidden" name="category_ids[]" value="<?= htmlspecialchars($cid) ?>"><?php endforeach; ?>
                        <?php foreach($selectedMaterials as $m): ?><input type="hidden" name="materials[]" value="<?= htmlspecialchars($m) ?>"><?php endforeach; ?>
                        <?php if ($params['has_images']): ?><input type="hidden" name="has_images" value="1"><?php endif; ?>
                        <input type="number" name="year_start" value="<?= htmlspecialchars($params['year_start'] ?? '') ?>" placeholder="<?= $facets['year_min'] ?? 'Start' ?>" class="w-full min-w-0 border-gray-600 bg-gray-800 text-gray-200 rounded text-sm px-2 py-1 focus:border-purple-500 focus:ring-0">
                        <span class="text-gray-500">-</span>
                        <input type="number" name="year_end" value="<?= htmlspecialchars($params['year_end'] ?? '') ?>" placeholder="<?= $facets['year_max'] ?? 'End' ?>" class="w-full min-w-0 border-gray-600 bg-gray-800 text-gray-200 rounded text-sm px-2 py-1 focus:border-purple-500 focus:ring-0">
                        <button type="submit" class="bg-purple-600 text-white rounded px-2 py-1 text-xs font-bold hover:bg-purple-700">Go</button>
                    </form>
                </div>

                <!-- Category Facet -->
                <?php if (!empty($facets['categories'])): ?>
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-300 mb-2 border-b border-gray-700 pb-2">Category</h4>
                    <div class="space-y-1.5 mt-3">
                        <?php foreach ($facets['categories'] as $cat): ?>
                            <?php $isChecked = in_array((int)$cat['id'], $selectedCategories); ?>
                            <a href="<?= buildFilterUrl($params, 'category_ids', $cat['id']) ?>"
                               class="flex items-center group cursor-pointer <?= $isChecked ? 'text-white font-semibold' : 'text-gray-400 hover:text-white' ?>">
                                <span class="w-4 h-4 flex-shrink-0 inline-flex justify-center items-center border
                                    <?= $isChecked ? 'bg-purple-600 border-purple-600 text-white' : 'border-gray-600 bg-gray-800 group-hover:border-gray-400' ?>
                                    rounded mr-2 transition-colors">
                                    <?php if ($isChecked): ?>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    <?php endif; ?>
                                </span>
                                <span class="text-sm"><?= htmlspecialchars($cat['name']) ?> <span class="text-gray-500 font-normal">(<?= $cat['facet_count'] ?>)</span></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Materials Facet -->
                <?php if (!empty($facets['materials'])): ?>
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-300 mb-2 border-b border-gray-700 pb-2">Material</h4>
                    <div class="space-y-1.5 mt-3">
                        <?php foreach ($facets['materials'] as $mat): ?>
                            <?php $isChecked = in_array($mat['name'], $selectedMaterials); ?>
                            <a href="<?= buildFilterUrl($params, 'materials', $mat['name']) ?>"
                               class="flex items-center group cursor-pointer <?= $isChecked ? 'text-white font-semibold' : 'text-gray-400 hover:text-white' ?>">
                                <span class="w-4 h-4 flex-shrink-0 inline-flex justify-center items-center border
                                    <?= $isChecked ? 'bg-purple-600 border-purple-600 text-white' : 'border-gray-600 bg-gray-800 group-hover:border-gray-400' ?>
                                    rounded mr-2 transition-colors">
                                    <?php if ($isChecked): ?>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    <?php endif; ?>
                                </span>
                                <span class="text-sm"><?= htmlspecialchars($mat['name']) ?> <span class="text-gray-500 font-normal">(<?= $mat['facet_count'] ?>)</span></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Has Images -->
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-300 mb-2 border-b border-gray-700 pb-2">Media</h4>
                    <div class="space-y-1.5 mt-3">
                        <a href="<?= buildFilterUrl($params, 'has_images', '1') ?>"
                           class="flex items-center group cursor-pointer <?= $params['has_images'] ? 'text-white font-semibold' : 'text-gray-400 hover:text-white' ?>">
                            <span class="w-4 h-4 flex-shrink-0 inline-flex justify-center items-center border
                                <?= $params['has_images'] ? 'bg-purple-600 border-purple-600 text-white' : 'border-gray-600 bg-gray-800 group-hover:border-gray-400' ?>
                                rounded mr-2 transition-colors">
                                <?php if ($params['has_images']): ?>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                <?php endif; ?>
                            </span>
                            <span class="text-sm">Has Images <span class="text-gray-500 font-normal">(<?= $facets['has_images'] ?>)</span></span>
                        </a>
                    </div>
                </div>

                <!-- Tags -->
                <?php if (!empty($facets['tags'])): ?>
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-300 mb-2 border-b border-gray-700 pb-2">Tags</h4>
                    <div class="flex flex-wrap gap-1.5 mt-3">
                        <?php foreach ($facets['tags'] as $tagFacet): ?>
                            <?php $isActiveTag = ($params['tag'] === $tagFacet['slug']); ?>
                            <a href="<?= buildFilterUrl($params, 'tag', $tagFacet['slug']) ?>"
                               class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium transition-colors
                                   <?= $isActiveTag ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-400 border border-gray-700 hover:bg-gray-700 hover:text-white' ?>">
                                <span class="mr-0.5 text-gray-500">#</span>
                                <?= htmlspecialchars($tagFacet['name']) ?>
                                <span class="ml-1 text-gray-500 font-normal">(<?= $tagFacet['facet_count'] ?>)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Main Results -->
        <main class="flex-1 min-w-0">
            <div class="mb-6 flex justify-between items-end border-b border-gray-700 pb-4">
                <h1 class="text-3xl font-bold text-white flex items-center gap-3">
                    <?php if ($q): ?>
                        <span>Results for "<?= htmlspecialchars($searchMeta['corrected_query'] ?? $q) ?>"</span>
                    <?php else: ?>
                        All Items
                    <?php endif; ?>
                </h1>
                <span class="text-gray-500 text-sm"><?= $totalResults ?> result(s)</span>
            </div>

            <?php if (!empty($searchMeta['was_corrected']) && $searchMeta['was_corrected']): ?>
            <div class="mb-6 p-4 bg-yellow-900/30 border border-yellow-700 rounded-lg flex items-start gap-3">
                <svg class="w-5 h-5 text-yellow-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <p class="text-sm text-yellow-300">Showing results for <strong><?= htmlspecialchars($searchMeta['corrected_query']) ?></strong> instead of <?= htmlspecialchars($searchMeta['original_query']) ?>.</p>
                    <a href="<?= SITE_URL ?>/search.php?<?= buildQuery(array_merge($params, ['q' => $searchMeta['original_query'], 'exact' => 1])) ?>" class="text-sm text-purple-400 hover:text-purple-300 hover:underline mt-1 inline-block">
                        Search instead for <?= htmlspecialchars($searchMeta['original_query']) ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (class_exists('HookRegistry')) HookRegistry::doAction('search_results_before_items', $results, $params); ?>

            <?php if ($results): ?>
                <?php
                    $resultTags = [];
                    $rids = array_column($results, 'id');
                    if ($rids) {
                        $ph = implode(',', array_fill(0, count($rids), '?'));
                        $rtStmt = $pdo->prepare("SELECT it.item_id, t.name, t.slug FROM item_tag it INNER JOIN tags t ON it.tag_id = t.id WHERE it.item_id IN ({$ph}) ORDER BY t.name ASC");
                        $rtStmt->execute($rids);
                        foreach ($rtStmt->fetchAll() as $r) {
                            $resultTags[$r['item_id']][] = $r;
                        }
                    }
                ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
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
                           class="group block border border-gray-700 rounded-lg overflow-hidden hover:border-gray-600 hover:shadow-lg hover:shadow-purple-900/20 transition bg-gray-800 flex flex-col">
                            <div class="h-48 bg-gray-900 flex items-center justify-center p-4">
                                <?php if ($previewUrl): ?>
                                    <img src="<?= htmlspecialchars($previewUrl) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="object-cover w-full h-full">
                                <?php else: ?>
                                    <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <?php endif; ?>
                            </div>
                            <div class="p-4 flex flex-col flex-grow">
                                <div class="flex justify-between items-start mb-1">
                                    <div class="text-xs font-bold text-gray-500"><?= htmlspecialchars($item['reg_number']) ?></div>
                                    <?php if (!empty($item['material'])): ?>
                                        <div class="text-[10px] bg-gray-700 text-gray-400 px-1.5 py-0.5 rounded border border-gray-600"><?= htmlspecialchars($item['material']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <h3 class="font-bold text-lg text-white group-hover:text-purple-400 transition line-clamp-2"><?= htmlspecialchars($item['title']) ?></h3>
                                <div class="text-xs text-gray-500 mt-2"><?= htmlspecialchars($item['production_date'] ?? 'n.d.') ?></div>
                                <?php $rTags = $resultTags[$item['id']] ?? []; ?>
                                <?php if ($rTags): ?>
                                <div class="flex flex-wrap gap-1 mt-3">
                                    <?php foreach (array_slice($rTags, 0, 3) as $rt): ?>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-gray-700 text-gray-400 border border-gray-600">
                                            <span class="mr-0.5 text-gray-500">#</span><?= htmlspecialchars($rt['name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($rTags) > 3): ?>
                                        <span class="text-xs text-gray-500">+<?= count($rTags) - 3 ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-20 bg-gray-800 border border-gray-700 rounded-lg">
                    <svg class="mx-auto h-12 w-12 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-300">No matching items</h3>
                    <p class="mt-1 text-sm text-gray-500">Try adjusting your search or clearing your filters.</p>
                    <div class="mt-6">
                        <a href="<?= SITE_URL ?>/search.php" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">Clear all filters</a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

<?php require_once ThemeManager::getFooter(); ?>
