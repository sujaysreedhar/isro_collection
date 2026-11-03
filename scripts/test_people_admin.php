<?php
/** Biography validation, safe rendering and admin navigation regression checks.
 * Run: php scripts/test_people_admin.php
 */
declare(strict_types=1);
require_once __DIR__ . '/../modules/people/PeopleSupport.php';
require_once __DIR__ . '/../includes/StorageInterface.php';
require_once __DIR__ . '/../includes/HookRegistry.php';
require_once __DIR__ . '/../includes/MediaProcessor.php';
require_once __DIR__ . '/../admin/layout.php';
define('SITE_URL', 'https://collection.example');
define('SITE_TITLE', 'Test collection');
$_SESSION = ['csrf_token' => str_repeat('a', 64), 'admin_username' => 'Editor'];
function ensureCsrfToken(): string { return $_SESSION['csrf_token']; }
function verifyCsrfToken(?string $token): bool { return $token === $_SESSION['csrf_token']; }
$assertions = 0;
$check = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) throw new RuntimeException($message);
    $assertions++;
};
[$values, $errors] = PeopleSupport::validate(['name' => '  Ada Lovelace  ', 'slug' => 'ada-lovelace', 'is_public' => '1', 'info_key' => ['Occupation'], 'info_val' => ['Mathematician']]);
$check(!$errors && $values['name'] === 'Ada Lovelace' && $values['is_public'] === 1, 'Valid profile should normalize and validate.');
$check(json_decode($values['infobox_data'], true)[0]['value'] === 'Mathematician', 'Infobox facts should survive validation.');
foreach (['../escape', '<script>', 'two words', '-start', ''] as $slug) {
    [, $errors] = PeopleSupport::validate(['name' => 'Name', 'slug' => $slug]);
    $check((bool)$errors, 'Invalid slug should be rejected: ' . $slug);
}
[, $errors] = PeopleSupport::validate(['name' => [], 'slug' => 'valid', 'info_key' => [['bad']], 'info_val' => [['bad']]]);
$check((bool)$errors, 'Malformed form values should be handled without warnings.');
[, $errors] = PeopleSupport::validate(['name' => 'Name', 'slug' => 'valid', 'short_description' => str_repeat('x', 501)]);
$check((bool)$errors, 'Descriptions must respect database length limits.');
$check(PeopleSupport::imageUrl('../escape.jpg') === '', 'Unsafe image paths must not be rendered.');
$check(PeopleSupport::imageUrl('original/person_abcdef.jpg') === SITE_URL . '/uploads/thumbnails/person_abcdef.webp', 'New photos must use thumbnails for previews.');
$check(PeopleSupport::imageUrl('original/person_abcdef.jpg', true) === SITE_URL . '/uploads/original/person_abcdef.jpg', 'Original photo URL must preserve source format.');
$check(PeopleSupport::imageUrl('legacy.jpg') === SITE_URL . '/uploads/display/legacy.jpg', 'Existing photos must keep working.');

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE people (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, birth_date TEXT, death_date TEXT, short_description TEXT, biography TEXT, infobox_data TEXT, profile_image TEXT, is_public INTEGER)');
$db->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, title TEXT, reg_number TEXT)');
$db->exec('CREATE TABLE item_people (id INTEGER PRIMARY KEY, item_id INTEGER, person_id INTEGER, role TEXT)');
$stmt = $db->prepare('INSERT INTO people VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
for ($i = 1; $i <= 26; $i++) $stmt->execute([$i, $i === 1 ? '<script>alert(1)</script>' : 'Person ' . $i, 'person-' . $i, '', '', '', 'Biography', '[]', null, $i % 2]);
$module = new class($db) {
    public function __construct(public PDO $pdo) {}
    public function render(): string { ob_start(); require __DIR__ . '/../modules/people/admin_people.php'; return ob_get_clean(); }
    public function process(): void { require __DIR__ . '/../modules/people/admin_people_logic.php'; }
};
$_GET = ['m' => 'people'];
$html = $module->render();
$check(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html, '<script>alert(1)</script>'), 'Biography names must be escaped in the list.');
$check(str_contains($html, 'Page 1 of 2'), 'Biographies must paginate at 25 profiles.');
$check(str_contains($html, 'method="post" action="?m=people&amp;action=delete'), 'Deletion must use a CSRF-protected POST form.');
$_GET = ['m' => 'people', 'visibility' => 'private'];
$html = $module->render();
$check(str_contains($html, '13 biographies') && !str_contains($html, 'Page 1 of 2'), 'Visibility filtering must affect counts and pagination.');
$_GET = ['m' => 'people', 'q' => 'Person 26'];
$html = $module->render();
$check(str_contains($html, '1 biography') && !str_contains($html, '>Person 25<'), 'Search must narrow the server-side result set.');
$_GET = ['m' => 'people', 'action' => 'edit', 'id' => 2];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['save_person' => '1', 'csrf_token' => $_SESSION['csrf_token'], 'name' => '', 'slug' => 'person-2', 'biography' => 'Unsaved work'];
$module->process();
$html = $module->render();
$check(str_contains($html, 'Enter the person&#039;s name.') && str_contains($html, 'Unsaved work'), 'Validation errors must retain unsaved editor content.');
$check($db->query('SELECT name FROM people WHERE id = 2')->fetchColumn() === 'Person 2', 'Invalid editor input must not update the database.');
$header = renderAdminHeader('Test');
$footer = renderAdminFooter();
$check(str_contains($header, 'Skip to content') && str_contains($header, '/admin/album_pages.php'), 'Admin navigation should expose skip link and album pages.');
$check(str_contains($header, 'aria-hidden="true" inert') && str_contains($footer, "event.key === 'Escape'"), 'Mobile drawer must be inert while closed and support Escape.');
$orphanRoot = __DIR__ . '/.runtime/qa/people/orphan-check/';
foreach (['original', 'thumbnails', 'display', 'pdfs'] as $directory) if (!is_dir($orphanRoot . $directory)) mkdir($orphanRoot . $directory, 0755, true);
$db->exec('CREATE TABLE media (file_path TEXT)');
$db->exec("UPDATE people SET profile_image = 'original/person_abcdef.jpg' WHERE id = 1");
$db->exec("UPDATE people SET profile_image = 'person_123456.jpg' WHERE id = 2");
$files = ['original/person_abcdef.jpg', 'thumbnails/person_abcdef.webp', 'display/person_123456.jpg', 'thumbnails/person_deadbeef.webp'];
foreach ($files as $file) file_put_contents($orphanRoot . $file, 'fixture');
$reflection = new ReflectionClass(MediaProcessor::class);
$processor = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('db')->setValue($processor, $db);
$reflection->getProperty('uploadRoot')->setValue($processor, $orphanRoot);
$orphans = $processor->orphanedFiles();
$check($orphans === ['thumbnails/person_deadbeef.webp'], 'Referenced new and legacy biography photos must survive orphan cleanup.');
$db->exec('DROP TABLE people');
$check($processor->orphanedFiles() === [], 'Unavailable biography tables must not cause photo deletion.');
foreach ($files as $file) unlink($orphanRoot . $file);
foreach (['original', 'thumbnails', 'display', 'pdfs'] as $directory) rmdir($orphanRoot . $directory);
rmdir($orphanRoot);
if (in_array('--render', $argv, true)) {
    $folder = __DIR__ . '/.runtime/qa/people';
    if (!is_dir($folder)) mkdir($folder, 0755, true);
    file_put_contents($folder . '/admin-edit.html', $header . $html . $footer);
    preg_match_all('#<script>(.*?)</script>#s', $html . $footer, $matches);
    file_put_contents($folder . '/admin-scripts.js', implode("\n", $matches[1]));
}
echo "Passed $assertions biographies/admin assertions.\n";
