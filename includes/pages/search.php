<?php
require_once __DIR__ . '/../SearchEngine.php';
global $pdo;

$searchEngine = new SearchEngine($pdo);

// Extract parameters
$params = [
    'q'            => trim($_GET['q'] ?? ''),
    'category_ids' => array_values(array_filter(array_map('intval', (array)($_GET['category_ids'] ?? [])))),
    'category'     => trim($_GET['category'] ?? ''),
    'materials'    => array_values(array_filter(array_map('trim', (array)($_GET['materials'] ?? [])))),
    'has_images'   => isset($_GET['has_images']) && $_GET['has_images'] === '1',
    'exact'        => isset($_GET['exact']) && $_GET['exact'] === '1',
    'tags'         => array_values(array_filter(array_map('trim', (array)($_GET['tags'] ?? [])))),
    'tag'          => trim($_GET['tag'] ?? ''),
    'year_start'   => (isset($_GET['year_start']) && is_numeric($_GET['year_start'])) ? (int)$_GET['year_start'] : null,
    'year_end'     => (isset($_GET['year_end']) && is_numeric($_GET['year_end'])) ? (int)$_GET['year_end'] : null,
];

// Pagination
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$params['limit'] = $perPage;
$params['offset'] = $offset;

$searchData = $searchEngine->search($params);
$results    = $searchData['results'];
$totalResults = $searchData['total_results'] ?? count($results);
$totalPages = ceil($totalResults / $perPage);
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
    
    // For tags (array-based toggling)
    if ($key === 'tags') {
        $tgs = $p['tags'] ?? [];
        if (!empty($p['tag'])) {
            $tgs[] = $p['tag']; // migrate legacy single-tag
            unset($p['tag']);
        }
        if (in_array((string)$value, $tgs)) {
            $tgs = array_values(array_filter($tgs, fn($t) => $t !== (string)$value));
        } else {
            $tgs[] = (string)$value;
        }
        $p['tags'] = $tgs;
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
    if (!empty($p['tag']))         $out[] = 'tags[]=' . urlencode($p['tag']);
    if (!empty($p['tags'])) {
        foreach (array_unique($p['tags']) as $t) {
            $out[] = 'tags[]=' . urlencode($t);
        }
    }
    return implode('&', $out);
}

// Build lookup maps
$catNameMap = array_column($facets['categories'], 'name', 'id');
$tagNameMap = array_column($facets['tags'] ?? [], 'name', 'slug');

?>
<?php require_once ThemeManager::getTemplatePath('search.php'); ?>
