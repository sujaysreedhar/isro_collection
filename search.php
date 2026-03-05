<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/SearchEngine.php';

$searchEngine = new SearchEngine($pdo);

// Extract parameters — category_ids is now an array
$params = [
    'q'            => trim($_GET['q'] ?? ''),
    'category_ids' => array_values(array_filter(array_map('intval', (array)($_GET['category_ids'] ?? [])))),
    'has_images'   => isset($_GET['has_images']) && $_GET['has_images'] === '1',
    'exact'        => isset($_GET['exact']) && $_GET['exact'] === '1',
    'tag'          => trim($_GET['tag'] ?? ''),
];

$searchData = $searchEngine->search($params);
$results    = $searchData['results'];
$facets     = $searchData['facets'];
$searchMeta = $searchData['search_meta'] ?? null;

/**
 * Build a URL that toggles one category id in/out of the category_ids array,
 * or toggles has_images on/off, while preserving all other active params.
 */
function buildFilterUrl(array $currentParams, string $key, $value): string {
    $p = $currentParams;

    if ($key === 'category_ids') {
        $ids = $p['category_ids'] ?? [];
        if (in_array((int)$value, $ids)) {
            $ids = array_values(array_filter($ids, fn($id) => $id !== (int)$value)); // remove
        } else {
            $ids[] = (int)$value;                                                     // add
        }
        $p['category_ids'] = $ids;
    }

    if ($key === 'has_images') {
        $p['has_images'] = $p['has_images'] ? null : '1';
    }

    return SITE_URL . '/search.php?' . buildQuery($p);
}

/** Serialize params cleanly — drops nulls/falses and expands arrays. */
function buildQuery(array $p): string {
    $out = [];
    if (!empty($p['q']))           $out[] = 'q=' . urlencode($p['q']);
    if (!empty($p['category_ids'])) {
        foreach (array_unique($p['category_ids']) as $id) {
            $out[] = 'category_ids[]=' . (int)$id;
        }
    }
    if (!empty($p['has_images']))  $out[] = 'has_images=1';
    if (!empty($p['exact']))       $out[] = 'exact=1';
    if (!empty($p['tag']))         $out[] = 'tag=' . urlencode($p['tag']);
    return implode('&', $out);
}

