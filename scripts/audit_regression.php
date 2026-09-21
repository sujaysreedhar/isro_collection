<?php
declare(strict_types=1);
require_once __DIR__ . '/audit_test_bootstrap.php';

$groups = ['access', 'visibility', 'csrf', 'media', 'xss', 'abuse', 'errors', 'search', 'backup'];
$group = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--group=')) $group = substr($argument, 8);
}
if (!in_array($group, $groups, true)) {
    fwrite(STDERR, "Usage: php scripts/audit_regression.php --group=<" . implode('|', $groups) . "> [--allow-disposable-mutations]\n");
    exit(2);
}
try {
    if ($group === 'access') {
        $base = rtrim(getenv('AUDIT_BASE_URL') ?: 'http://localhost/collection', '/');
        foreach (['/.git/HEAD', '/.git/config', '/update.sql'] as $path) {
            $headers = @get_headers($base . $path);
            auditCheck(is_array($headers), "Unavailable: HTTP endpoint $path");
            auditCheck(preg_match('#^HTTP/\S+ (403|404)\b#', $headers[0]) === 1, "Exposed: $path returned {$headers[0]}");
        }
        $headers = @get_headers($base . '/');
        auditCheck(is_array($headers) && preg_match('#^HTTP/\S+ 200\b#', $headers[0]) === 1, 'Homepage unavailable.');
    } elseif ($group === 'csrf') {
        session_save_path(sys_get_temp_dir());
        require_once __DIR__ . '/../includes/Csrf.php';
        Csrf::initSession();
        $_SESSION['csrf_token'] = str_repeat('a', 64);
        auditCheck(moduleCsrfAllowed('GET', [], []) === true, 'GET read blocked.');
        auditCheck(moduleCsrfAllowed('POST', [], []) === false, 'Missing token accepted.');
        auditCheck(moduleCsrfAllowed('POST', ['csrf_token' => ['bad']], []) === false, 'Array token accepted.');
        auditCheck(moduleCsrfAllowed('POST', ['csrf_token' => 'wrong'], []) === false, 'Incorrect token accepted.');
        auditCheck(moduleCsrfAllowed('POST', ['csrf_token' => str_repeat('a', 64)], []) === true, 'Valid token rejected.');
    } elseif ($group === 'errors') {
        require_once __DIR__ . '/../includes/PublicError.php';
        $log = tempnam(sys_get_temp_dir(), 'audit-error-');
        ini_set('error_log', $log);
        $payload = PublicError::payload(new RuntimeException('SQL secret-host C:\\private\\config.php'));
        auditCheck(($payload['error'] ?? '') === 'Service temporarily unavailable.', 'Unexpected public error wording.');
        auditCheck(preg_match('/^[a-f0-9]{16}$/', $payload['error_id'] ?? '') === 1, 'Missing correlation ID.');
        auditCheck(!str_contains(json_encode($payload), 'secret-host'), 'Internal exception disclosed.');
        auditCheck(str_contains((string)file_get_contents($log), $payload['error_id']), 'Correlation ID not logged.');
        unlink($log);
    } elseif ($group === 'backup') {
        class HookRegistry {
            public static array $actions = [];
            public static function addAction(string $name, callable $callback): void { self::$actions[$name][] = $callback; }
            public static function addFilter(string $name, callable $callback): void {}
        }
        require_once __DIR__ . '/../includes/BaseModule.php';
        require_once __DIR__ . '/../modules/auto_backup/module.php';
        $module = new AutoBackupModule(new PDO('sqlite::memory:'), 'auto_backup', []);
        $module->boot();
        auditCheck(empty(HookRegistry::$actions['admin_head']), 'Admin page render still triggers automatic backup.');
        auditCheck(is_file(__DIR__ . '/run_auto_backup.php'), 'Scheduled CLI entry point missing.');
    } elseif ($group === 'abuse') {
        $pdo = auditFixturePdo($argv);
        $pdo->exec('CREATE TABLE request_rate_limits (scope TEXT, key_hash TEXT, window_start INTEGER, attempts INTEGER, expires_at INTEGER, PRIMARY KEY(scope,key_hash,window_start))');
        require_once __DIR__ . '/../includes/RateLimiter.php';
        auditCheck(RateLimiter::consume($pdo, 'login', 'person@ip', 2, 900)['allowed'], 'First attempt denied.');
        auditCheck(RateLimiter::consume($pdo, 'login', 'person@ip', 2, 900)['allowed'], 'Second attempt denied.');
        $result = RateLimiter::consume($pdo, 'login', 'person@ip', 2, 900);
        auditCheck(!$result['allowed'] && $result['retry_after'] > 0, 'Third attempt not limited.');
        auditCheck(RateLimiter::consume($pdo, 'login', 'other@ip', 2, 900)['allowed'], 'Independent key limited.');
        auditCheck($pdo->query('SELECT key_hash FROM request_rate_limits')->fetchColumn() !== 'person@ip', 'Raw limiter key stored.');
    } elseif ($group === 'media') {
        require_once __DIR__ . '/../includes/ImageUploadValidator.php';
        require_once __DIR__ . '/../includes/BlogImage.php';
        require_once __DIR__ . '/../includes/MediaProcessor.php';
        auditCheck(BlogImage::path('thumbnails/blog_' . str_repeat('a', 32) . '_1200.webp', 640) === 'thumbnails/blog_' . str_repeat('a', 32) . '_640.webp', 'New blog variant not selected.');
        auditCheck(BlogImage::path('blog/legacy.jpg', 640) === 'blog/legacy.jpg', 'Legacy blog image changed.');
        auditCheck(MediaProcessor::variantStoragePath('img_1_abcdef0123456789_1200.webp', 'card') === 'thumbnails/img_1_abcdef0123456789_640.webp', 'New card variant not selected.');
        auditCheck(MediaProcessor::variantStoragePath('legacy.webp', 'display') === 'display/legacy.webp', 'Legacy display fallback changed.');
        auditCheck(MediaProcessor::variantStoragePath('legacy.webp', 'card') === 'display/legacy.webp', 'Legacy card fallback changed.');
        auditCheck(MediaProcessor::normalizeStoragePath('display/img_1_abcdef0123456789_1200.webp') === 'thumbnails/img_1_abcdef0123456789_1200.webp', 'Storage URL did not resolve new variant.');
        auditCheck(MediaProcessor::normalizeStoragePath('thumbnails/img_1_abcdef0123456789_1200.webp') === 'thumbnails/img_1_abcdef0123456789_200.webp', 'Storage URL did not resolve new thumbnail.');
        require_once __DIR__ . '/../includes/LocalStorage.php';
        $legacyDir = sys_get_temp_dir() . '/audit-legacy-' . bin2hex(random_bytes(4));
        mkdir($legacyDir . '/thumbs', 0700, true);
        file_put_contents($legacyDir . '/thumbs/legacy.webp', 'legacy');
        try {
            $local = new LocalStorage($legacyDir, 'http://example.test/uploads');
            auditCheck(str_ends_with(MediaProcessor::url('legacy.webp', 'thumbnails', 'image', $local), '/thumbs/legacy.webp'), 'Legacy thumbnail fallback lost.');
        } finally {
            unlink($legacyDir . '/thumbs/legacy.webp');
            rmdir($legacyDir . '/thumbs');
            rmdir($legacyDir);
        }
        $valid = tempnam(sys_get_temp_dir(), 'audit-image-');
        $bad = tempnam(sys_get_temp_dir(), 'audit-image-');
        $image = imagecreatetruecolor(4, 3);
        imagepng($image, $valid);
        imagedestroy($image);
        file_put_contents($bad, '<script>alert(1)</script>');
        try {
            $info = ImageUploadValidator::inspect($valid, false);
            auditCheck($info['width'] === 4 && $info['height'] === 3, 'Valid image rejected.');
            $card = tempnam(sys_get_temp_dir(), 'audit-card-');
            try {
                auditCheck(MediaProcessor::optimizeImage($valid, $card, 640, 640), 'WebP card encode failed.');
                auditCheck(getimagesize($card)[0] === 4 && getimagesize($card)[1] === 3, 'Small image was enlarged.');
            } finally { unlink($card); }
            try { ImageUploadValidator::inspect($bad, false); throw new RuntimeException('HTML accepted as image.'); }
            catch (DomainException $expected) {}
        } finally {
            unlink($valid);
            unlink($bad);
        }
    } elseif ($group === 'search') {
        if (!defined('SITE_URL')) define('SITE_URL', 'http://example.test');
        if (!defined('CACHE_DIR')) define('CACHE_DIR', sys_get_temp_dir() . '/audit-search-cache-' . bin2hex(random_bytes(4)));
        mkdir(CACHE_DIR, 0700, true);
        $pdo = auditFixturePdo($argv);
        $pdo->exec('CREATE TABLE blog_posts (id INTEGER PRIMARY KEY, title TEXT, content TEXT, slug TEXT, status TEXT, published_at TEXT, featured_image TEXT)');
        $pdo->exec('CREATE TABLE blog_post_tag (post_id INTEGER, tag_id INTEGER)');
        $pdo->exec('CREATE TABLE tags (id INTEGER, name TEXT)');
        for ($i = 1; $i <= 5; $i++) {
            $pdo->exec("INSERT INTO blog_posts VALUES ($i,'needle $i','body','post-$i','published','2026-09-20','')");
        }
        require_once __DIR__ . '/../includes/BaseModule.php';
        require_once __DIR__ . '/../includes/HookRegistry.php';
        require_once __DIR__ . '/../modules/blog/module.php';
        HookRegistry::reset();
        (new BlogModule($pdo, 'blog', []))->boot();
        auditCheck(HookRegistry::applyFilters('search_provider_counts', ['blog' => 0, 'people' => 0], ['q' => 'needle'])['blog'] === 5, 'Blog matches absent from total.');
        $page = HookRegistry::applyFilters('search_results', [], ['q' => 'needle', 'blog_limit' => 2, 'blog_offset' => 2]);
        auditCheck(count($page) === 2 && $page[0]['id'] === 'blog_3', 'Blog page is unbounded or unordered.');
        require_once __DIR__ . '/../includes/SearchEngine.php';
        require_once __DIR__ . '/../includes/AppConfig.php';
        auditCheck(SearchEngine::validateParams(['q' => str_repeat('a', 201)]) !== null, 'Oversize query accepted.');
        auditCheck(SearchEngine::validateParams(['q' => ['bad']]) !== null, 'Array query accepted.');
        auditCheck(SearchEngine::validateParams(['q' => 'needle']) === null, 'Valid query rejected.');
        auditCheck(SearchEngine::paginationPlan(3, 2, 2) === ['item_limit' => 1, 'item_offset' => 2, 'blog_limit' => 1, 'blog_offset' => 0], 'Mixed page boundary incorrect.');
        auditCheck(SearchEngine::paginationPlan(3, 2, 4) === ['item_limit' => 0, 'item_offset' => 4, 'blog_limit' => 2, 'blog_offset' => 1], 'Blog-only page boundary incorrect.');
        $engine = new SearchEngine($pdo);
        auditCheck((new ReflectionMethod(SearchEngine::class, 'getVocabulary'))->invoke($engine) === [], 'Missing cache triggered visitor query.');
        $pdo->exec('CREATE TABLE categories (name TEXT)');
        $pdo->exec('CREATE TABLE items (title TEXT, physical_description TEXT, credit_line TEXT, production_date TEXT, is_visible INTEGER)');
        $pdo->exec("INSERT INTO items VALUES ('postmark','blue','','2026',1)");
        $engine->rebuildVocabularyCache();
        $engine->rebuildVocabularyCache();
        auditCheck(is_file(CACHE_DIR . '/vocabulary.json'), 'Vocabulary cache not published.');
        auditCheck(in_array('postmark', json_decode(file_get_contents(CACHE_DIR . '/vocabulary.json'), true), true), 'Vocabulary content missing.');
        unlink(CACHE_DIR . '/vocabulary.json');
        unlink(CACHE_DIR . '/vocabulary.lock');
        rmdir(CACHE_DIR);
    } elseif ($group === 'visibility') {
        $pdo = auditFixturePdo($argv);
        $pdo->exec('CREATE TABLE module_sets (id INTEGER PRIMARY KEY, slug TEXT, is_public INTEGER, target_count INTEGER)');
        $pdo->exec('CREATE TABLE module_set_structure (id INTEGER, set_id INTEGER, sort_order INTEGER, label TEXT)');
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, title TEXT, is_visible INTEGER)');
        $pdo->exec('CREATE TABLE module_set_items (set_id INTEGER, item_id INTEGER, structure_id INTEGER)');
        $pdo->exec("INSERT INTO module_sets VALUES (1,'public',1,2),(2,'private',0,1)");
        $pdo->exec("INSERT INTO items VALUES (1,'Visible',1),(2,'Secret',0)");
        $pdo->exec('INSERT INTO module_set_items VALUES (1,1,NULL),(1,2,NULL)');
        require_once __DIR__ . '/../includes/BaseModule.php';
        require_once __DIR__ . '/../modules/set_manager/module.php';
        $sets = new SetManagerModule($pdo, 'set_manager', []);
        auditCheck($sets->getPublicSetBySlug('private') === false, 'Private slug is public.');
        auditCheck($sets->getPublicSetBySlug('public') !== false, 'Public slug missing.');
        auditCheck($sets->getSetProgress(1, true)['count'] === 1, 'Hidden item included in public progress.');
        auditCheck($sets->getSetProgress(1)['count'] === 2, 'Admin progress changed.');
        $pdo->exec('CREATE TABLE postmark_locations (id INTEGER, pin_code TEXT, post_office TEXT, ppc_name TEXT, district TEXT, state TEXT, latitude REAL, longitude REAL, is_acquired INTEGER, linked_item_id INTEGER, is_locked INTEGER)');
        $pdo->exec("INSERT INTO postmark_locations VALUES (1,'1','Visible Office','','D','S',1,1,1,1,0),(2,'2','Hidden Office','','D','S',1,1,1,2,1)");
        require_once __DIR__ . '/../modules/postmark_atlas/PublicLocations.php';
        $locations = PublicLocations::all($pdo, false);
        auditCheck(count($locations) === 2, 'Location markers removed.');
        auditCheck((int)$locations[0]['linked_item_id'] === 1, 'Visible item link lost.');
        auditCheck($locations[1]['linked_item_id'] === null && $locations[1]['linked_item_title'] === null, 'Hidden item link disclosed.');
        auditCheck(!array_key_exists('is_locked', $locations[1]), 'Internal atlas metadata disclosed.');
    } else {
        auditFixturePdo($argv);
        throw new RuntimeException("Unimplemented behavioral group: $group");
    }
    echo "PASS $group\n";
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL $group: " . $error->getMessage() . "\n");
    exit(1);
}
