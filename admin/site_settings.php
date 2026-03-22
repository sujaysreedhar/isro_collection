<?php
// admin/site_settings.php — General site branding & configuration
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../config/config.php';

$error   = '';
$success = '';

// Ensure branding upload directory exists
$logoDir = __DIR__ . '/../uploads/branding';
if (!is_dir($logoDir)) {
    @mkdir($logoDir, 0755, true);
}

// Current settings
$siteLogo    = $appSettings['site_logo'] ?? '';
$siteFavicon = $appSettings['site_favicon'] ?? '';
$siteUrl     = $appSettings['site_url']   ?? SITE_URL;
$siteTitle   = $appSettings['site_title'] ?? SITE_TITLE;
$siteDesc    = $appSettings['site_description'] ?? '';
$debugMode   = $appSettings['debug_mode'] ?? '0';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? 'save';

    // --- Logo actions ---
    if ($action === 'remove_logo') {
        if ($siteLogo && file_exists($logoDir . '/' . $siteLogo)) @unlink($logoDir . '/' . $siteLogo);
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('site_logo', '') ON DUPLICATE KEY UPDATE setting_value = ''")->execute();
        $siteLogo = '';
        $success = 'Logo removed.';

    } elseif ($action === 'upload_logo') {
        $result = handleBrandingUpload('logo_file', 'site_logo', $siteLogo, $logoDir, $pdo,
            ['image/png','image/jpeg','image/gif','image/svg+xml','image/webp'], 2*1024*1024);
        if ($result['error']) $error = $result['error'];
        else { $siteLogo = $result['filename']; $success = 'Logo uploaded!'; }

    // --- Favicon actions ---
    } elseif ($action === 'remove_favicon') {
        if ($siteFavicon && file_exists($logoDir . '/' . $siteFavicon)) @unlink($logoDir . '/' . $siteFavicon);
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('site_favicon', '') ON DUPLICATE KEY UPDATE setting_value = ''")->execute();
        $siteFavicon = '';
        $success = 'Favicon removed.';

    } elseif ($action === 'upload_favicon') {
        $result = handleBrandingUpload('favicon_file', 'site_favicon', $siteFavicon, $logoDir, $pdo,
            ['image/png','image/x-icon','image/vnd.microsoft.icon','image/svg+xml','image/gif','image/webp'], 1*1024*1024);
        if ($result['error']) $error = $result['error'];
        else { $siteFavicon = $result['filename']; $success = 'Favicon uploaded!'; }

    // --- General settings ---
    } elseif ($action === 'save_general') {
        $newUrl   = rtrim(trim($_POST['site_url'] ?? ''), '/');
        $newTitle = trim($_POST['site_title'] ?? '');
        $newDesc  = trim($_POST['site_description'] ?? '');
        if (empty($newUrl) || empty($newTitle)) {
            $error = 'Site URL and Site Title are required.';
        } else {
            $newDebug = isset($_POST['debug_mode']) ? '1' : '0';
            $upsert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2");
            $upsert->execute([':k' => 'site_url',         ':v' => $newUrl,   ':v2' => $newUrl]);
            $upsert->execute([':k' => 'site_title',        ':v' => $newTitle, ':v2' => $newTitle]);
            $upsert->execute([':k' => 'site_description',  ':v' => $newDesc,  ':v2' => $newDesc]);
            $upsert->execute([':k' => 'debug_mode',        ':v' => $newDebug, ':v2' => $newDebug]);
            $siteUrl = $newUrl; $siteTitle = $newTitle; $siteDesc = $newDesc; $debugMode = $newDebug;
            $success = 'Settings saved. Changes apply on the next page load.';
        }
    }
}

/**
 * Generic handler for branding file uploads (logo / favicon)
 */
