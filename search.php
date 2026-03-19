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
<?php require_once ThemeManager::getTemplatePath('search.php'); ?>
