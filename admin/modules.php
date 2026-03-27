<?php
// admin/modules.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../config/config.php';

$error = '';
$success = '';

// Helper to update active modules in DB
function saveActiveModules($pdo, $activeModules) {
    global $appSettings;
    $json = json_encode(array_values($activeModules));
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('active_modules', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$json, $json]);
    $appSettings['active_modules'] = $json;
}

// Get current active
$activeModulesJson = $appSettings['active_modules'] ?? '[]';
$activeModules = json_decode($activeModulesJson, true);
if (!is_array($activeModules)) $activeModules = [];

$moduleManager = new ModuleManager($pdo, __DIR__ . '/../modules', $activeModules);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';
    $moduleSlug = trim($_POST['module'] ?? '');
    
    if ($moduleSlug && preg_match('/^[a-zA-Z0-9_\-]+$/', $moduleSlug)) {
        if ($action === 'enable') {
            if (!in_array($moduleSlug, $activeModules)) {
                $activeModules[] = $moduleSlug;
                saveActiveModules($pdo, $activeModules);
                
                // Use Manager to load and activate
                $instance = $moduleManager->loadModule($moduleSlug);
                if ($instance) {
                    $instance->activate();
                }
                $moduleManager->clearCache();
                $success = "Module '$moduleSlug' enabled.";
            }
        } elseif ($action === 'disable') {
            if (($key = array_search($moduleSlug, $activeModules)) !== false) {
                // Load it once to call deactivate if it's not already loaded
                $instance = $moduleManager->loadModule($moduleSlug);
                if ($instance) {
                    $instance->deactivate();
                }
                unset($activeModules[$key]);
                saveActiveModules($pdo, $activeModules);
                $moduleManager->clearCache();
                $success = "Module '$moduleSlug' disabled.";
            }
        }
    } else {
        $error = "Invalid module name.";
    }
}

// Re-instantiate ModuleManager with the updated active modules list so discoverModules() reflects the new state
$moduleManager = new ModuleManager($pdo, __DIR__ . '/../modules', $activeModules);

// Discover modules via Manager
$availableModules = $moduleManager->discoverModules();

// Sort by admin_menu_priority if available, otherwise by name
uasort($availableModules, function($a, $b) {
    $pA = $a['admin_menu_priority'] ?? 100;
    $pB = $b['admin_menu_priority'] ?? 100;
    if ($pA !== $pB) return $pA <=> $pB;
    return strcasecmp($a['name'], $b['name']);
});

$filter = $_GET['filter'] ?? 'all';
$filteredModules = $availableModules;
if ($filter === 'active') {
    $filteredModules = array_filter($availableModules, fn($m) => $m['is_active']);
} elseif ($filter === 'inactive') {
    $filteredModules = array_filter($availableModules, fn($m) => !$m['is_active']);
}

echo renderAdminHeader('Manage Modules');
?>

<div class="mb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Manage Modules</h1>
        <p class="text-sm text-gray-500 mt-1">Enable or disable extensible features of the application.</p>
    </div>
    <div class="flex bg-gray-100 p-1 rounded-lg">
        <a href="?filter=all" class="px-4 py-1.5 text-sm font-medium rounded-md <?= $filter === 'all' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-600 hover:text-gray-900' ?>">All</a>
        <a href="?filter=active" class="px-4 py-1.5 text-sm font-medium rounded-md <?= $filter === 'active' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-600 hover:text-gray-900' ?>">Active</a>
        <a href="?filter=inactive" class="px-4 py-1.5 text-sm font-medium rounded-md <?= $filter === 'inactive' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-600 hover:text-gray-900' ?>">Inactive</a>
    </div>
</div>

<?php if ($error): ?>
<div class="mb-5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded flex items-start gap-2">
    <span><?= htmlspecialchars($error) ?></span>
</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="mb-5 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded flex items-start gap-2">
    <span><?= htmlspecialchars($success) ?></span>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Module</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php if (empty($filteredModules)): ?>
            <tr><td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">No modules found matching the criteria.</td></tr>
            <?php else: ?>
                <?php foreach ($filteredModules as $mod): ?>
                <tr>
                    <td class="px-6 py-4">
                        <div class="text-sm font-bold text-gray-900"><?= htmlspecialchars($mod['name']) ?></div>
                        <div class="text-xs text-gray-500 mt-1">v<?= htmlspecialchars($mod['version']) ?> | by <?= htmlspecialchars($mod['author'] ?? 'Unknown') ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?= htmlspecialchars($mod['description']) ?>
                    </td>
                    <td class="px-6 py-4 text-right whitespace-nowrap">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                            <input type="hidden" name="module" value="<?= htmlspecialchars($mod['slug']) ?>">
                            <?php if ($mod['is_active']): ?>
                                <div class="flex items-center gap-2">
                                    <a href="<?= SITE_URL ?>/admin/module_page.php?m=<?= htmlspecialchars($mod['slug']) ?>" class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Settings
                                    </a>
                                    <input type="hidden" name="action" value="disable">
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-red-300 shadow-sm text-xs font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        Disable
                                    </button>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="action" value="enable">
                                <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-transparent shadow-sm text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Enable
                                </button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= renderAdminFooter(); ?>
