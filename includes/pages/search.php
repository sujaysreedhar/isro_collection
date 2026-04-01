<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../SearchEngine.php';
global $pdo;

$searchEngine = new SearchEngine($pdo);

// Extract parameters
$params = [
    'q'            => trim($_GET['q'] ?? ''),
    'category_ids' => array_values(array_filter(array_map('intval', (array)($_GET['category_ids'] ?? [])))),
    'materials'    => array_values(array_filter(array_map('trim', (array)($_GET['materials'] ?? [])))),
    'has_images'   => isset($_GET['has_images']) && $_GET['has_images'] === '1',
    'exact'        => isset($_GET['exact']) && $_GET['exact'] === '1',
    'tag'          => trim($_GET['tag'] ?? ''),
    'year_start'   => (isset($_GET['year_start']) && is_numeric($_GET['year_start'])) ? (int)$_GET['year_start'] : null,
    'year_end'     => (isset($_GET['year_end']) && is_numeric($_GET['year_end'])) ? (int)$_GET['year_end'] : null,
];

$searchData = $searchEngine->search($params);
$results    = $searchData['results'];
$facets     = $searchData['facets'];
$searchMeta = $searchData['search_meta'] ?? null;

/**
 * Build a URL that toggles one filter value in/out of the arrays,
 * or sets a scalar filter, while preserving all other active params.
 */
function buildFilterUrl(array $currentParams, string $key, $value): string {
    $p = $currentParams;

    if ($key === 'category_ids') {
        $ids = $p['category_ids'] ?? [];
        if (in_array((int)$value, $ids)) {
            $ids = array_values(array_filter($ids, fn($id) => $id !== (int)$value));
        } else {
            $ids[] = (int)$value;
        }
        $p['category_ids'] = $ids;
    }

    if ($key === 'materials') {
        $mats = $p['materials'] ?? [];
        if (in_array((string)$value, $mats)) {
            $mats = array_values(array_filter($mats, fn($m) => $m !== (string)$value));
        } else {
            $mats[] = (string)$value;
        }
        $p['materials'] = $mats;
    }

    if ($key === 'has_images') {
        $p['has_images'] = $p['has_images'] ? null : '1';
    }
    
    // For single tags (kept as single for simplicity, logic can easily expand to multi)
    if ($key === 'tag') {
        $p['tag'] = ($p['tag'] === $value) ? '' : $value;
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
    if (!empty($p['materials'])) {
        foreach (array_unique($p['materials']) as $m) {
            $out[] = 'materials[]=' . urlencode($m);
        }
    }
    if ($p['year_start'] !== null) $out[] = 'year_start=' . $p['year_start'];
    if ($p['year_end'] !== null)   $out[] = 'year_end=' . $p['year_end'];
    if (!empty($p['has_images']))  $out[] = 'has_images=1';
    if (!empty($p['exact']))       $out[] = 'exact=1';
    if (!empty($p['tag']))         $out[] = 'tag=' . urlencode($p['tag']);
    return implode('&', $out);
}

// Build lookup maps
$catNameMap = array_column($facets['categories'], 'name', 'id');
$tagNameMap = array_column($facets['tags'] ?? [], 'name', 'slug');

?>
<?php require_once ThemeManager::getTemplatePath('search.php'); ?>
