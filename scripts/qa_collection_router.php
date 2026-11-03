<?php
/** Guarded fixture-only UI server; never loads application configuration.
 * scripts/.runtime/php/php.exe -S 127.0.0.1:8099 -t . scripts/qa_collection_router.php
 * /admin/module_page.php?m=wishlist
 * /admin/module_page.php?m=collection_workbench&tab=storage
 * /admin/module_page.php?m=collection_workbench&tab=completeness&checklist=1
 * /admin/module_page.php?m=collection_workbench&tab=bulk&ids=1,2
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(404); exit; }
$root = dirname(__DIR__);
$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$static = [
    '/themes/common/dist/tailwind.css' => 'text/css',
    '/modules/wishlist/assets/wishlist.css' => 'text/css',
    '/modules/wishlist/assets/wishlist.js' => 'text/javascript',
];
if (isset($static[$path])) { header('Content-Type: ' . $static[$path] . '; charset=UTF-8'); readfile($root . $path); exit; }
if ($path === '/') { header('Location: /admin/module_page.php?m=wishlist'); exit; }
if (!in_array($path, ['/admin/module_page.php', '/admin/ajax.php'], true)) { http_response_code(404); exit('This fixture server serves collection preview pages only.'); }
$port = (int)($_SERVER['SERVER_PORT'] ?? 8099);
define('SITE_URL', 'http://127.0.0.1:' . $port);
define('SITE_TITLE', 'Collection Portal');
define('ABSPATH', $root);
$fixtureRoot = __DIR__ . '/.runtime/qa/collection';
if (!is_dir($fixtureRoot)) mkdir($fixtureRoot, 0755, true);
session_name('collection_qa_preview');
ini_set('session.save_path', $fixtureRoot);
require_once $root . '/includes/Csrf.php';
Csrf::initSession();
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'Collection editor';
require_once $root . '/includes/BaseModule.php';
require_once $root . '/includes/HookRegistry.php';
require_once $root . '/includes/CollectionInventory.php';
require_once $root . '/modules/wishlist/module.php';
require_once $root . '/modules/collection_workbench/module.php';
require_once $root . '/admin/layout.php';
final class CollectionQAPDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(str_replace([' FOR UPDATE', 'INSERT IGNORE'], ['', 'INSERT OR IGNORE'], $query), $options);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        if (preg_match("/^SHOW TABLES LIKE '([a-zA-Z0-9_]+)'$/", $query, $matches)) $query = "SELECT name FROM sqlite_master WHERE type='table' AND name='" . $matches[1] . "'";
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}
$pdo = new CollectionQAPDO('sqlite:' . $fixtureRoot . '/collection.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys=ON');
$schema = [
    'CREATE TABLE IF NOT EXISTS categories (id INTEGER PRIMARY KEY, name TEXT)',
    'CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, physical_description TEXT, production_date TEXT, reg_number TEXT, category_id INTEGER, is_visible INTEGER)',
    'CREATE TABLE IF NOT EXISTS item_category (item_id INTEGER, category_id INTEGER, PRIMARY KEY(item_id,category_id))',
    'CREATE TABLE IF NOT EXISTS module_storage (item_id INTEGER PRIMARY KEY REFERENCES items(id) ON DELETE CASCADE, album TEXT, page_number TEXT, box_id TEXT, location_notes TEXT)',
    'CREATE TABLE IF NOT EXISTS module_valuations (item_id INTEGER PRIMARY KEY REFERENCES items(id) ON DELETE CASCADE, purchase_price DECIMAL(15,2), current_value DECIMAL(15,2), currency TEXT, purchase_date TEXT)',
    'CREATE TABLE IF NOT EXISTS collection_private_details (item_id INTEGER PRIMARY KEY REFERENCES items(id) ON DELETE CASCADE, sleeve TEXT, shelf TEXT, private_notes TEXT)',
    'CREATE TABLE IF NOT EXISTS collection_checklists (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, dimension TEXT, group_value TEXT)',
    'CREATE TABLE IF NOT EXISTS collection_checklist_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, checklist_id INTEGER REFERENCES collection_checklists(id) ON DELETE CASCADE, label TEXT, catalogue_reference TEXT, item_id INTEGER REFERENCES items(id) ON DELETE SET NULL, UNIQUE(checklist_id,item_id))',
    'CREATE TABLE IF NOT EXISTS module_themes (id INTEGER PRIMARY KEY, name TEXT)',
    'CREATE TABLE IF NOT EXISTS module_theme_item (theme_id INTEGER,item_id INTEGER,PRIMARY KEY(theme_id,item_id))',
    'CREATE TABLE IF NOT EXISTS module_wishlist_items (id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,description TEXT,theme_id INTEGER,status TEXT,item_type TEXT,priority INTEGER,catalogue_ref TEXT,target_price TEXT,target_currency TEXT,image_url TEXT,collection_item_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE IF NOT EXISTS module_wishlist_stores (id INTEGER PRIMARY KEY AUTOINCREMENT,wishlist_item_id INTEGER REFERENCES module_wishlist_items(id) ON DELETE CASCADE,store_name TEXT,store_url TEXT,price TEXT,notes TEXT,amount TEXT,currency TEXT,shipping TEXT,availability TEXT,last_checked TEXT,archived INTEGER DEFAULT 0,created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE IF NOT EXISTS module_wishlist_price_history (id INTEGER PRIMARY KEY AUTOINCREMENT,store_id INTEGER REFERENCES module_wishlist_stores(id) ON DELETE CASCADE,amount TEXT,currency TEXT,shipping TEXT,availability TEXT,checked_on TEXT,recorded_at TEXT DEFAULT CURRENT_TIMESTAMP)',
];
foreach ($schema as $sql) $pdo->exec($sql);
$inventory = new CollectionInventory($pdo);
$wishlist = new WishlistService($pdo);
if (!(int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn()) {
    $pdo->exec("INSERT INTO categories VALUES (1,'Stamps'),(2,'Covers'),(3,'Space exploration')");
    $pdo->exec("INSERT INTO module_themes VALUES (1,'Space exploration'),(2,'Postal history')");
    $pdo->exec("INSERT INTO items (title,physical_description,production_date,reg_number,category_id,is_visible) VALUES ('Space commemorative stamp','Synthetic fixture record','1969','STAMP-001',1,1),('Launch day cover','Synthetic fixture record','1975','COVER-002',2,1),('Research satellite stamp','Synthetic fixture record','1980','STAMP-003',1,0),('Special cancellation cover','Synthetic fixture record','1985','COVER-004',2,1)");
    $pdo->exec('INSERT INTO item_category VALUES (1,1),(1,3),(2,2),(3,1),(4,2)');
    foreach ([1,2,3,4] as $id) $inventory->savePrivate($id, ['album' => $id <= 2 ? 'Space album' : 'Postal history', 'page_number' => (string)($id * 2), 'sleeve' => 'A' . $id, 'box_id' => 'Archive box ' . ($id <= 2 ? '1' : '2'), 'shelf' => 'Shelf ' . ($id <= 2 ? 'A' : 'B'), 'location_notes' => 'Synthetic fixture: lower drawer', 'private_notes' => 'Synthetic private note for visual quality checks.', 'purchase_price' => (string)($id * 100), 'current_value' => (string)($id * 125), 'currency' => 'INR', 'purchase_date' => '2026-10-12']);
    foreach ([['India space commemoratives','country','India'],['Issues of 1969','year','1969'],['Space programme series','series','Space programme'],['Space exploration','theme','Space exploration']] as $definition) {
        $checklistId = $inventory->createChecklist(['name' => $definition[0], 'dimension' => $definition[1], 'group_value' => $definition[2]]);
        $inventory->saveEntry($checklistId, 0, ['label' => 'Space commemorative stamp', 'catalogue_reference' => 'SAMPLE-01', 'item_id' => '1']);
        $inventory->saveEntry($checklistId, 0, ['label' => 'Launch day cover', 'catalogue_reference' => 'SAMPLE-02', 'item_id' => '2']);
        $inventory->saveEntry($checklistId, 0, ['label' => 'Missing commemorative issue', 'catalogue_reference' => 'SAMPLE-03']);
        $inventory->saveEntry($checklistId, 0, ['label' => 'Missing miniature sheet', 'catalogue_reference' => 'SAMPLE-04']);
        $inventory->saveEntry($checklistId, 0, ['label' => 'Research satellite stamp', 'catalogue_reference' => 'SAMPLE-05', 'item_id' => '3']);
    }
}
if (!(int)$pdo->query('SELECT COUNT(*) FROM module_wishlist_items')->fetchColumn()) {
    $lastWeek = date('Y-m-d', time() - 7 * 86400);
    $today = date('Y-m-d');
    $item = ['name' => 'Space exploration commemorative stamp', 'description' => 'Synthetic collecting notes: look for a clean, lightly hinged example.', 'item_type' => 'stamp', 'status' => 'shortlisted', 'catalogue_ref' => 'SAMPLE-101', 'theme_id' => '1', 'target_price' => '600', 'target_currency' => 'INR', 'priority' => '4', 'stores' => [
        ['name' => 'Example Stamp Dealer', 'url' => 'https://seller.example/stamp-101', 'amount' => '650', 'currency' => 'INR', 'shipping' => '50', 'availability' => 'available', 'last_checked' => $lastWeek, 'notes' => 'Sample offer; ask for a reverse scan.'],
        ['name' => 'Example Collector Market', 'url' => 'https://market.example/lot-101', 'amount' => '590', 'currency' => 'INR', 'shipping' => '40', 'availability' => 'available', 'last_checked' => $today, 'notes' => 'Sample offer with tracked shipping.'],
        ['name' => 'Example International Dealer', 'url' => 'https://dealer.example/101', 'amount' => '7.50', 'currency' => 'USD', 'shipping' => '3.00', 'availability' => 'available', 'last_checked' => $today],
    ]];
    $id = $wishlist->save($item, null);
    $offers = $pdo->query('SELECT id FROM module_wishlist_stores WHERE wishlist_item_id=' . $id . ' ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $item['id'] = (string)$id;
    foreach ($item['stores'] as $i => &$offer) $offer['id'] = (string)$offers[$i];
    unset($offer);
    $item['stores'][0]['amount'] = '610'; $item['stores'][0]['last_checked'] = $today;
    $wishlist->save($item, null);
    $wishlist->save(['name' => 'First day cover with special cancellation', 'description' => 'Synthetic fixture: compare full cover scans before purchase.', 'item_type' => 'cover', 'status' => 'searching', 'theme_id' => '2', 'catalogue_ref' => 'SAMPLE-FDC-02', 'priority' => '3', 'stores' => [['name' => 'Example Postal History', 'url' => 'https://postal.example/cover-2', 'amount' => '1200', 'currency' => 'INR', 'shipping' => '', 'availability' => 'unknown', 'last_checked' => $today],['name' => 'Example Cover Shop', 'url' => 'https://covers.example/fdc-2', 'amount' => '1300', 'currency' => 'INR', 'shipping' => '0', 'availability' => 'available', 'last_checked' => $today]]], null);
    $wishlist->save(['name' => 'Miniature sheet for the space album', 'item_type' => 'stamp', 'status' => 'ordered', 'priority' => '2', 'stores' => [['name' => 'Example Dealer', 'url' => 'https://seller.example/sheet', 'amount' => '450', 'currency' => 'INR', 'shipping' => '25', 'availability' => 'sold', 'last_checked' => $today]]], null);
}
$GLOBALS['appSettings'] = ['active_modules' => '["wishlist","collection_workbench"]'];
$wishModule = new WishlistModule($pdo, 'wishlist', []); $wishModule->boot();
$workbench = new CollectionWorkbenchModule($pdo, 'collection_workbench', []); $workbench->boot();
if ($path === '/admin/ajax.php') {
    header('Content-Type: application/json');
    if ($_FILES) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Image uploads are disabled in the isolated visual fixture.']); exit; }
    $action = is_string($_REQUEST['action'] ?? null) ? $_REQUEST['action'] : '';
    if (!str_starts_with($action, 'wishlist_') || !HookRegistry::applyFilters('admin_ajax_' . $action, false)) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Preview action not found.']); }
    exit;
}
$slug = in_array($_GET['m'] ?? '', ['wishlist','collection_workbench'], true) ? $_GET['m'] : 'wishlist';
if (!moduleCsrfAllowed($_SERVER['REQUEST_METHOD'] ?? 'GET', $_POST, $_SERVER)) { http_response_code(403); exit('Security check failed.'); }
HookRegistry::doAction('admin_init_' . $slug);
echo renderAdminHeader($slug === 'wishlist' ? 'Wantlist & seller tracking' : 'Collection Workbench');
HookRegistry::doAction('admin_page_' . $slug);
echo renderAdminFooter();