function handleBrandingUpload(string $inputName, string $settingsKey, string $currentFile, string $dir, PDO $pdo, array $allowedMimes, int $maxSize): array {
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'No file selected or upload error.', 'filename' => $currentFile];
    }
    $tmp  = $_FILES[$inputName]['tmp_name'];
    $orig = $_FILES[$inputName]['name'];
    $size = $_FILES[$inputName]['size'];
    $mime = mime_content_type($tmp);

    if (!in_array($mime, $allowedMimes)) return ['error' => 'Invalid file type.', 'filename' => $currentFile];
    if ($size > $maxSize) return ['error' => 'File too large. Max ' . ($maxSize / 1024 / 1024) . ' MB.', 'filename' => $currentFile];

    if ($currentFile && file_exists($dir . '/' . $currentFile)) @unlink($dir . '/' . $currentFile);

    $ext = pathinfo($orig, PATHINFO_EXTENSION) ?: 'png';
    $newName = $settingsKey . '_' . time() . '.' . strtolower($ext);
    if (!move_uploaded_file($tmp, $dir . '/' . $newName)) {
        return ['error' => 'Failed to save. Check permissions.', 'filename' => $currentFile];
    }

    $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")
        ->execute([$settingsKey, $newName, $newName]);
    return ['error' => null, 'filename' => $newName];
}

$logoUrl    = $siteLogo    ? SITE_URL . '/uploads/branding/' . rawurlencode($siteLogo) : '';
$faviconUrl = $siteFavicon ? SITE_URL . '/uploads/branding/' . rawurlencode($siteFavicon) : '';

echo renderAdminHeader('Site Settings');
?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-900">Site Settings</h1>
    <p class="text-sm text-gray-500 mt-1">Configure your site's identity, branding, and general settings.</p>
</div>

<?php if ($error): ?>
<div class="mb-5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-start gap-2">
    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <span><?= htmlspecialchars($error) ?></span>
</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="mb-5 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-start gap-2">
    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
    <span><?= htmlspecialchars($success) ?></span>
</div>
<?php endif; ?>

