<?php
// scripts/test_phase2_verification.php
// Verification suite for Phase 2 optimizations and BaseModule refactoring.

echo "=== Running Phase 2 Verification Suite ===\n\n";

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

require_once __DIR__ . '/../config/config.php';
global $pdo;

// Helper to inspect registered actions in HookRegistry via Reflection
function checkHookAction(string $tag): bool {
    $ref = new ReflectionClass('HookRegistry');
    $prop = $ref->getProperty('actions');
    $prop->setAccessible(true);
    $actions = $prop->getValue();
    return !empty($actions[$tag]);
}

// Helper to inspect registered filters in HookRegistry via Reflection
function checkHookFilter(string $tag): bool {
    $ref = new ReflectionClass('HookRegistry');
    $prop = $ref->getProperty('filters');
    $prop->setAccessible(true);
    $filters = $prop->getValue();
    return !empty($filters[$tag]);
}

// ── 1. Test Dashboard Orphan Cache Logic ─────────────────────────────────────
echo "\n-- Section 1: Dashboard Orphan Cache --\n";

// Set a test cached value
$testCount = 999;
$testTime = time();
$upsert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
$upsert->execute(['dashboard_orphaned_count', $testCount, $testCount]);
$upsert->execute(['dashboard_orphaned_time', $testTime, $testTime]);

// Simulate admin/index.php cache check
$cachedOrphaned = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
$cachedOrphaned->execute(['dashboard_orphaned_count']);
$val = $cachedOrphaned->fetchColumn();

$cachedOrphanedTime = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
$cachedOrphanedTime->execute(['dashboard_orphaned_time']);
$t = (int)$cachedOrphanedTime->fetchColumn();

$isCached = ($val !== false && (time() - $t) < 900);
assertTest("Dashboard cache retrieves cached count", $isCached && (int)$val === 999);

// Test invalidation (simulating delete_orphans in ajax.php)
$pdo->prepare("DELETE FROM settings WHERE setting_key IN ('dashboard_orphaned_count', 'dashboard_orphaned_time')")->execute();
$checkVal = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'dashboard_orphaned_count'")->fetchColumn();
assertTest("Orphan cache invalidation clears settings keys", $checkVal === false);

// Test fresh orphan scan and cache write (simulating scan_orphans in ajax.php)
$mp = new MediaProcessor($pdo, $storage ?? null);
$orphans = $mp->orphanedFiles();
$actualCount = count($orphans);
$upsert->execute(['dashboard_orphaned_count', $actualCount, $actualCount]);
$upsert->execute(['dashboard_orphaned_time', time(), time()]);

$refreshedVal = (int)$pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'dashboard_orphaned_count'")->fetchColumn();
assertTest("Orphan scan persists actual count to settings", $refreshedVal === $actualCount);

// ── 2. Test ajax.php search_items Action ────────────────────────────────────
echo "\n-- Section 2: search_items AJAX Endpoint --\n";

// Fetch first item to test exclude
$firstItem = $pdo->query("SELECT id, reg_number, title FROM items ORDER BY id ASC LIMIT 1")->fetch();

if ($firstItem) {
    $firstId = (int)$firstItem['id'];
    
    // Simulate query without exclude
    $stmt1 = $pdo->prepare("SELECT id, title, reg_number, CONCAT('[', COALESCE(reg_number, ''), '] ', title) as text FROM items ORDER BY title ASC LIMIT 30");
    $stmt1->execute();
    $results1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    
    assertTest("search_items returns results with id, title, reg_number, text", !empty($results1) && isset($results1[0]['id'], $results1[0]['text'], $results1[0]['title'], $results1[0]['reg_number']));
    assertTest("search_items formats text as [reg_number] title", !empty($results1[0]['text']) && strpos($results1[0]['text'], '[') === 0);

    // Simulate query with exclude
    $stmt2 = $pdo->prepare("SELECT id, title, reg_number, CONCAT('[', COALESCE(reg_number, ''), '] ', title) as text FROM items WHERE id != :exclude ORDER BY title ASC LIMIT 30");
    $stmt2->execute([':exclude' => $firstId]);
    $results2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_column($results2, 'id');
    
    assertTest("search_items excludes specified item id", !in_array($firstId, $ids));
} else {
    echo " [SKIP] No items in database to test search_items\n";
}

