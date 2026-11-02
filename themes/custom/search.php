<?php
require_once __DIR__ . '/partials/archive_helpers.php';
$pageTitle = 'Search Results - ' . SITE_TITLE;
$currentMenu = 'explore';
$bodyClass = 'archive-ui';
$searchParams = $params;
$selectedCategories = $params['category_ids'] ?? [];
$selectedMaterials = $params['materials'] ?? [];
$activeTags = array_unique(array_merge($params['tags'] ?? [], !empty($params['tag']) ? [$params['tag']] : []));
$q = $params['q'] ?? '';
$view = $params['view'] ?? 'grid';
$sort = $params['sort'] ?? 'recent';
$sortLabels = ['recent' => 'Recently Added', 'oldest' => 'Oldest Added', 'title_asc' => 'Title A to Z', 'title_desc' => 'Title Z to A'];
$urlFor = static function (array $changes = [], ?int $targetPage = null) use ($params): string {
    $query = buildQuery(array_replace($params, $changes));
    if ($targetPage !== null && $targetPage > 1) $query .= ($query ? '&' : '') . 'page=' . $targetPage;
    return SITE_URL . '/search.php' . ($query ? '?' . $query : '');
};
$chips = [];
if ($q !== '') $chips[] = ['label' => 'Search: ' . $q, 'url' => $urlFor(['q' => '', 'exact' => false])];
if (!empty($params['category'])) $chips[] = ['label' => $params['category'], 'url' => $urlFor(['category' => ''])];
foreach ($selectedCategories as $categoryId) $chips[] = ['label' => $catNameMap[$categoryId] ?? 'Category', 'url' => buildFilterUrl($params, 'category_ids', $categoryId)];
foreach ($selectedMaterials as $material) $chips[] = ['label' => $material, 'url' => buildFilterUrl($params, 'materials', $material)];
foreach ($activeTags as $tag) $chips[] = ['label' => '#' . ($tagNameMap[$tag] ?? $tag), 'url' => buildFilterUrl($params, 'tags', $tag)];
if (($params['year_start'] ?? null) !== null || ($params['year_end'] ?? null) !== null) {
    $chips[] = ['label' => 'Year: ' . ($params['year_start'] ?? 'Any') . ' to ' . ($params['year_end'] ?? 'Any'), 'url' => $urlFor(['year_start' => null, 'year_end' => null])];
}
if (!empty($params['has_images'])) $chips[] = ['label' => 'Has Images', 'url' => $urlFor(['has_images' => false])];
if (!empty($params['exact'])) $chips[] = ['label' => 'Exact Search', 'url' => $urlFor(['exact' => false])];
$additionalHead = '<script src="' . SITE_URL . '/themes/custom/archive-search.js?v=' . filemtime(__DIR__ . '/archive-search.js') . '" defer></script>';
require_once ThemeManager::getHeader();
?>
<main class="archive-page archive-search">
    <header class="archive-search-heading">
        <div>
            <p class="archive-search-eyebrow">Explore Collections <span></span></p>
            <h1>Search Results</h1>
            <p class="archive-result-count"><?= number_format((int)$totalResults) ?> <?= (int)$totalResults === 1 ? 'item' : 'items' ?> found</p>
        </div>
        <form action="<?= SITE_URL ?>/search.php" method="GET" class="archive-query-form" role="search">
            <?php archiveUiSearchFields($params, ['q']); ?>
            <?= archiveUiIcon('magnifying-glass') ?>
            <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" maxlength="200" data-autocomplete="true" autocomplete="off" aria-label="Search items, dates, or stories" placeholder="Search items, dates, or stories...">
            <button type="submit" aria-label="Search collection"><?= archiveUiIcon('arrow-right') ?></button>
        </form>
    </header>

    <div class="archive-search-layout">
        <div id="archive-filter-home">
            <aside class="archive-filters archive-panel" id="archive-filter-panel" aria-labelledby="refine-title">
                <div class="archive-filter-heading"><h2 id="refine-title"><?= archiveUiIcon('adjustments-horizontal') ?> Refine Search</h2><a href="<?= SITE_URL ?>/search.php">Clear All</a></div>
                <form action="<?= SITE_URL ?>/search.php" method="GET" id="archive-filter-form">
                    <?php archiveUiSearchFields($params, ['category_ids', 'materials', 'has_images', 'year_start', 'year_end', 'tags']); ?>
                    <?php foreach (array_diff($selectedCategories, array_column($facets['categories'] ?? [], 'id')) as $missing): ?><input type="hidden" name="category_ids[]" value="<?= (int)$missing ?>"><?php endforeach; ?>
                    <?php foreach (array_diff($selectedMaterials, array_column($facets['materials'] ?? [], 'name')) as $missing): ?><input type="hidden" name="materials[]" value="<?= htmlspecialchars($missing) ?>"><?php endforeach; ?>
                    <?php foreach (array_diff($activeTags, array_column($facets['tags'] ?? [], 'slug')) as $missing): ?><input type="hidden" name="tags[]" value="<?= htmlspecialchars($missing) ?>"><?php endforeach; ?>
                    <fieldset class="archive-filter-year">
                        <legend><?= archiveUiIcon('calendar-days') ?> Production Year</legend>
                        <div class="archive-year-controls">
                            <input type="number" step="1" name="year_start" aria-label="Production year start" value="<?= htmlspecialchars($params['year_start'] ?? '') ?>" placeholder="<?= htmlspecialchars($facets['year_min'] ?? 'Start') ?>">
                            <span aria-hidden="true">-</span>
                            <input type="number" step="1" name="year_end" aria-label="Production year end" value="<?= htmlspecialchars($params['year_end'] ?? '') ?>" placeholder="End">
                            <button type="submit">Apply</button>
                        </div>
                    </fieldset>

                    <?php if (!empty($facets['categories'])): ?>
                    <details class="archive-filter-group" open>
                        <summary><?= archiveUiIcon('square-3-stack-3d') ?><span>Category</span><?= archiveUiIcon('chevron-down') ?></summary>
                        <div class="archive-filter-options">
                            <?php foreach ($facets['categories'] as $category): ?>
                            <label><input type="checkbox" name="category_ids[]" value="<?= (int)$category['id'] ?>"<?= in_array((int)$category['id'], $selectedCategories, true) ? ' checked' : '' ?>><span><?= htmlspecialchars($category['name']) ?> <small>(<?= number_format((int)$category['facet_count']) ?>)</small></span></label>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <?php endif; ?>

                    <?php if (!empty($facets['materials'])): ?>
                    <details class="archive-filter-group" open>
                        <summary><?= archiveUiIcon('document-text') ?><span>Material</span><?= archiveUiIcon('chevron-down') ?></summary>
                        <div class="archive-filter-options">
                            <?php foreach ($facets['materials'] as $material): ?>
                            <label><input type="checkbox" name="materials[]" value="<?= htmlspecialchars($material['name']) ?>"<?= in_array($material['name'], $selectedMaterials, true) ? ' checked' : '' ?>><span><?= htmlspecialchars($material['name']) ?> <small>(<?= number_format((int)$material['facet_count']) ?>)</small></span></label>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <?php endif; ?>

                    <details class="archive-filter-group" open>
                        <summary><?= archiveUiIcon('photo') ?><span>Media</span><?= archiveUiIcon('chevron-down') ?></summary>
                        <div class="archive-filter-options"><label><input type="checkbox" name="has_images" value="1"<?= !empty($params['has_images']) ? ' checked' : '' ?>><span>Has Images <small>(<?= number_format((int)($facets['has_images'] ?? 0)) ?>)</small></span></label></div>
                    </details>

                    <?php if (!empty($facets['tags'])): ?>
                    <details class="archive-filter-group archive-filter-group--tags" open>
                        <summary><?= archiveUiIcon('tag') ?><span>Tags</span><?= archiveUiIcon('chevron-down') ?></summary>
                        <div class="archive-filter-tags">
                            <?php foreach (array_slice($facets['tags'], 0, 5) as $tag): ?>
                            <label><input type="checkbox" name="tags[]" value="<?= htmlspecialchars($tag['slug']) ?>"<?= in_array($tag['slug'], $activeTags, true) ? ' checked' : '' ?>><span><?= htmlspecialchars($tag['name']) ?> <small>(<?= number_format((int)$tag['facet_count']) ?>)</small></span></label>
                            <?php endforeach; ?>
                            <?php if (count($facets['tags']) > 5): ?>
                            <details class="archive-more-tags"<?= array_intersect($activeTags, array_column(array_slice($facets['tags'], 5), 'slug')) ? ' open' : '' ?>><summary>+ More Tags</summary><div>
                                <?php foreach (array_slice($facets['tags'], 5) as $tag): ?>
                                <label><input type="checkbox" name="tags[]" value="<?= htmlspecialchars($tag['slug']) ?>"<?= in_array($tag['slug'], $activeTags, true) ? ' checked' : '' ?>><span><?= htmlspecialchars($tag['name']) ?> <small>(<?= number_format((int)$tag['facet_count']) ?>)</small></span></label>
                                <?php endforeach; ?>
                            </div></details>
                            <?php endif; ?>
                        </div>
                    </details>
                    <?php endif; ?>
                    <div class="archive-filter-submit"><button type="submit">Apply Filters <?= archiveUiIcon('arrow-right') ?></button></div>
                </form>
            </aside>
        </div>

        <section class="archive-search-results" aria-label="Search results">
            <div class="archive-results-toolbar">
                <button type="button" class="archive-open-filters archive-button" data-open-filters aria-controls="archive-filter-dialog" aria-expanded="false"><?= archiveUiIcon('adjustments-horizontal') ?> Filters<?= $chips ? ' (' . count($chips) . ')' : '' ?></button>
                <nav class="archive-category-tabs archive-panel" aria-label="Quick category filters">
                    <a href="<?= htmlspecialchars($urlFor(['category_ids' => [], 'category' => ''])) ?>"<?= !$selectedCategories && empty($params['category']) ? ' aria-current="true"' : '' ?>>All Items<?= !$selectedCategories && empty($params['category']) ? ' (' . number_format((int)$totalResults) . ')' : '' ?></a>
                    <?php foreach (array_slice($facets['categories'] ?? [], 0, 3) as $category): ?>
                    <a href="<?= htmlspecialchars($urlFor(['category_ids' => [(int)$category['id']], 'category' => ''])) ?>" title="<?= htmlspecialchars($category['name']) ?>"<?= count($selectedCategories) === 1 && in_array((int)$category['id'], $selectedCategories, true) ? ' aria-current="true"' : '' ?>><?= archiveUiIcon('square-3-stack-3d') ?><span><?= htmlspecialchars($category['name']) ?></span><small>(<?= number_format((int)$category['facet_count']) ?>)</small></a>
                    <?php endforeach; ?>
                </nav>
                <div class="archive-results-controls">
                    <form action="<?= SITE_URL ?>/search.php" method="GET" class="archive-sort-form">
                        <?php archiveUiSearchFields($params, ['sort']); ?>
                        <label for="archive-sort">Sort by:</label>
                        <select name="sort" id="archive-sort" aria-label="Sort collection items" data-submit-change>
                            <?php foreach ($sortLabels as $value => $label): ?><option value="<?= $value ?>"<?= $sort === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                        </select>
                        <noscript><button type="submit">Apply</button></noscript>
                    </form>
                    <nav class="archive-view-switch" aria-label="Results layout">
                        <a href="<?= htmlspecialchars($urlFor(['view' => 'grid'], (int)$page)) ?>" aria-label="Grid view"<?= $view === 'grid' ? ' aria-current="true"' : '' ?>><?= archiveUiIcon('squares-2x2') ?></a>
                        <a href="<?= htmlspecialchars($urlFor(['view' => 'list'], (int)$page)) ?>" aria-label="List view"<?= $view === 'list' ? ' aria-current="true"' : '' ?>><?= archiveUiIcon('list-bullet') ?></a>
                    </nav>
                </div>
            </div>

            <?php if ($chips): ?>
            <nav class="archive-search-chips" aria-label="Active search filters">
                <?php foreach ($chips as $chip): ?><a href="<?= htmlspecialchars($chip['url']) ?>" aria-label="Remove filter: <?= htmlspecialchars($chip['label']) ?>"><?= htmlspecialchars($chip['label']) ?> <?= archiveUiIcon('x-mark') ?></a><?php endforeach; ?>
            </nav>
            <?php endif; ?>
            <?php if (!empty($searchMeta['was_corrected'])): ?>
            <div class="archive-search-note"><p>Showing results for <strong><?= htmlspecialchars($searchMeta['corrected_query']) ?></strong> instead of <?= htmlspecialchars($searchMeta['original_query']) ?>.</p><a href="<?= htmlspecialchars($urlFor(['q' => $searchMeta['original_query'], 'exact' => true])) ?>">Search instead for <?= htmlspecialchars($searchMeta['original_query']) ?></a></div>
            <?php endif; ?>
            <?php if (class_exists('HookRegistry')) HookRegistry::doAction('search_results_before_items', $results, $params); ?>

            <?php if ($results): ?>
            <div class="archive-search-grid" data-results-view="<?= htmlspecialchars($view) ?>">
                <?php foreach ($results as $result):
                    $isModule = !empty($result['_module_url']);
                    $resultUrl = $isModule ? $result['_module_url'] : SITE_URL . '/item/' . (int)$result['id'];
                    $resultType = $result['_module_type'] ?? 'Item';
                    $previewPath = trim((string)($result['preview_file_path'] ?? ''));
                    $previewUrl = $previewPath !== '' ? MediaProcessor::url($previewPath, 'card', 'image', $storage ?? null) : ($result['_module_image_url'] ?? '');
                    $resultCategory = $result['category_name'] ?? ($isModule ? $resultType : 'Uncategorized');
                ?>
                <article class="archive-result-card archive-panel">
                    <a class="archive-result-link" href="<?= htmlspecialchars($resultUrl) ?>">
                        <div class="archive-result-image">
                            <?php if ($previewUrl): ?><img src="<?= htmlspecialchars($previewUrl) ?>" alt="<?= htmlspecialchars($result['title'] ?? '') ?>" width="640" height="360" loading="lazy"><?php else: ?><?= archiveUiIcon('photo') ?><?php endif; ?>
                        </div>
                        <div class="archive-result-content">
                            <span class="archive-reg"><?= htmlspecialchars($result['reg_number'] ?? $resultType) ?></span>
                            <h2><?= htmlspecialchars($result['title'] ?? '') ?></h2>
                            <div class="archive-result-meta">
                                <?php if (!empty($result['production_date'])): ?><span><?= archiveUiIcon('calendar-days') ?><?= htmlspecialchars(archiveUiDate($result['production_date'])) ?></span><?php endif; ?>
                                <span class="archive-result-category" title="<?= htmlspecialchars($resultCategory) ?>"><?= archiveUiIcon('square-3-stack-3d') ?><span><?= htmlspecialchars($resultCategory) ?></span></span>
                            </div>
                        </div>
                        <span class="archive-result-arrow archive-round-arrow"><?= archiveUiIcon('arrow-right') ?></span>
                    </a>
                    <?php if (!$isModule): ?><button type="button" class="archive-result-bookmark" data-archive-favorite-id="<?= (int)$result['id'] ?>" data-item-title="<?= htmlspecialchars($result['title'] ?? '') ?>" aria-pressed="false" aria-label="Save <?= htmlspecialchars($result['title'] ?? '') ?> to Favorites"><?= archiveUiIcon('bookmark') ?></button><?php endif; ?>
                    <?php if (!$isModule && class_exists('HookRegistry')) HookRegistry::doAction('item_card_badge', $result); ?>
                </article>
                <?php endforeach; ?>
            </div>
            <p class="archive-search-status" role="status" aria-live="polite" data-search-favorite-status></p>

            <?php if ($totalPages > 1): ?>
            <nav class="archive-search-pagination" aria-label="Search results pagination">
                <?php if ($page > 1): ?><a href="<?= htmlspecialchars($urlFor([], $page - 1)) ?>" aria-label="Previous page"><?= archiveUiIcon('chevron-left') ?></a><?php endif; ?>
                <?php for ($number = max(1, $page - 2); $number <= min((int)$totalPages, $page + 2); $number++): ?>
                    <?php if ($number === (int)$page): ?><span aria-current="page"><?= $number ?></span><?php else: ?><a href="<?= htmlspecialchars($urlFor([], $number)) ?>" aria-label="Page <?= $number ?>"><?= $number ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="<?= htmlspecialchars($urlFor([], $page + 1)) ?>" aria-label="Next page"><?= archiveUiIcon('chevron-right') ?></a><?php endif; ?>
            </nav>
            <p class="archive-page-position">Page <?= (int)$page ?> of <?= number_format((int)$totalPages) ?></p>
            <?php endif; ?>
            <?php else: ?>
            <div class="archive-empty archive-panel"><?= archiveUiIcon('magnifying-glass') ?><h2>No matching items</h2><p>Try another search term or remove a filter.</p><a class="archive-button archive-button--primary" href="<?= SITE_URL ?>/search.php">Clear All Filters</a></div>
            <?php endif; ?>
        </section>
    </div>
</main>
<dialog id="archive-filter-dialog" class="archive-filter-dialog" aria-label="Refine search filters"><header><h2>Filters</h2><button type="button" data-close-filters aria-label="Close filters"><?= archiveUiIcon('x-mark') ?></button></header><div data-filter-dialog-content></div></dialog>
<?php require_once ThemeManager::getFooter(); ?>