// Build a category id→name lookup for the active filter chips
$catNameMap = array_column($facets['categories'], 'name', 'id');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - <?= SITE_TITLE ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        h1, h2, h3, h4, .serif { font-family: 'Playfair Display', serif; color: #111827; }
    </style>
</head>
<body class="text-gray-800 antialiased flex flex-col min-h-screen">

    <!-- Global Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="<?= SITE_URL ?>" class="text-2xl font-bold serif tracking-tight flex-shrink-0"><?= SITE_TITLE ?></a>
            <div class="flex-1 max-w-2xl ml-8">
                <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <!-- Preserve active filters in the search form -->
                    <?php foreach ($params['category_ids'] as $cid): ?>
                        <input type="hidden" name="category_ids[]" value="<?= $cid ?>">
                    <?php endforeach; ?>
                    <?php if ($params['has_images']): ?>
                        <input type="hidden" name="has_images" value="1">
                    <?php endif; ?>
                    <?php if (!empty($params['tag'])): ?>
                        <input type="hidden" name="tag" value="<?= htmlspecialchars($params['tag']) ?>">
                    <?php endif; ?>
                    <input type="text" name="q" value="<?= htmlspecialchars($params['q']) ?>"
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md bg-gray-50 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 sm:text-sm"
                           placeholder="Search the collections...">
                </form>
            </div>
            <nav class="hidden lg:flex space-x-8 ml-8 flex-shrink-0">
                <a href="<?= SITE_URL ?>/gallery.php" class="text-gray-500 hover:text-gray-900 font-medium text-sm">Gallery</a>
            </nav>
        </div>
    </header>

    <div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-10 flex flex-col md:flex-row gap-8">

        <!-- Sidebar: Facets -->
        <aside class="w-full md:w-64 flex-shrink-0">
            <div class="sticky top-24">
                <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 mb-4">Refine Search</h3>

                <!-- Active Filters chips -->
                <?php $hasActiveFilters = !empty($params['q']) || !empty($params['category_ids']) || $params['has_images'] || !empty($params['tag']); ?>
                <?php if ($hasActiveFilters): ?>
                <div class="mb-5 bg-gray-100 p-3 rounded text-sm">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-semibold text-gray-700">Active Filters</h4>
                        <a href="<?= SITE_URL ?>/search.php" class="text-xs text-gray-400 hover:text-red-600 transition">Clear all</a>
                    </div>
                    <div class="flex flex-wrap gap-1.5">

                        <?php if (!empty($params['q'])): ?>
                            <?php $noQ = $params; $noQ['q'] = ''; ?>
                            <a href="<?= SITE_URL ?>/search.php?<?= buildQuery($noQ) ?>"
                               class="inline-flex items-center px-2 py-1 rounded bg-gray-800 text-white text-xs hover:bg-red-600 transition">
                                "<?= htmlspecialchars($params['q']) ?>"
                                <span class="ml-1 leading-none">&times;</span>
                            </a>
                        <?php endif; ?>

                        <?php foreach ($params['category_ids'] as $activeCid): ?>
                            <?php
                                $label = $catNameMap[$activeCid] ?? "Category {$activeCid}";
                                $withoutThis = $params;
                                $withoutThis['category_ids'] = array_values(array_filter($params['category_ids'], fn($id) => $id !== $activeCid));
                            ?>
                            <a href="<?= SITE_URL ?>/search.php?<?= buildQuery($withoutThis) ?>"
                               class="inline-flex items-center px-2 py-1 rounded bg-gray-800 text-white text-xs hover:bg-red-600 transition">
                                <?= htmlspecialchars($label) ?>
                                <span class="ml-1 leading-none">&times;</span>
                            </a>
                        <?php endforeach; ?>

                        <?php if ($params['has_images']): ?>
                            <?php $noImg = $params; $noImg['has_images'] = false; ?>
                            <a href="<?= SITE_URL ?>/search.php?<?= buildQuery($noImg) ?>"
                               class="inline-flex items-center px-2 py-1 rounded bg-gray-800 text-white text-xs hover:bg-red-600 transition">
                                Has Images
                                <span class="ml-1 leading-none">&times;</span>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($params['tag'])): ?>
                            <?php $noTag = $params; $noTag['tag'] = ''; ?>
                            <a href="<?= SITE_URL ?>/search.php?<?= buildQuery($noTag) ?>"
                               class="inline-flex items-center px-2 py-1 rounded bg-gray-800 text-white text-xs hover:bg-red-600 transition">
                                #<?= htmlspecialchars($params['tag']) ?>
                                <span class="ml-1 leading-none">&times;</span>
                            </a>
                        <?php endif; ?>

                    </div>
                </div>
                <?php endif; ?>

                <!-- Category Facet (multi-select checkboxes) -->
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-800 mb-2 border-b border-gray-200 pb-2">Category</h4>
                    <div class="space-y-1.5 mt-3">
                        <?php foreach ($facets['categories'] as $cat): ?>
                            <?php $isChecked = in_array((int)$cat['id'], $params['category_ids']); ?>
                            <a href="<?= buildFilterUrl($params, 'category_ids', $cat['id']) ?>"
                               class="flex items-center group cursor-pointer <?= $isChecked ? 'text-gray-900 font-semibold' : 'text-gray-600 hover:text-gray-900' ?>">
                                <span class="w-4 h-4 flex-shrink-0 inline-flex justify-center items-center border
                                    <?= $isChecked ? 'bg-gray-900 border-gray-900 text-white' : 'border-gray-300 bg-white group-hover:border-gray-500' ?>
                                    rounded mr-2 transition-colors">
                                    <?php if ($isChecked): ?>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    <?php endif; ?>
                                </span>
                                <span class="text-sm"><?= htmlspecialchars($cat['name']) ?> <span class="text-gray-400 font-normal">(<?= $cat['facet_count'] ?>)</span></span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($facets['categories'])): ?>
                            <p class="text-sm text-gray-400 italic">No categories match.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Has Images Facet -->
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-800 mb-2 border-b border-gray-200 pb-2">Media</h4>
                    <div class="space-y-1.5 mt-3">
                        <a href="<?= buildFilterUrl($params, 'has_images', '1') ?>"
                           class="flex items-center group cursor-pointer <?= $params['has_images'] ? 'text-gray-900 font-semibold' : 'text-gray-600 hover:text-gray-900' ?>">
                            <span class="w-4 h-4 flex-shrink-0 inline-flex justify-center items-center border
                                <?= $params['has_images'] ? 'bg-gray-900 border-gray-900 text-white' : 'border-gray-300 bg-white group-hover:border-gray-500' ?>
                                rounded mr-2 transition-colors">
                                <?php if ($params['has_images']): ?>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                <?php endif; ?>
                            </span>
                            <span class="text-sm">Has Images <span class="text-gray-400 font-normal">(<?= $facets['has_images'] ?>)</span></span>
                        </a>
                    </div>
                </div>

                <!-- Tag Facet -->
                <?php if (!empty($facets['tags'])): ?>
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-800 mb-2 border-b border-gray-200 pb-2">Tags</h4>
                    <div class="flex flex-wrap gap-1.5 mt-3">
                        <?php foreach ($facets['tags'] as $tagFacet): ?>
                            <?php $isActiveTag = ($params['tag'] === $tagFacet['slug']); ?>
                            <?php
                                $tagUrl = $params;
                                $tagUrl['tag'] = $isActiveTag ? '' : $tagFacet['slug'];
                            ?>
                            <a href="<?= SITE_URL ?>/search.php?<?= buildQuery($tagUrl) ?>"
                               class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium transition-colors
                                   <?= $isActiveTag
                                       ? 'bg-gray-900 text-white'
                                       : 'bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-gray-900' ?>">
                                <span class="mr-0.5 <?= $isActiveTag ? 'text-gray-400' : 'text-gray-400' ?>">#</span>
                                <?= htmlspecialchars($tagFacet['name']) ?>
                                <span class="ml-1 text-gray-400 font-normal">(<?= $tagFacet['facet_count'] ?>)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </aside>

        <!-- Main Results -->
        <main class="flex-1 min-w-0">
            <div class="mb-6 flex justify-between items-end border-b border-gray-200 pb-4">
                <h1 class="text-3xl font-bold serif flex items-center gap-3">
                    <?php if (!empty($params['q'])): ?>
                        <span>Results for "<?= htmlspecialchars($searchMeta['corrected_query'] ?? $params['q']) ?>"</span>
                    <?php else: ?>
                        All Items
                    <?php endif; ?>
                </h1>
                <span class="text-gray-500 text-sm"><?= count($results) ?> result(s)</span>
            </div>

            <?php if (!empty($searchMeta['was_corrected']) && $searchMeta['was_corrected']): ?>
            <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg flex items-start gap-3">
                <svg class="w-5 h-5 text-yellow-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <p class="text-sm text-yellow-800">
                        Showing results for <strong><?= htmlspecialchars($searchMeta['corrected_query']) ?></strong> instead of <?= htmlspecialchars($searchMeta['original_query']) ?>.
                    </p>
                    <?php
                        // Build URL to search for original query exactly (no correction)
                        // Note: For a real implementation we'd probably want a ?exact=1 flag 
                        // but since the original query spelling will be sent, the spelling corrector
                        // will normally correct it again. To fix this fully, search engine needs an bypass flag.
                        // We will implement `&exact=1` behavior down the line, but for now we link back.
                    ?>
                    <a href="<?= SITE_URL ?>/search.php?<?= buildQuery(array_merge($params, ['q' => $searchMeta['original_query'], 'exact' => 1])) ?>" class="text-sm text-blue-600 hover:text-blue-800 hover:underline mt-1 inline-block">
                        Search instead for <?= htmlspecialchars($searchMeta['original_query']) ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($results): ?>
                <?php
                    // Batch-fetch tags for all result items
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
                                    $displayPath = __DIR__ . '/uploads/display/' . $previewPath;
                                    $previewUrl = file_exists($displayPath)
                                        ? SITE_URL . '/uploads/display/' . rawurlencode($previewPath)
                                        : SITE_URL . '/uploads/originals/' . rawurlencode($previewPath);
                                }
                            }
                        ?>
                        <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>"
                           class="group block border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition bg-white flex flex-col">
                            <div class="h-48 bg-gray-100 flex items-center justify-center p-4">
                                <?php if ($previewUrl): ?>
                                    <img src="<?= htmlspecialchars($previewUrl) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="object-cover w-full h-full">
                                <?php else: ?>
                                    <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <?php endif; ?>
                            </div>
                            <div class="p-4 flex flex-col flex-grow">
                                <div class="text-xs font-bold text-gray-500 mb-1"><?= htmlspecialchars($item['reg_number']) ?></div>
                                <h3 class="font-bold serif text-lg text-gray-900 group-hover:text-blue-800 transition line-clamp-2"><?= htmlspecialchars($item['title']) ?></h3>
                                <?php $rTags = $resultTags[$item['id']] ?? []; ?>
                                <?php if ($rTags): ?>
                                <div class="flex flex-wrap gap-1 mt-2">
                                    <?php foreach (array_slice($rTags, 0, 3) as $rt): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">
                                            <span class="mr-0.5 text-gray-400">#</span><?= htmlspecialchars($rt['name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($rTags) > 3): ?>
                                        <span class="text-xs text-gray-400">+<?= count($rTags) - 3 ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-20 bg-white border border-gray-200 rounded-lg">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No matching items</h3>
                    <p class="mt-1 text-sm text-gray-500">Try adjusting your search or clearing your filters.</p>
                    <div class="mt-6">
                        <a href="<?= SITE_URL ?>/search.php" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800">
                            Clear all filters
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white mt-auto py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center text-sm text-gray-400">
            <p>&copy; <?= date('Y') ?> <?= SITE_TITLE ?>. All rights reserved.</p>
            <div class="flex space-x-6">
                <a href="#" class="hover:text-white transition-colors">Privacy</a>
                <a href="#" class="hover:text-white transition-colors">Terms</a>
            </div>
        </div>
    </footer>

</body>
</html>
