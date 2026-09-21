<?php
// scripts/test_phase1_verification.php
// Verification suite for Phase 1 fixes.

echo "=== Running Phase 1 Verification Suite ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $name, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $name\n";
        $passCount++;
    } else {
        echo " [FAIL] $name" . ($detail ? " ($detail)" : "") . "\n";
        $failCount++;
    }
}

// 1. Verify .htaccess files exist
$uploadsHtaccess = __DIR__ . '/../uploads/.htaccess';
$tmpHtaccess = __DIR__ . '/../tmp/.htaccess';

assertTest("uploads/.htaccess exists", file_exists($uploadsHtaccess));
if (file_exists($uploadsHtaccess)) {
    $content = file_get_contents($uploadsHtaccess);
    assertTest("uploads/.htaccess blocks php execution", strpos($content, 'php_flag engine off') !== false && strpos($content, 'FilesMatch') !== false);
}

assertTest("tmp/.htaccess exists", file_exists($tmpHtaccess));
if (file_exists($tmpHtaccess)) {
    $content = file_get_contents($tmpHtaccess);
    assertTest("tmp/.htaccess denies all access", strpos($content, 'Require all denied') !== false);
}

// 2. Test Csrf class and shim
require_once __DIR__ . '/../includes/Csrf.php';
assertTest("Csrf class exists", class_exists('Csrf'));

$token = Csrf::getToken();
assertTest("Csrf::getToken returns 64-char hex", is_string($token) && strlen($token) === 64);
assertTest("Csrf::verify succeeds with valid token", Csrf::verify($token));
assertTest("Csrf::verify rejects invalid token", !Csrf::verify('invalid_token_123'));
assertTest("Csrf::verify rejects null", !Csrf::verify(null));

$inputHtml = Csrf::input();
assertTest("Csrf::input renders hidden field", strpos($inputHtml, '<input type="hidden" name="csrf_token"') !== false && strpos($inputHtml, $token) !== false);

assertTest("Global ensureCsrfToken works", ensureCsrfToken() === $token);
assertTest("Global verifyCsrfToken works", verifyCsrfToken($token));


// 3. Test api_export columns
require_once __DIR__ . '/../config/config.php';
global $pdo;

// Test query that was previously failing with historical_context
try {
    $stmt = $pdo->prepare("SELECT id, reg_number, title, production_date, year_start, year_end, material, physical_description, historical_significance, credit_line FROM items LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    assertTest("Items query with historical_significance succeeds", true);
} catch (Exception $e) {
    assertTest("Items query with historical_significance succeeds", false, $e->getMessage());
}

// Check api.php file content for old bugs
$apiContent = file_get_contents(__DIR__ . '/../modules/api_export/api.php');
assertTest("api.php no longer references historical_context", strpos($apiContent, 'historical_context') === false);
assertTest("api.php uses /uploads/thumbnails/", strpos($apiContent, '/uploads/thumbnails/') !== false);
assertTest("api.php does not use /uploads/thumbs/", strpos($apiContent, '/uploads/thumbs/') === false);

// Check export_csv.php
$csvContent = file_get_contents(__DIR__ . '/../modules/api_export/export_csv.php');
assertTest("export_csv.php no longer references historical_context", strpos($csvContent, 'historical_context') === false);
assertTest("export_csv.php no longer references creation_date", strpos($csvContent, 'creation_date') === false);
assertTest("export_csv.php references historical_significance", strpos($csvContent, 'historical_significance') !== false);

// 4. Test SearchEngine visibility
require_once __DIR__ . '/../includes/SearchEngine.php';
$engine = new SearchEngine($pdo);
$reflection = new ReflectionClass($engine);
$buildWhere = $reflection->getMethod('buildWhere');
$buildWhere->setAccessible(true);

$wherePublic = $buildWhere->invoke($engine, []);
assertTest("SearchEngine buildWhere includes is_visible = 1 by default", in_array('i.is_visible = 1', $wherePublic['where']));

$whereAdmin = $buildWhere->invoke($engine, ['include_hidden' => true]);
assertTest("SearchEngine buildWhere allows include_hidden override", !in_array('i.is_visible = 1', $whereAdmin['where']));

// 5. Test home.php query
$homeContent = file_get_contents(__DIR__ . '/../includes/pages/home.php');
assertTest("home.php filters by is_visible = 1", strpos($homeContent, 'WHERE i.is_visible = 1') !== false);

// 6. Test item_detail.php visibility and 404
$detailContent = file_get_contents(__DIR__ . '/../includes/pages/item_detail.php');
assertTest("item_detail.php enforces is_visible = 1 for non-admins", strpos($detailContent, 'is_visible = 1') !== false);
assertTest("item_detail.php renders 404 template instead of die()", strpos($detailContent, "ThemeManager::getTemplatePath('404.php')") !== false);

// 7. Test newsletter heredoc fix
$newsContent = file_get_contents(__DIR__ . '/../modules/newsletter/module.php');
assertTest("newsletter module does not contain literal <?= in heredoc", strpos($newsContent, '<?=') === false);
assertTest("newsletter module hooks into admin_sidebar_links", strpos($newsContent, 'admin_sidebar_links') !== false);
assertTest("newsletter module hooks into admin_page_newsletter", strpos($newsContent, 'admin_page_newsletter') !== false);

// 8. Test item_comments module
$commContent = file_get_contents(__DIR__ . '/../modules/item_comments/module.php');
assertTest("item_comments module hooks into admin_sidebar_links", strpos($commContent, 'admin_sidebar_links') !== false);
assertTest("item_comments module hooks into admin_page_item_comments", strpos($commContent, 'admin_page_item_comments') !== false);

// 9. Test admin/login.php CSRF
$loginContent = file_get_contents(__DIR__ . '/../admin/login.php');
assertTest("admin/login.php checks verifyCsrfToken", strpos($loginContent, 'verifyCsrfToken') !== false);
assertTest("admin/login.php renders csrf_token input", strpos($loginContent, 'name="csrf_token"') !== false);

echo "\n=========================================\n";
echo "Results: {$passCount} Passed, {$failCount} Failed\n";
echo "=========================================\n";

exit($failCount > 0 ? 1 : 0);
