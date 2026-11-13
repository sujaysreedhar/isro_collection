<?php
declare(strict_types=1);

// Isolated renderer: no application bootstrap, credentials, or persistent writes.
define('SITE_URL', 'http://localhost/collection');
define('SITE_TITLE', 'Atlas test');
class ThemeManager {
    public static function getHeader(): string { return __FILE__; }
    public static function getFooter(): string { return __FILE__; }
    public static function getActiveTheme(): string { return $GLOBALS['testTheme']; }
}
class HookRegistry {
    public static array $calls = [];
    public static function doAction(string $name): void { self::$calls[] = $name; }
}
function checkAtlas(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$locations = [['id' => 1, 'post_office' => '</script><script>alert(1)</script>', 'latitude' => '12', 'longitude' => '77']];
$jsonLocations = json_encode($locations);
foreach (['default', 'dark', 'glass', 'modern_blue'] as $testTheme) {
    HookRegistry::$calls = [];
    ob_start();
    include __DIR__ . '/../themes/' . $testTheme . '/atlas.php';
    $html = ob_get_clean();
    checkAtlas(!str_contains($html, 'cartocdn.com'), "$testTheme must not load API-gated CARTO tiles");
    foreach (['atlas-search-input', 'atlas-search-dropdown', 'atlas-state', 'atlas-status', 'atlas-show-all', 'atlas-summary', 'frontendAtlasMap'] as $id) {
        checkAtlas(str_contains($html, 'id="' . $id . '"'), "$testTheme missing $id");
    }
    checkAtlas(!str_contains($html, '</script><script>alert(1)</script>'), 'Location data must not escape its JSON script');
    checkAtlas(str_contains($html, 'atlas_public.js'), "$testTheme must load shared behavior");
    if (in_array($testTheme, ['dark', 'glass', 'modern_blue'], true)) {
        checkAtlas(HookRegistry::$calls === ['frontend_header'], "$testTheme must retain the frontend_header hook");
    } else {
        checkAtlas(HookRegistry::$calls === [], 'Default header owns the hook; Atlas must not duplicate it');
    }
    echo "PASS $testTheme public Atlas rendering\n";
}

require_once __DIR__ . '/../modules/postmark_atlas/PublicLocations.php';
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE items (id INTEGER, title TEXT, is_visible INTEGER);
CREATE TABLE postmark_locations (id INTEGER, pin_code TEXT, post_office TEXT, ppc_name TEXT, district TEXT, state TEXT, latitude REAL, longitude REAL, is_acquired INTEGER, linked_item_id INTEGER);
CREATE TABLE media (id INTEGER, item_id INTEGER, file_path TEXT, media_type TEXT, upload_date TEXT);
INSERT INTO items VALUES (1, "Public item", 1), (2, "Private item", 0);
INSERT INTO postmark_locations VALUES (1,"560001","Bangalore","PPC","Bangalore","Karnataka",12,77,1,1),(2,"680001","Thrissur","PPC","Thrissur","Kerala",10,76,0,2);
INSERT INTO media VALUES (1,1,"public.webp","image","2026-01-01"),(2,2,"private.webp","image","2026-01-01");');
$rows = PublicLocations::all($pdo, false, true);
checkAtlas(($rows[0]['preview_file_path'] ?? null) === 'public.webp', 'Public linked item gets image preview');
checkAtlas($rows[1]['linked_item_id'] === null && $rows[1]['linked_item_title'] === null && $rows[1]['preview_file_path'] === null, 'Private item data and images stay hidden');
checkAtlas(!array_key_exists('preview_file_path', PublicLocations::all($pdo, false)[0]), 'Other callers keep the original payload');
echo "PASS public image previews and private item protection\n";
