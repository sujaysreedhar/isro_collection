<?php
// scripts/test_variants.php
// End-to-end check for the Variants module. Start MySQL (XAMPP) first, then run:
//   F:\xampp\php\php.exe scripts\test_variants.php
// Creates temporary test data, exercises the module, prints PASS/FAIL, cleans up.

require_once __DIR__ . '/../config/config.php';
require_once ABSPATH . '/modules/variants/module.php';

function line($s) { echo $s . "\n"; }

line("--- Variants module verification ---");

$mod = new VariantsModule($pdo, 'variants', []);

// 1. Activate → create tables.
$mod->activate();
$tables = $pdo->query("SHOW TABLES LIKE 'module_variant_%'")->fetchAll(PDO::FETCH_COLUMN);
line("Tables present: " . implode(', ', $tables));
if (count($tables) < 2) { line("FAIL: tables not created"); exit(1); }

// 2. Two real visible items to use as variants.
$items = $pdo->query("SELECT id, title FROM items WHERE is_visible = 1 ORDER BY id ASC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
if (count($items) < 2) { line("SKIP: need at least 2 visible items in DB"); exit(0); }
$a = (int)$items[0]['id'];
$b = (int)$items[1]['id'];
line("Using items #$a and #$b");

// 3. Create a group and attach both items via the real save path.
$pdo->exec("DELETE FROM module_variant_groups WHERE slug LIKE 'zz-verify%'");
$pdo->prepare("INSERT INTO module_variant_groups (title, slug, group_type) VALUES ('ZZ Verify Cancellation', 'zz-verify-cxl', 'Cancellation')")->execute();
$gid = (int)$pdo->lastInsertId();

$_POST = ['variant_group_id' => (string)$gid, 'variant_label' => '1878 issue'];
$mod->handleItemSaved($a);
$_POST = ['variant_group_id' => (string)$gid, 'variant_label' => '1905 issue'];
$mod->handleItemSaved($b);

$count = (int)$pdo->query("SELECT COUNT(*) FROM module_variant_members WHERE group_id = $gid")->fetchColumn();
line(($count === 2 ? 'PASS' : 'FAIL') . " — two members attached (got $count)");

// 4. Render the switcher for item A and inspect the HTML.
$itemA = $pdo->query("SELECT * FROM items WHERE id = $a")->fetch(PDO::FETCH_ASSOC);
ob_start();
$mod->renderSwitcher($itemA);
$html = ob_get_clean();

$checks = [
    'renders container'     => str_contains($html, 'variant-switcher'),
    'shows group title'     => str_contains($html, 'ZZ Verify Cancellation'),
    'shows 1878 label'      => str_contains($html, '1878 issue'),
    'shows 1905 label'      => str_contains($html, '1905 issue'),
    'links to sibling item' => str_contains($html, '/item/' . $b),
    'marks current Viewing' => str_contains($html, 'Viewing'),
];
foreach ($checks as $name => $ok) {
    line(($ok ? 'PASS' : 'FAIL') . " — $name");
}

// 5. Render guard: second call for same item outputs nothing.
ob_start();
$mod->renderSwitcher($itemA);
line((trim(ob_get_clean()) === '' ? 'PASS' : 'FAIL') . " — render guard suppresses duplicate");

// 6. Clearing the group removes membership.
$_POST = ['variant_group_id' => '', 'variant_label' => ''];
$mod->handleItemSaved($a);
$after = (int)$pdo->query("SELECT COUNT(*) FROM module_variant_members WHERE item_id = $a")->fetchColumn();
line(($after === 0 ? 'PASS' : 'FAIL') . " — clearing group removes membership");

// Cleanup (leaves the tables in place).
$pdo->exec("DELETE FROM module_variant_groups WHERE id = $gid");
$_POST = [];
line("Cleaned up test group. Done.");
