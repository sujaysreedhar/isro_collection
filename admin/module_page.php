<?php
// admin/module_page.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../config/config.php';

$moduleSlug = $_GET['m'] ?? '';

// Validate slug
if (!$moduleSlug || !preg_match('/^[a-zA-Z0-9_\-]+$/', $moduleSlug)) {
    die("Invalid module specified.");
}

// Check if module is active
$activeModulesJson = $appSettings['active_modules'] ?? '[]';
$activeModules = json_decode($activeModulesJson, true);
if (!is_array($activeModules)) $activeModules = [];

if (!in_array($moduleSlug, $activeModules)) {
    die("Module is not active.");
}

// Extract module info for the header
$modulesDir = __DIR__ . '/../modules';
$modFile    = $modulesDir . '/' . $moduleSlug . '/module.php';
$modJson    = $modulesDir . '/' . $moduleSlug . '/module.json';
$name       = $moduleSlug;
$wideMode   = false;

if (file_exists($modJson)) {
    $meta = json_decode(file_get_contents($modJson), true) ?? [];
    if (!empty($meta['name']))           $name     = $meta['name'];
    if (!empty($meta['wide_admin_page'])) $wideMode = true;
} elseif (file_exists($modFile)) {
    $content = file_get_contents($modFile, false, null, 0, 500);
    if (preg_match('/Module Name:\\s*(.*)/i', $content, $m)) $name = trim($m[1]);
}

// Allow modules to handle logic (like POST redirects) before any headers are sent
if (class_exists('HookRegistry')) {
    HookRegistry::doAction("admin_init_{$moduleSlug}");
}

echo renderAdminHeader($name . ' Settings', $wideMode);
?>

<?php if (!$wideMode): ?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($name) ?></h1>
</div>

<div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
<?php endif; ?>

    <?php
    // Give modules a chance to render their own interface here
    if (class_exists('HookRegistry')) {
        HookRegistry::doAction("admin_page_{$moduleSlug}");
    } else {
        echo "<p class='text-gray-500'>HookRegistry is not available.</p>";
    }
    ?>

<?php if (!$wideMode): ?>
</div>
<?php endif; ?>

<?= renderAdminFooter(); ?>
