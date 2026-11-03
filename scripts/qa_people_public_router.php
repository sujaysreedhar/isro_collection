<?php
declare(strict_types=1);
// Loopback-only preview using synthetic SQLite data. Never loads application configuration.
if (PHP_SAPI!=='cli-server' || !in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)) { http_response_code(404); exit; }
$root=dirname(__DIR__); $path=(string)parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (in_array($path,['/themes/custom/style.css','/themes/custom/archive.css','/themes/custom/archive.js','/themes/common/dist/tailwind.css','/modules/people/assets/people-public.css','/modules/people/assets/people-public.js'],true) || preg_match('#^/themes/custom/assets/icons/[a-z-]+\.svg$#D',$path)) {
    $file=$root.$path;
    if (!is_file($file)) { http_response_code(404); exit; }
    header('Content-Type: '.(str_ends_with($path,'.css')?'text/css':(str_ends_with($path,'.svg')?'image/svg+xml':'text/javascript'))); readfile($file); exit;
}
$fixtureRoot=__DIR__.'/.runtime/qa/people-public';
if (!is_dir($fixtureRoot)) mkdir($fixtureRoot,0755,true);
if (in_array($path,['/uploads/thumbnails/person_aaaaaaaaaaaaaaaaaaaaaaaa.webp','/uploads/original/person_aaaaaaaaaaaaaaaaaaaaaaaa.jpg','/uploads/display/fixture-stamp.png','/uploads/display/fixture-cover.png'],true)) {
    $portrait=str_contains($path,'person_'); $cover=str_contains($path,'cover');
    $image=imagecreatetruecolor($cover?1200:($portrait?720:600),$portrait?900:600);
    $paper=imagecolorallocate($image,237,239,238); $ink=imagecolorallocate($image,50,68,93);
    imagefill($image,0,0,$paper); imagesetthickness($image,8); imagerectangle($image,12,12,imagesx($image)-13,imagesy($image)-13,$ink);
    imagestring($image,5,32,(int)(imagesy($image)/2),$portrait?'COMPLETE PORTRAIT FRAME':($cover?'COMPLETE COVER FRAME':'COMPLETE STAMP FRAME'),$ink);
    header('Content-Type: image/png'); imagepng($image); exit;
}
define('ABSPATH',$root); define('CACHE_DIR',$fixtureRoot.'/cache');
define('SITE_URL','http://127.0.0.1:'.(int)$_SERVER['SERVER_PORT']); define('SITE_TITLE','Collection Archive');
require_once $root.'/includes/Autoloader.php'; require_once $root.'/modules/people/PeoplePublic.php'; require_once __DIR__.'/people_public_fixture.php';
function renderFrontendNav(string $active='',bool $mobile=false,string $slug='header'): void {
    echo '<nav><a href="'.SITE_URL.'/">Explore</a><a class="font-bold" href="'.SITE_URL.'/people.php">People</a></nav>';
}
$pdo=peoplePublicFixture(); $storage=null;
$appSettings=['active_theme'=>'custom','enable_cache'=>'0']; AppConfig::load($appSettings);
HookRegistry::addAction('frontend_head',static function(){ echo '<link rel="stylesheet" href="'.SITE_URL.'/themes/custom/archive.css">'; });
PeoplePublic::assets(); $currentMenu='people'; $bodyClass='archive-ui people-public-body';
if (preg_match('#^/person/([a-z0-9-]+)$#D',$path,$match)) {
    $person=PeoplePublic::profile($pdo,$match[1]);
    if (!$person) { http_response_code(404); echo 'Biography not found'; exit; }
    $relatedItems=PeoplePublic::records($pdo,(int)$person['id']); $facts=PeoplePublic::facts($person); $paragraphs=PeoplePublic::paragraphs($person['biography']);
    $pageTitle=$person['name'].' - Biography'; require ThemeManager::getHeader(); require $root.'/modules/people/views/profile.php'; require ThemeManager::getFooter();
} elseif ($path==='/' || $path==='/people.php') {
    $view=PeoplePublic::directory($pdo,$_GET); $pageTitle='People & Biographies';
    require ThemeManager::getHeader(); require $root.'/modules/people/views/directory.php'; require ThemeManager::getFooter();
} else { http_response_code(404); echo 'This fixture previews the biography directory and sample profiles only.'; }
