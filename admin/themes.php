<?php
// admin/themes.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../config/config.php';

$error = '';
$success = '';

// Get active theme
$activeTheme = $appSettings['active_theme'] ?? 'default';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';
    $themeName = trim($_POST['theme'] ?? '');

    if ($action === 'activate' && $themeName && preg_match('/^[a-zA-Z0-9_\-]+$/', $themeName)) {
        // Validate theme exists
        if (is_dir(__DIR__ . '/../themes/' . $themeName)) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('active_theme', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$themeName, $themeName]);
            $appSettings['active_theme'] = $themeName;
            $activeTheme = $themeName;
            $success = "Theme '$themeName' activated successfully.";
        } else {
            $error = "Theme not found.";
        }
    }
}

// Discover themes
$themes = [];
$themeDir = __DIR__ . '/../themes';
if (is_dir($themeDir)) {
    foreach (scandir($themeDir) as $item) {
        if ($item === '.' || $item === '..') continue;
        if (is_dir($themeDir . '/' . $item)) {
            $themes[] = $item;
        }
    }
}

echo renderAdminHeader('Manage Themes');
?>

<div class="mb-6 flex justify-between items-end">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Manage Themes</h1>
        <p class="text-sm text-gray-500 mt-1">Select the active theme for the public frontend.</p>
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

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($themes as $theme): ?>
    <div class="bg-white rounded-lg border <?= $theme === $activeTheme ? 'border-2 border-blue-500 ring-2 ring-blue-200 shadow-md' : 'border-gray-200 shadow-sm' ?> overflow-hidden relative">
        <?php if ($theme === $activeTheme): ?>
            <div class="absolute top-0 right-0 bg-blue-500 text-white text-xs font-bold px-3 py-1 rounded-bl-lg z-10">ACTIVE</div>
        <?php endif; ?>
        
        <div class="h-40 bg-gray-100 flex items-center justify-center border-b border-gray-200">
            <!-- Theme screenshot could go here, for now just a placeholder -->
            <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
        
        <div class="p-4 flex flex-col justify-between h-32">
            <div>
                <h3 class="font-bold text-lg text-gray-900 capitalize"><?= htmlspecialchars($theme) ?> Theme</h3>
                <p class="text-xs text-gray-500 mt-1">Folder: <code>/themes/<?= htmlspecialchars($theme) ?>/</code></p>
            </div>
            
            <div class="mt-4 flex justify-end">
                <?php if ($theme !== $activeTheme): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="theme" value="<?= htmlspecialchars($theme) ?>">
                    <button type="submit" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded hover:bg-gray-800 transition">Activate</button>
                </form>
                <?php else: ?>
                    <span class="px-4 py-2 text-sm font-medium text-gray-400 cursor-not-allowed">Active</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?= renderAdminFooter(); ?>
