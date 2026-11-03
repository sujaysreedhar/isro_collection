<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/CollectionInventory.php';
require_once __DIR__ . '/../includes/Csrf.php';
if (!defined('SITE_URL')) define('SITE_URL', 'https://collection.example');
$_SESSION = ['admin_id' => 1, 'csrf_token' => str_repeat('a', 64)];

function cwCheck(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    echo "PASS: {$label}\n";
}
function cwReject(callable $call, string $label): void {
    try { $call(); } catch (InvalidArgumentException $e) { cwCheck(true, $label); return; }
    throw new RuntimeException('FAIL: ' . $label . ' was accepted');
}
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) { fwrite(STDERR, "PDO SQLite is required; application database was not accessed.\n"); exit(1); }
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys = ON');
foreach ([
    'CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT)',
    'CREATE TABLE items (id INTEGER PRIMARY KEY, title TEXT, reg_number TEXT, category_id INTEGER, is_visible INTEGER)',
    'CREATE TABLE item_category (item_id INTEGER, category_id INTEGER, PRIMARY KEY(item_id,category_id))',
    'CREATE TABLE module_storage (item_id INTEGER PRIMARY KEY REFERENCES items(id) ON DELETE CASCADE, album TEXT, page_number TEXT, box_id TEXT, location_notes TEXT)',
    'CREATE TABLE module_valuations (item_id INTEGER PRIMARY KEY REFERENCES items(id) ON DELETE CASCADE, purchase_price DECIMAL(15,2), current_value DECIMAL(15,2), currency TEXT, purchase_date TEXT)',
    'CREATE TABLE collection_private_details (item_id INTEGER PRIMARY KEY REFERENCES items(id) ON DELETE CASCADE, sleeve TEXT, shelf TEXT, private_notes TEXT)',
    'CREATE TABLE collection_checklists (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, dimension TEXT, group_value TEXT)',
    'CREATE TABLE collection_checklist_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, checklist_id INTEGER REFERENCES collection_checklists(id) ON DELETE CASCADE, label TEXT, catalogue_reference TEXT, item_id INTEGER REFERENCES items(id) ON DELETE SET NULL, UNIQUE(checklist_id,item_id))',
    "INSERT INTO categories VALUES (1,'Stamps'),(2,'Covers'),(3,'Space')",
    "INSERT INTO items VALUES (1,'Apollo stamp','REG-1',1,1),(2,'Launch cover','REG-2',2,1),(3,'Hidden stamp','REG-3',1,0)",
    'INSERT INTO item_category VALUES (1,1),(1,3),(2,2),(3,1)'
] as $sql) $pdo->exec($sql);
$inventory = new CollectionInventory($pdo);
cwCheck($inventory->ready(), 'schema readiness uses only required tables');
cwCheck(CollectionInventory::amount('000123.4', 'Price') === '123.40', 'money normalized without floating point');
cwReject(static fn() => CollectionInventory::amount('-1', 'Price'), 'negative money rejected');
cwReject(static fn() => CollectionInventory::amount('1.001', 'Price'), 'extra money precision rejected');
cwReject(static fn() => CollectionInventory::normalizeIds(['1 OR 1=1']), 'malformed IDs rejected');
cwReject(static fn() => CollectionInventory::normalizeIds(array_fill(0, 101, 1)), 'bulk limit enforced');
cwCheck(CollectionInventory::normalizeIds(['2', '1', '2']) === [1, 2], 'IDs deduplicated and sorted');
cwReject(static fn() => CollectionInventory::normalizePrivate(['purchase_date' => '2026-02-30']), 'invalid calendar date rejected');
cwReject(static fn() => CollectionInventory::normalizePrivate(['currency' => 'US']), 'invalid currency rejected');
cwReject(static fn() => $inventory->normalizeChanges(['title' => 'Unexpected']), 'unapproved bulk fields rejected');
cwReject(static fn() => $inventory->normalizeChanges(['category_id' => 999]), 'missing category rejected');
$inventory->savePrivate(1, ['album' => 'Space album', 'page_number' => '7', 'sleeve' => 'A2', 'box_id' => 'Blue box', 'shelf' => 'Shelf 3', 'private_notes' => 'Private ownership note', 'location_notes' => 'Lower drawer', 'purchase_price' => '100.50', 'current_value' => '150', 'currency' => 'inr', 'purchase_date' => '2026-10-12']);
$private = $inventory->privateDetails(1);
cwCheck($private['album'] === 'Space album' && $private['sleeve'] === 'A2' && (float)$private['purchase_price'] === 100.50, 'private storage and finances saved in reused tables');
cwCheck(!array_key_exists('purchase_price', $pdo->query('SELECT * FROM items WHERE id = 1')->fetch()), 'core public records contain no private money fields');
cwCheck($inventory->search('Shelf 3')['total'] === 1, 'storage search finds physical shelf');
cwCheck(count($inventory->selectedItems([1, 999])) === 1, 'Manage Items handoff preserves valid selected records and omits nonexistent records');
$preview = $inventory->preview([1, 2], ['album' => 'New album', 'category_id' => 3, 'is_visible' => 0]);
cwCheck($inventory->privateDetails(1)['album'] === 'Space album', 'preview makes no writes');
cwCheck($inventory->applyPreview($preview) === 2, 'confirmed preview updates selected items');
cwCheck($pdo->query('SELECT category_id FROM item_category WHERE item_id = 1')->fetchAll(PDO::FETCH_COLUMN) === [3], 'bulk categories synchronized with join table');
cwCheck((int)$pdo->query('SELECT category_id FROM items WHERE id = 1')->fetchColumn() === 3, 'bulk primary category synchronized');
cwCheck($inventory->privateDetails(1)['sleeve'] === 'A2' && (float)$inventory->privateDetails(1)['purchase_price'] === 100.50, 'unchecked private fields retained');
cwCheck((int)$pdo->query('SELECT is_visible FROM items WHERE id = 2')->fetchColumn() === 0, 'explicit visibility change applied');
$stale = $inventory->preview([1, 2], ['shelf' => 'Overwrite']);
$inventory->saveExtraFields(2, ['shelf' => 'Edited after preview']);
cwReject(static fn() => $inventory->applyPreview($stale), 'stale private edit blocks complete batch');
cwCheck($inventory->privateDetails(1)['shelf'] === 'Shelf 3', 'stale batch writes nothing to earlier rows');
$expired = $inventory->preview([1], ['album' => 'Expired']); $expired['created'] = time() - 901;
cwReject(static fn() => $inventory->applyPreview($expired), 'expired preview rejected');
cwReject(static fn() => $inventory->preview([1, 999], ['album' => 'Missing']), 'deleted or nonexistent selection rejected');
$pdo->exec('UPDATE items SET is_visible = 1');
$rollback = $inventory->preview([1, 2], ['is_visible' => 0]);
$pdo->exec("CREATE TRIGGER cw_fail BEFORE UPDATE ON items WHEN NEW.id = 2 BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
try { $inventory->applyPreview($rollback); throw new RuntimeException('Expected fixture failure'); }
catch (PDOException $e) { cwCheck((int)$pdo->query('SELECT is_visible FROM items WHERE id = 1')->fetchColumn() === 1, 'later write failure rolls back earlier records'); }
$pdo->exec('DROP TRIGGER cw_fail');
$checklist = $inventory->createChecklist(['name' => 'India 2026', 'dimension' => 'country', 'group_value' => 'India']);
$inventory->saveEntry($checklist, 0, ['label' => 'Apollo stamp', 'catalogue_reference' => 'SG-1', 'item_id' => '1']);
$inventory->saveEntry($checklist, 0, ['label' => 'Missing issue']);
$inventory->saveEntry($checklist, 0, ['label' => 'Hidden owned issue', 'item_id' => '3']);
cwReject(static fn() => $inventory->saveEntry($checklist, 0, ['label' => 'Duplicate owned link', 'item_id' => '1']), 'same physical item cannot inflate checklist twice');
$progress = $inventory->checklists('country')[0];
cwCheck((int)$progress['expected_count'] === 3 && (int)$progress['owned_count'] === 2, 'completeness uses explicit expected slots including private owned items');
$pdo->exec('DELETE FROM items WHERE id = 1');
$progress = $inventory->checklists('country')[0];
cwCheck((int)$progress['expected_count'] === 3 && (int)$progress['owned_count'] === 1, 'deleted owned item becomes missing without losing expected position');
cwReject(static fn() => $inventory->createChecklist(['name' => 'Bad year', 'dimension' => 'year', 'group_value' => 'last year']), 'year grouping validates four digits');
cwReject(static fn() => $inventory->saveEntry($checklist, 0, ['label' => 'Invalid owned link', 'item_id' => '999']), 'expected item cannot link nonexistent record');
function cwRender(CollectionInventory $inventory, array $get): string {
    $_GET = $get;
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $error = ''; $success = '';
    ob_start();
    require __DIR__ . '/../modules/collection_workbench/admin_page.php';
    return ob_get_clean();
}
set_error_handler(static function(int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$html = cwRender($inventory, ['tab' => 'storage', 'item' => 2]);
cwCheck(str_contains($html, 'Save private details') && !str_contains($html, 'Unable to load'), 'private-detail template renders without warnings');
$html = cwRender($inventory, ['tab' => 'bulk', 'ids' => '2,3,999']);
cwCheck(str_contains($html, 'Showing 2 valid records') && substr_count($html, 'class="cw-item-select"') === 2, 'bulk selection template handles records outside initial list');
$html = cwRender($inventory, ['tab' => 'completeness', 'checklist' => $checklist]);
cwCheck(str_contains($html, 'missing-entry') && str_contains($html, '1 / 3 owned'), 'completeness template highlights missing expected items');
restore_error_handler();
echo "All Collection Workbench integration checks passed.\n";
