<?php
/** Local, fixture-only biography UI preview. Never loads application credentials.
 * scripts/.runtime/php/php.exe -S 127.0.0.1:8097 -t . scripts/qa_people_router.php
 * List: /admin/module_page.php?m=people
 * Editor: /admin/module_page.php?m=people&action=edit&id=1
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($path === '/themes/common/dist/tailwind.css') {
    header('Content-Type: text/css; charset=UTF-8');
    readfile($root . '/themes/common/dist/tailwind.css');
    exit;
}
$fixtureRoot = __DIR__ . '/.runtime/qa/people';
if (!is_dir($fixtureRoot)) mkdir($fixtureRoot, 0755, true);
if ($path === '/uploads/thumbnails/person_abcdef.webp' || $path === '/uploads/original/person_abcdef.jpg') {
    // An intentionally wide test image makes unintended cropping easy to spot.
    $imagePath = $fixtureRoot . '/wide-photo.jpg';
    if (!is_file($imagePath)) {
        $image = imagecreatetruecolor(1200, 600);
        $background = imagecolorallocate($image, 226, 232, 240);
        imagefill($image, 0, 0, $background);
        $ink = imagecolorallocate($image, 30, 64, 175);
        imagerectangle($image, 5, 5, 1194, 594, $ink);
        imagestring($image, 5, 30, 280, 'LEFT EDGE', $ink);
        imagestring($image, 5, 1030, 280, 'RIGHT EDGE', $ink);
        imagestring($image, 5, 480, 280, 'UNCROPPED PHOTO FIXTURE', $ink);
        imagejpeg($image, $imagePath, 95);
        imagedestroy($image);
    }
    header('Content-Type: image/jpeg');
    readfile($imagePath);
    exit;
}
if ($path === '/') { header('Location: /admin/module_page.php?m=people'); exit; }
if ($path !== '/admin/module_page.php' || ($_GET['m'] ?? 'people') !== 'people') {
    http_response_code(404);
    exit('This local preview serves biography fixture pages only.');
}
require_once $root . '/includes/Csrf.php';
session_name('people_qa_preview');
ini_set('session.save_path', $fixtureRoot);
Csrf::initSession();
$_SESSION['admin_username'] = 'Collection editor';
define('SITE_URL', 'http://127.0.0.1:' . (int)($_SERVER['SERVER_PORT'] ?? 8097));
define('SITE_TITLE', 'Collection Portal');
require_once $root . '/includes/StorageInterface.php';
require_once $root . '/includes/BaseModule.php';
require_once $root . '/includes/HookRegistry.php';
require_once $root . '/modules/people/module.php';
require_once $root . '/admin/layout.php';
class QAPeoplePDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
$pdo = new QAPeoplePDO('sqlite:' . $fixtureRoot . '/people.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE IF NOT EXISTS people (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, slug TEXT UNIQUE, birth_date TEXT, death_date TEXT, short_description TEXT, biography TEXT, infobox_data TEXT, profile_image TEXT, is_public INTEGER)');
$pdo->exec('CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY, title TEXT, reg_number TEXT)');
$pdo->exec('CREATE TABLE IF NOT EXISTS item_people (id INTEGER PRIMARY KEY, item_id INTEGER, person_id INTEGER, role TEXT)');
if (!(int)$pdo->query('SELECT COUNT(*) FROM people')->fetchColumn()) {
    $stmt = $pdo->prepare('INSERT INTO people (name,slug,birth_date,death_date,short_description,biography,infobox_data,profile_image,is_public) VALUES (?,?,?,?,?,?,?,?,?)');
    $names = ['A. Raman', 'B. Mehta', 'C. Rao', 'D. Sen', 'E. Thomas', 'F. Kapoor', 'G. Nair', 'H. Bose'];
    foreach ($names as $i => $name) $stmt->execute([$name, 'sample-person-' . ($i + 1), '1900', '', 'Historical figure connected to the collection', "This is a synthetic profile for visual quality checks.\n\nUse this page to check editing, readable typography, linked collection records, and the full photo preview.", '[{"label":"Occupation","value":"Researcher"},{"label":"Source","value":"Sample collection notes"}]', $i === 0 ? 'original/person_abcdef.jpg' : null, $i % 3 !== 2 ? 1 : 0]);
    $pdo->exec("INSERT INTO items VALUES (1, 'Commemorative stamp with portrait', 'STAMP-001'), (2, 'First day cover with special cancellation', 'COVER-002'), (3, 'Historical correspondence cover', 'COVER-003')");
    $pdo->exec("INSERT INTO item_people VALUES (1,1,1,'Subject'), (2,2,1,'Associated')");
}
$GLOBALS['appSettings'] = ['active_modules' => '["people"]'];
$module = new PeopleModule($pdo, 'people', []);
$module->boot();
if (!moduleCsrfAllowed($_SERVER['REQUEST_METHOD'] ?? 'GET', $_POST, $_SERVER)) { http_response_code(403); exit('Security check failed.'); }
HookRegistry::doAction('admin_init_people');
echo renderAdminHeader('People & Biographies', true);
HookRegistry::doAction('admin_page_people');
echo renderAdminFooter();
