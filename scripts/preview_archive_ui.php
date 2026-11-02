<?php
/**
 * Isolated visual fixture; does not bootstrap the app or connect to a database.
 * Run: ARCHIVE_UI_PREVIEW=1 php -S 127.0.0.1:8093 -t . scripts/preview_archive_ui.php
 * Only runs under PHP's development server with the explicit environment flag.
 */
if (PHP_SAPI !== 'cli-server' || getenv('ARCHIVE_UI_PREVIEW') !== '1') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/archive-preview-dark.css') {
    header('Content-Type: text/css');
    echo str_replace('@media (prefers-color-scheme: dark)', '@media screen', file_get_contents($root . '/themes/custom/archive.css'));
    return;
}
if (preg_match('#^/(themes/|uploads/|includes/cache/assets/)#', $path)) return false;
if ($path === '/ajax_search.php') {
    header('Content-Type: application/json');
    echo '[]';
    return;
}
define('ABSPATH', $root);
define('CACHE_DIR', $root . '/includes/cache');
define('SITE_URL', 'http://127.0.0.1:8093');
define('SITE_TITLE', 'My Collection');
require_once $root . '/includes/Autoloader.php';
require_once $root . '/includes/frontend.php';
$appSettings = [
    'active_theme' => 'custom', 'enable_cache' => '0',
    'site_logo' => 'site_logo_1775392724.webp',
    'theme_studio_show_stats' => '1', 'theme_studio_grid_cols' => '4',
    'theme_studio_font_body' => 'Inter',
];
AppConfig::load($appSettings);
require_once $root . '/includes/HookRegistry.php';
require_once $root . '/modules/theme_studio/module.php';
if (isset($_GET['dark'])) HookRegistry::addAction('frontend_footer', static function () {
    echo '<link rel="stylesheet" href="/archive-preview-dark.css">';
});
$catalog = [
    ['id' => 103, 'reg_number' => 'PS-103', 'title' => 'National Science Day', 'production_date' => '2026-02-28', 'file_path' => 'img_103_9028527f310efc91.webp'],
    ['id' => 104, 'reg_number' => 'PS-104', 'title' => 'Chinese Fishing Nets, Kochi', 'production_date' => '2026-04-04', 'file_path' => 'img_104_ae99cd3d598b879e.webp'],
    ['id' => 105, 'reg_number' => 'PS-105', 'title' => 'Mattancherry Postal Cancellation', 'production_date' => '2026-04-04', 'file_path' => 'img_105_0b0144070d03ed58.webp'],
    ['id' => 6, 'reg_number' => 'PS-006', 'title' => 'Asiatic Cheetah', 'production_date' => '2025-03-03', 'file_path' => '1754573759_6894abbf18f58.jpg'],
];
$categories = [];
foreach (['Postal Cancellations', 'Stamps', 'Bank Notes', 'Coins', 'Special Cancellations'] as $index => $name) {
    $categories[] = ['id' => $index + 1, 'name' => $name, 'image_path' => '', 'sample_image' => $catalog[$index % 4]['file_path'], 'item_count' => 12 + $index];
}
class ArchivePreviewResult {
    public function __construct(private array $data) {}
    public function fetchAll($mode = null): array { return $this->data; }
    public function fetch($mode = null): array { return $this->data; }
}
class ArchivePreviewDatabase {
    public function query(string $query): ArchivePreviewResult {
        global $categories;
        return new ArchivePreviewResult(str_contains($query, 'FROM categories') ? $categories : ['total_items' => 120, 'with_images' => 113, 'latest_year' => 2026]);
    }
}
$pdo = new ArchivePreviewDatabase();
$storage = null;
$featuredItems = $catalog;
$featuredTags = [];
if ($path === '/') {
    require $root . '/themes/custom/index.php';
} elseif (in_array($path, ['/search', '/search.php'], true)) {
    require_once __DIR__ . '/archive_search_fixture.php';
    $pdo = archiveSearchFixturePdo();
    AppConfig::set('year_range_min', '2024');
    AppConfig::set('year_range_max', '2026');
    require $root . '/includes/pages/search.php';
} elseif (preg_match('#^/item/(\d+)$#', $path, $match)) {
    $item = $catalog[0];
    foreach ($catalog as $entry) if ($entry['id'] === (int) $match[1]) $item = $entry;
    $item += [
        'view_count' => 22, 'is_visible' => 1, 'material' => '', 'credit_line' => '',
        'physical_description' => '<p>A rectangular India Post card bearing a dated pictorial cancellation. The design combines an atom, a DNA helix and an electronic circuit, with the title National Science Day in Hindi and English.</p><p>The date and Ernakulam postal location appear beneath the illustration.</p>',
        'historical_significance' => '<p>This cancellation is preserved as part of the personal collection. Its scientific imagery and dated postal markings document the subject, place and occasion commemorated by the card.</p>',
    ];
    $itemCategories = [['id' => 5, 'name' => 'Special Cancellations']];
    $itemTags = [['name' => 'India Post', 'slug' => 'india-post'], ['name' => 'Science', 'slug' => 'science']];
    $imageMedia = [
        ['id' => 1, 'file_path' => $item['file_path'], 'caption' => '', 'license_type' => 'All Rights Reserved'],
        ['id' => 2, 'file_path' => $catalog[1]['file_path'], 'caption' => 'Second image for thumbnail and license synchronization checks.', 'license_type' => 'Public Domain'],
    ];
    $primaryMedia = $imageMedia[0];
    if (isset($_GET['single'])) $imageMedia = [$primaryMedia];
    $youtubeMedia = $pdfMedia = $stories = [];
    $relatedItems = array_map(static fn($entry) => $entry + ['thumb' => $entry['file_path']], array_slice($catalog, 1));
    $citation = 'Visual fixture for layout review. Sample content only; no database records are changed.';
    if (isset($_GET['empty'])) {
        $imageMedia = []; $primaryMedia = null; $relatedItems = []; $itemTags = []; $itemCategories = [];
        $item['physical_description'] = ''; $item['historical_significance'] = ''; $item['production_date'] = '';
    }
    require $root . '/themes/custom/item_detail.php';
} else {
    http_response_code(404);
    echo 'This isolated fixture serves the home, search and sample item pages.';
}