<div class="space-y-6">

    <!-- ═══════════ General Settings ═══════════ -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/80">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">General Configuration</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Core site identity used across all pages and themes.</p>
                </div>
            </div>
        </div>
        <form method="POST" class="p-6 space-y-5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
            <input type="hidden" name="action" value="save_general">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Site Title <span class="text-red-500">*</span></label>
                    <input type="text" name="site_title" value="<?= htmlspecialchars($siteTitle) ?>" required
                           class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" placeholder="My Collection Portal">
                    <p class="text-xs text-gray-400 mt-1.5">Displayed in the header, browser tab, and meta tags.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Site URL <span class="text-red-500">*</span></label>
                    <input type="url" name="site_url" value="<?= htmlspecialchars($siteUrl) ?>" required
                           class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono" placeholder="https://example.com/collection">
                    <p class="text-xs text-gray-400 mt-1.5">Without trailing slash. Used for all internal links.</p>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Site Description</label>
                <textarea name="site_description" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all resize-none" placeholder="A digital archive of historical artifacts..."><?= htmlspecialchars($siteDesc) ?></textarea>
                <p class="text-xs text-gray-400 mt-1.5">Used in meta descriptions and OpenGraph tags.</p>
            </div>
            
            <div class="pt-2">
                <label class="flex items-center gap-3 cursor-pointer group">
                    <div class="relative">
                        <input type="checkbox" name="debug_mode" value="1" <?= ($debugMode === '1') ? 'checked' : '' ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-900 group-hover:text-blue-600 transition-colors">Enable Debug Mode</span>
                        <p class="text-xs text-gray-500">When enabled, detailed PHP errors and warnings will be displayed on the frontend.</p>
                    </div>
                </label>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-5 py-2.5 text-sm font-semibold rounded-lg text-white bg-gray-900 hover:bg-gray-800 transition-all shadow-sm hover:shadow-md active:scale-[0.98]">Save Settings</button>
            </div>
        </form>
    </div>

    <!-- ═══════════ Logo & Favicon Row ═══════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Logo Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/80">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Site Logo</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Shown in header. 200×60px, max 2 MB.</p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="flex flex-col items-center gap-4">
                    <div class="w-full h-24 border-2 border-dashed border-gray-300 rounded-xl flex items-center justify-center bg-gray-50/50 overflow-hidden" id="logo-preview-box">
                        <?php if ($logoUrl): ?>
                            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="max-w-full max-h-full object-contain p-2">
                        <?php else: ?>
                            <div class="text-center text-gray-400"><svg class="mx-auto h-8 w-8 mb-1 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg><span class="text-xs font-medium block">No Logo</span></div>
                        <?php endif; ?>
                    </div>
                    <div class="w-full flex flex-col sm:flex-row items-start sm:items-center gap-3">
                        <form method="POST" enctype="multipart/form-data" class="flex items-center gap-2 flex-1">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                            <input type="hidden" name="action" value="upload_logo">
                            <label class="cursor-pointer inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-xs font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all">
                                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>Choose
                                <input type="file" name="logo_file" accept="image/*" class="hidden" onchange="previewBranding(this,'logo-preview-box','logo-fname'); this.form.submit();">
                            </label>
                            <span class="text-xs text-gray-400 truncate" id="logo-fname">PNG, JPG, SVG, WebP</span>
                        </form>
                        <?php if ($logoUrl): ?>
                        <form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>"><input type="hidden" name="action" value="remove_logo"><button type="submit" onclick="return confirm('Remove logo?')" class="text-xs text-red-500 hover:text-red-700 font-medium">Remove</button></form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Favicon Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/80">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Favicon</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Browser tab icon. 32×32 or 64×64px, max 1 MB.</p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="flex flex-col items-center gap-4">
                    <div class="w-24 h-24 border-2 border-dashed border-gray-300 rounded-xl flex items-center justify-center bg-gray-50/50 overflow-hidden mx-auto" id="favicon-preview-box">
                        <?php if ($faviconUrl): ?>
                            <img src="<?= htmlspecialchars($faviconUrl) ?>" alt="Favicon" class="max-w-full max-h-full object-contain p-2">
                        <?php else: ?>
                            <div class="text-center text-gray-400"><svg class="mx-auto h-8 w-8 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg><span class="text-xs font-medium block mt-1">No Favicon</span></div>
                        <?php endif; ?>
                    </div>
                    <div class="w-full flex flex-col sm:flex-row items-start sm:items-center gap-3">
                        <form method="POST" enctype="multipart/form-data" class="flex items-center gap-2 flex-1">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                            <input type="hidden" name="action" value="upload_favicon">
                            <label class="cursor-pointer inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-xs font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all">
                                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>Choose
                                <input type="file" name="favicon_file" accept="image/png,image/x-icon,image/svg+xml,image/gif,image/webp" class="hidden" onchange="previewBranding(this,'favicon-preview-box','fav-fname'); this.form.submit();">
                            </label>
                            <span class="text-xs text-gray-400 truncate" id="fav-fname">ICO, PNG, SVG</span>
                        </form>
                        <?php if ($faviconUrl): ?>
                        <form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>"><input type="hidden" name="action" value="remove_favicon"><button type="submit" onclick="return confirm('Remove favicon?')" class="text-xs text-red-500 hover:text-red-700 font-medium">Remove</button></form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /grid -->

</div>

<script>
function previewBranding(input, boxId, nameId) {
    const box = document.getElementById(boxId);
    const nameEl = document.getElementById(nameId);
    if (input.files && input.files[0]) {
        nameEl.textContent = input.files[0].name;
        const reader = new FileReader();
        reader.onload = function(e) {
            box.innerHTML = '<img src="' + e.target.result + '" alt="Preview" class="max-w-full max-h-full object-contain p-2">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?= renderAdminFooter(); ?>
