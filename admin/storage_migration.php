<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../LocalStorage.php';
require_once __DIR__ . '/../S3Storage.php';

$error = '';
$success = '';
$log = [];

// Load settings to configure S3 and Local instances
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$localBase = realpath(__DIR__ . '/../uploads');
if (!$localBase) {
    @mkdir(__DIR__ . '/../uploads', 0755, true);
    $localBase = realpath(__DIR__ . '/../uploads');
}
$localStorage = new LocalStorage($localBase, SITE_URL . '/uploads');

$s3Configured = !empty($settings['s3_bucket']) && !empty($settings['s3_access_key']) && !empty($settings['s3_secret_key']);
$s3Storage = null;
if ($s3Configured) {
    $s3Storage = new S3Storage([
        'bucket'      => $settings['s3_bucket'],
        'region'      => $settings['s3_region'] ?? 'us-east-1',
        'access_key'  => $settings['s3_access_key'],
        'secret_key'  => $settings['s3_secret_key'],
        'endpoint'    => $settings['s3_endpoint'] ?? '',
        'path_prefix' => $settings['s3_path_prefix'] ?? 'collection/uploads',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }

    $direction = $_POST['direction'] ?? ''; // 'local_to_s3' or 's3_to_local'

    if ($direction === 'local_to_s3' && !$s3Configured) {
        $error = "S3 must be fully configured to migrate to it.";
    } elseif ($direction === 'local_to_s3') {
        // Local -> S3
        $stmt = $pdo->query("SELECT id, file_path, media_type FROM media WHERE media_type IN ('image', 'pdf')");
        $count = 0;
        while ($media = $stmt->fetch()) {
            $f = $media['file_path'];
            if (!$f) continue;
            
            if ($media['media_type'] === 'pdf') {
                $localFilePath = $localBase . '/pdfs/' . $f;
                if (file_exists($localFilePath)) {
                    $s3Storage->put('pdfs/' . $f, $localFilePath, 'application/pdf');
                    $count++;
                    $log[] = "Migrated PDF: $f";
                }
            } else {
                // Images: thumbs, display, and original
                foreach (['thumbs', 'display'] as $dir) {
                    $localFilePath = $localBase . '/' . $dir . '/' . $f;
                    if (file_exists($localFilePath)) {
                        $s3Storage->put($dir . '/' . $f, $localFilePath, 'image/webp');
                        $count++;
                    }
                }
                // Original image might be jpg, png, etc.
                $base = pathinfo($f, PATHINFO_FILENAME);
                foreach (['jpg','png','gif','webp'] as $ext) {
                    $origPath = $localBase . '/original/' . $base . '.' . $ext;
                    if (file_exists($origPath)) {
                        $mime = 'image/jpeg';
                        if ($ext === 'png') $mime = 'image/png';
                        if ($ext === 'gif') $mime = 'image/gif';
                        if ($ext === 'webp') $mime = 'image/webp';
                        $s3Storage->put('original/' . $base . '.' . $ext, $origPath, $mime);
                        $count++;
                    }
                }
                $log[] = "Migrated Image Variants: $f";
            }
        }
        $success = "Successfully migrated $count files from Local Storage to S3.";

    } elseif ($direction === 's3_to_local' && !$s3Configured) {
         $error = "S3 must be configured to migrate from it.";
    } elseif ($direction === 's3_to_local') {
        // S3 -> Local
        $stmt = $pdo->query("SELECT id, file_path, media_type FROM media WHERE media_type IN ('image', 'pdf')");
        $count = 0;
        while ($media = $stmt->fetch()) {
            $f = $media['file_path'];
            if (!$f) continue;
            
            if ($media['media_type'] === 'pdf') {
                if ($s3Storage->exists('pdfs/' . $f)) {
                    // Download to temp file, then put to local
                    $s3Url = $s3Storage->url('pdfs/' . $f);
                    $tmp = tempnam(sys_get_temp_dir(), 's3dl_');
                    file_put_contents($tmp, file_get_contents($s3Url));
                    $localStorage->put('pdfs/' . $f, $tmp);
                    @unlink($tmp);
                    $count++;
                    $log[] = "Migrated PDF: $f";
                }
            } else {
                foreach (['thumbs', 'display'] as $dir) {
                    if ($s3Storage->exists($dir . '/' . $f)) {
                        $s3Url = $s3Storage->url($dir . '/' . $f);
                        $tmp = tempnam(sys_get_temp_dir(), 's3dl_');
                        file_put_contents($tmp, file_get_contents($s3Url));
                        $localStorage->put($dir . '/' . $f, $tmp);
                        @unlink($tmp);
                        $count++;
                    }
                }
                $base = pathinfo($f, PATHINFO_FILENAME);
                foreach (['jpg','png','gif','webp'] as $ext) {
                    if ($s3Storage->exists('original/' . $base . '.' . $ext)) {
                        $s3Url = $s3Storage->url('original/' . $base . '.' . $ext);
                        $tmp = tempnam(sys_get_temp_dir(), 's3dl_');
                        file_put_contents($tmp, file_get_contents($s3Url));
                        $localStorage->put('original/' . $base . '.' . $ext, $tmp);
                        @unlink($tmp);
                        $count++;
                    }
                }
                $log[] = "Migrated Image Variants: $f";
            }
        }
        $success = "Successfully migrated $count files from S3 to Local Storage.";
    }
}

echo renderAdminHeader('Storage Migration');
?>

<div class="mb-6 border-b border-gray-200 pb-4">
    <h1 class="text-2xl font-bold text-gray-900">Storage Migration Tool</h1>
    <p class="text-sm text-gray-500 mt-1">Move your existing media files between Local Storage and Amazon S3.</p>
</div>

<?php if ($error): ?>
<div class="mb-5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="mb-5 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
    <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <!-- Local to S3 -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 flex flex-col">
        <h3 class="font-bold text-lg mb-2">Local ➡️ S3</h3>
        <p class="text-sm text-gray-600 mb-6 flex-grow">
            Copies all media files (images and PDFs) from the local <code>uploads/</code> directory into your configured S3 bucket.
            This is useful if you are moving from a single-server setup to cloud storage.
        </p>
        <form method="POST" onsubmit="return confirm('Depending on the size of your collection, this may take a while. Continue?');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
            <input type="hidden" name="direction" value="local_to_s3">
            <button type="submit" class="w-full justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 <?= !$s3Configured ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= !$s3Configured ? 'disabled' : '' ?>>
                Migrate to S3
            </button>
            <?php if (!$s3Configured): ?>
                <p class="text-xs text-red-500 mt-2 text-center">S3 is not configured in Settings.</p>
            <?php endif; ?>
        </form>
    </div>

    <!-- S3 to Local -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 flex flex-col">
        <h3 class="font-bold text-lg mb-2">S3 ➡️ Local</h3>
        <p class="text-sm text-gray-600 mb-6 flex-grow">
            Downloads all media files from your configured S3 bucket and saves them to the local <code>uploads/</code> directory.
            Useful if you are moving away from S3 back to local hosting.
        </p>
        <form method="POST" onsubmit="return confirm('Depending on the size of your collection, this may take a while. Continue?');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
            <input type="hidden" name="direction" value="s3_to_local">
            <button type="submit" class="w-full justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white text-gray-700 bg-gray-100 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 <?= !$s3Configured ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= !$s3Configured ? 'disabled' : '' ?>>
                Migrate to Local
            </button>
            <?php if (!$s3Configured): ?>
                <p class="text-xs text-red-500 mt-2 text-center">S3 is not configured in Settings.</p>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!empty($log)): ?>
<div class="bg-gray-900 text-gray-300 p-4 rounded-lg overflow-y-auto h-64 font-mono text-xs">
    <h4 class="text-white mb-2 font-bold uppercase tracking-wider">Migration Log</h4>
    <?php foreach ($log as $l): ?>
        <div class="mb-1">&gt; <?= htmlspecialchars($l) ?></div>
    <?php endforeach; ?>
    <div class="text-green-400 mt-2">&gt; Done. Remember to change your Storage Driver in Settings if you want to switch backends.</div>
</div>
<?php endif; ?>

<?= renderAdminFooter() ?>
