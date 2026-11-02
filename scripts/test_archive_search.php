<?php
/** Run with PHP CLI. Uses only a fresh in-memory SQLite database. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/archive_search_fixture.php';
require_once __DIR__ . '/../includes/Autoloader.php';
require_once __DIR__ . '/../includes/frontend.php';
define('SITE_URL', 'http://example.test');
define('SITE_TITLE', 'Archive UI test');
define('CACHE_DIR', dirname(__DIR__) . '/includes/cache');
$appSettings = ['active_theme' => 'custom', 'enable_cache' => '0'];
AppConfig::load($appSettings);
HookRegistry::reset();
$pdo = archiveSearchFixturePdo();
$engine = new SearchEngine($pdo);
function archiveSearchCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$first = $engine->search(['limit' => 20, 'offset' => 0]);
$second = $engine->search(['limit' => 20, 'offset' => 20]);
archiveSearchCheck($first['total_results'] === 25 && count($first['results']) === 20 && count($second['results']) === 5, 'Public total or page boundary is incorrect.');
archiveSearchCheck($first['results'][0]['id'] === 25 && $second['results'][0]['id'] === 5, 'Default order or page boundary changed.');
$ascendingFirst = $engine->search(['sort' => 'title_asc', 'limit' => 20, 'offset' => 0]);
$ascendingSecond = $engine->search(['sort' => 'title_asc', 'limit' => 20, 'offset' => 20]);
$combined = array_merge($ascendingFirst['results'], $ascendingSecond['results']);
$titles = array_column($combined, 'title');
$expectedTitles = $titles;
sort($expectedTitles, SORT_STRING);
archiveSearchCheck($titles === $expectedTitles && count(array_unique(array_column($combined, 'id'))) === 25, 'Title sorting is not global across pages.');
$oldest = $engine->search(['sort' => 'oldest', 'limit' => 2, 'offset' => 0]);
archiveSearchCheck(array_column($oldest['results'], 'id') === [1, 2], 'Oldest sort failed.');
$descending = $engine->search(['sort' => 'title_desc', 'limit' => 20, 'offset' => 0]);
archiveSearchCheck($descending['results'][0]['title'] === $expectedTitles[24], 'Descending title sort failed.');
$filtered = $engine->search(['category_ids' => [2], 'materials' => ['Paper, Ink'], 'tags' => ['karnataka'], 'has_images' => true, 'year_start' => 2025, 'year_end' => 2025, 'sort' => 'oldest', 'limit' => 20, 'offset' => 0]);
archiveSearchCheck(array_column($filtered['results'], 'id') === [1, 13, 25], 'Combined filters changed or were dropped.');
archiveSearchCheck(SearchEngine::validateParams(['sort' => 'i.id; DROP TABLE items']) !== null, 'Untrusted sort expression accepted.');
archiveSearchCheck(SearchEngine::validateParams(['view' => ['list']]) !== null, 'Invalid view accepted.');
$storage = null;
$_GET = ['sort' => 'title_asc', 'view' => 'list', 'page' => '2'];
ob_start();
require __DIR__ . '/../includes/pages/search.php';
$html = ob_get_clean();
archiveSearchCheck(str_contains($html, '25 items found') && str_contains($html, 'data-results-view="list"'), 'Controller total or list view was lost.');
archiveSearchCheck(substr_count($html, 'class="archive-result-card archive-panel"') === 5, 'Wrong number of rendered cards on page 2.');
$query = buildQuery(['q' => 'a & b', 'category' => 'category-2', 'category_ids' => [2], 'materials' => ['Paper, Ink'], 'tags' => ['karnataka'], 'has_images' => true, 'exact' => true, 'year_start' => 2024, 'year_end' => 2026, 'sort' => 'title_desc', 'view' => 'list']);
parse_str($query, $roundTrip);
archiveSearchCheck($roundTrip['q'] === 'a & b' && $roundTrip['category'] === 'category-2' && $roundTrip['sort'] === 'title_desc' && $roundTrip['view'] === 'list' && $roundTrip['materials'] === ['Paper, Ink'] && $roundTrip['tags'] === ['karnataka'], 'Search state was lost in navigation URLs.');
echo "PASS: global sorting, pagination, combined filters, input validation and Custom search rendering.\n";