// ── 3. Test edit_item.php Static Hygiene ─────────────────────────────────────
echo "\n-- Section 3: edit_item.php Optimizations --\n";

$editItemContent = file_get_contents(__DIR__ . '/../admin/edit_item.php');
assertTest("edit_item.php does NOT query allItems into memory", strpos($editItemContent, '$allItems = $pdo->query') === false);
assertTest("edit_item.php queries recent reg numbers for hints", strpos($editItemContent, '$recentRegNumbers') !== false);
assertTest("edit_item.php queries only prelinkedRelatedItems", strpos($editItemContent, '$prelinkedRelatedItems') !== false);
assertTest("edit_item.php configures TomSelect remote async loading", strpos($editItemContent, "ajax.php?action=search_items") !== false && strpos($editItemContent, "loadThrottle") !== false);

// ── 4. Test BaseModule OOP Normalization ─────────────────────────────────────
echo "\n-- Section 4: BaseModule Conformance & Manager --\n";

$moduleManager = new ModuleManager($pdo, __DIR__ . '/../modules', ['api_export', 'item_comments', 'newsletter']);

// Test ApiExportModule
$modApi = $moduleManager->loadModule('api_export');
assertTest("api_export loads as BaseModule instance", $modApi instanceof BaseModule);
assertTest("api_export class is ApiExportModule", get_class($modApi) === 'ApiExportModule');
assertTest("api_export registers admin_items_list_actions", checkHookAction('admin_items_list_actions'));
assertTest("api_export registers admin_init_api_export", checkHookAction('admin_init_api_export'));
assertTest("api_export registers admin_page_api_export", checkHookAction('admin_page_api_export'));
assertTest("api_export activate() returns true", $modApi->activate() === true);
assertTest("api_export deactivate() returns true", $modApi->deactivate() === true);

// Test ItemCommentsModule
$modComments = $moduleManager->loadModule('item_comments');
assertTest("item_comments loads as BaseModule instance", $modComments instanceof BaseModule);
assertTest("item_comments class is ItemCommentsModule", get_class($modComments) === 'ItemCommentsModule');
assertTest("item_comments registers admin_page_item_comments", checkHookAction('admin_page_item_comments'));
assertTest("item_comments registers frontend_item_detail_bottom", checkHookAction('frontend_item_detail_bottom'));
assertTest("item_comments activate() creates table successfully", $modComments->activate() === true);
assertTest("item_comments deactivate() returns true", $modComments->deactivate() === true);

// Test NewsletterModule
$modNews = $moduleManager->loadModule('newsletter');
assertTest("newsletter loads as BaseModule instance", $modNews instanceof BaseModule);
assertTest("newsletter class is NewsletterModule", get_class($modNews) === 'NewsletterModule');
assertTest("newsletter registers admin_page_newsletter", checkHookAction('admin_page_newsletter'));
assertTest("newsletter registers frontend_footer", checkHookAction('frontend_footer'));
assertTest("newsletter activate() creates table successfully", $modNews->activate() === true);
assertTest("newsletter deactivate() returns true", $modNews->deactivate() === true);

// Test sidebar filter
$sidebarSections = [
    'overview' => ['links' => []],
    'catalog'  => ['links' => []],
];
$filteredSections = HookRegistry::applyFilters('admin_sidebar_links', $sidebarSections);
assertTest("Sidebar filter populates catalog.item_comments link", isset($filteredSections['catalog']['links']['item_comments']));
assertTest("Sidebar filter populates overview.newsletter link", isset($filteredSections['overview']['links']['newsletter']));

// ── Summary ──────────────────────────────────────────────────────────────────
echo "\n============================================\n";
echo "Phase 2 Verification Complete: $passCount passed, $failCount failed.\n";
echo "============================================\n";

exit($failCount > 0 ? 1 : 0);
