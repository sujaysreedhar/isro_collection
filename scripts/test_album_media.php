<?php
declare(strict_types=1);
// Isolated image/PDF regression fixtures. Never boot the application database.
$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
require_once $root . '/includes/Autoloader.php';
$fixtureRoot = __DIR__ . '/.runtime/album-fixture';
@mkdir($fixtureRoot . '/uploads/original', 0755, true);
@mkdir($fixtureRoot . '/uploads/thumbnails', 0755, true);
define('ABSPATH', $fixtureRoot);
define('SITE_URL', 'https://collection.example');
define('SITE_TITLE', 'Collection album');
AppConfig::load(['media_has_is_primary' => '1']);
$checks = 0;
function amCheck(bool $value, string $label): void { global $checks; if (!$value) throw new RuntimeException($label); $checks++; }
function amReject(callable $call, string $label): void { try { $call(); } catch (DomainException $e) { amCheck(true, $label); return; } throw new RuntimeException($label); }
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE items(id INTEGER PRIMARY KEY,reg_number TEXT,title TEXT,production_date TEXT,physical_description TEXT,is_visible INTEGER,purchase_price TEXT,private_notes TEXT)');
$pdo->exec('CREATE TABLE media(id INTEGER PRIMARY KEY,item_id INTEGER,file_path TEXT,caption TEXT,media_type TEXT,is_primary INTEGER)');
$name = 'img_1_0123456789abcdef_1200.webp';
$original = $fixtureRoot . '/uploads/original/img_1_0123456789abcdef.png';
$image = imagecreatetruecolor(1800, 700);
imagefill($image, 0, 0, imagecolorallocate($image, 247, 245, 234));
$ink = imagecolorallocate($image, 28, 67, 99);
imagesetthickness($image, 18); imagerectangle($image, 12, 12, 1787, 687, $ink);
imagestring($image, 5, 660, 330, 'COMPLETE COVER - ALL FOUR EDGES', $ink);
imagepng($image, $original); imagedestroy($image);
amCheck(MediaProcessor::optimizeImage($original, $fixtureRoot . '/uploads/thumbnails/' . $name, 1200, 1200), 'Preview generation');
$dimensions = getimagesize($fixtureRoot . '/uploads/thumbnails/' . $name);
amCheck($dimensions[0] === 1200 && $dimensions[1] === 467, 'Preview retains wide cover proportions');
amCheck(MediaProcessor::originalStoragePath($name) === 'original/img_1_0123456789abcdef.png', 'Current original naming resolved');
amCheck(MediaProcessor::url($name, 'original') === SITE_URL . '/uploads/original/img_1_0123456789abcdef.png', 'Full-size URL uses untouched source');
amCheck(MediaProcessor::originalStoragePath('../secret.png') === null, 'Traversal rejected');
amCheck(MediaProcessor::originalStoragePath('missing.png') === null, 'Missing originals stay unavailable');
amCheck(AlbumPages::ids('7, 1, 2, 1') === [7,1,2], 'Album selection preserves requested order and removes duplicates');
foreach (['', '1,abc', '-1', '0', implode(',', range(1,61)), '2147483648'] as $value) amReject(fn() => AlbumPages::ids($value), 'Invalid selection rejected');
amReject(fn() => AlbumPages::options(['album_title'=>'', 'page_size'=>'A4']), 'Empty title rejected');
amReject(fn() => AlbumPages::options(['per_page'=>3]), 'Unsupported layout rejected');
$insert = $pdo->prepare('INSERT INTO items VALUES (?,?,?,?,?,?,?,?)');
$mediaInsert = $pdo->prepare("INSERT INTO media VALUES (?,?,?,?,'image',1)");
for ($i=1; $i<=7; $i++) {
    $insert->execute([$i,'ST-'.$i,$i===2 ? str_repeat('Long stamp title ', 12) : 'India commemorative cover '.$i,'2026-11-02',str_repeat('Catalogue description with the issue and postal cancellation. ', 12),1,'SECRET-PRICE','SECRET-STORAGE']);
    if ($i !== 3) $mediaInsert->execute([$i,$i,$name,'Caption describing the complete uncropped cover.']);
}
$insert->execute([99,'HIDDEN','Private collection item','', '',0,'SECRET-PRICE','SECRET-STORAGE']);
amReject(fn() => AlbumPages::items($pdo,[99]), 'Hidden item excluded from shareable album');
amReject(fn() => AlbumPages::items($pdo,[987]), 'Nonexistent item excluded');
$items = AlbumPages::items($pdo,[7,1,2,3,4,5,6]);
amCheck(array_column($items,'id') === [7,1,2,3,4,5,6], 'Requested order applied to records');
amCheck(!array_key_exists('purchase_price',$items[0]) && !array_key_exists('private_notes',$items[0]), 'Private fields never selected');
foreach (['A4','letter'] as $paper) { foreach ([1,2,4,6] as $count) {
    $options = AlbumPages::options(['album_title'=>'Selected covers and stamps','per_page'=>$count,'page_size'=>$paper,'captions'=>1,'description'=>1,'qr'=>1]);
    $html = AlbumPages::html($items,$options);
    amCheck(!str_contains($html,'SECRET-'), 'Private values absent from album HTML');
    amCheck(str_contains($html,'data:image/svg+xml;base64,'), 'QR generated locally');
    $pdf = AlbumPages::pdf($items,$options);
    amCheck(str_starts_with($pdf,'%PDF-'), 'Real downloadable PDF generated');
    preg_match_all('#/Type /Page\b#',$pdf,$pages);
    amCheck(count($pages[0]) === (int)ceil(count($items)/$count), 'PDF pagination matches selected layout '.$count);
    if ($count === 4 && $paper === 'A4') file_put_contents(__DIR__ . '/.runtime/album-layout-qa.pdf',$pdf);
} }
$memoryLimit = ini_get('memory_limit');
ini_set('memory_limit', ((int)ceil(memory_get_usage(true) / 1048576) + 8) . 'M');
amReject(fn() => AlbumPages::pdf(array_fill(0,60,$items[0]),$options), 'Oversized album receives a useful error before exhausting memory');
ini_set('memory_limit', $memoryLimit);
echo "PASS: {$checks} album/media checks; isolated PDF fixture scripts/.runtime/album-layout-qa.pdf\n";
