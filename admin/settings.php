<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

$error   = '';
$success = '';

// Load current settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Defaults
$defaults = [
    'storage_driver' => 'local',
    's3_bucket'      => '',
    's3_region'      => 'us-east-1',
    's3_access_key'  => '',
    's3_secret_key'  => '',
    's3_endpoint'    => '',
    's3_path_prefix' => 'collection/uploads',
];
$settings = array_merge($defaults, $settings);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? 'save';

    if ($action === 'test_s3') {
        // Test S3 connection
        require_once __DIR__ . '/../S3Storage.php';
        $testConfig = [
            'bucket'      => trim($_POST['s3_bucket'] ?? ''),
            'region'      => trim($_POST['s3_region'] ?? 'us-east-1'),
            'access_key'  => trim($_POST['s3_access_key'] ?? ''),
            'secret_key'  => trim($_POST['s3_secret_key'] ?? ''),
            'endpoint'    => trim($_POST['s3_endpoint'] ?? ''),
            'path_prefix' => trim($_POST['s3_path_prefix'] ?? 'collection/uploads'),
        ];

        if (empty($testConfig['bucket']) || empty($testConfig['access_key']) || empty($testConfig['secret_key'])) {
            $error = 'Bucket, Access Key, and Secret Key are required to test the connection.';
        } else {
            $testStorage = new S3Storage($testConfig);
            $result = $testStorage->testConnection();
            if ($result === true) {
                $success = '✅ S3 connection successful! Bucket "' . htmlspecialchars($testConfig['bucket']) . '" is accessible.';
            } else {
                $error = $result;
            }
        }

        // Keep submitted values in form
        $settings = array_merge($settings, [
            's3_bucket'      => $testConfig['bucket'],
            's3_region'      => $testConfig['region'],
            's3_access_key'  => $testConfig['access_key'],
            's3_secret_key'  => $testConfig['secret_key'],
            's3_endpoint'    => $testConfig['endpoint'],
            's3_path_prefix' => $testConfig['path_prefix'],
        ]);

    } else {
        // Save settings
        $saveable = [
            'storage_driver' => in_array($_POST['storage_driver'] ?? '', ['local', 's3']) ? $_POST['storage_driver'] : 'local',
            's3_bucket'      => trim($_POST['s3_bucket'] ?? ''),
            's3_region'      => trim($_POST['s3_region'] ?? 'us-east-1'),
            's3_access_key'  => trim($_POST['s3_access_key'] ?? ''),
            's3_secret_key'  => trim($_POST['s3_secret_key'] ?? ''),
            's3_endpoint'    => trim($_POST['s3_endpoint'] ?? ''),
            's3_path_prefix' => trim($_POST['s3_path_prefix'] ?? 'collection/uploads'),
        ];

        // Validate if switching to S3
        if ($saveable['storage_driver'] === 's3') {
            if (empty($saveable['s3_bucket']) || empty($saveable['s3_access_key']) || empty($saveable['s3_secret_key'])) {
                $error = 'When using S3, Bucket, Access Key, and Secret Key are required.';
            }
        }

        if (!$error) {
            try {
                $upsert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2");
                foreach ($saveable as $key => $val) {
                    $upsert->execute([':k' => $key, ':v' => $val, ':v2' => $val]);
                }
                $success = 'Settings saved successfully.';
                $settings = array_merge($settings, $saveable);
            } catch (\PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        } else {
            $settings = array_merge($settings, $saveable);
        }
    }
}

echo renderAdminHeader('Storage Settings');
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Storage Settings</h1>
    <p class="text-sm text-gray-500 mt-1">Configure where uploaded media files are stored.</p>
</div>

<?php if ($error): ?>
<div class="mb-5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded flex items-start gap-2">
    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <span><?= htmlspecialchars($error) ?></span>
</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="mb-5 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded flex items-start gap-2">
    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
    <span><?= $success ?></span>
</div>
<?php endif; ?>

<form method="POST" class="space-y-6" id="settings-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
    <input type="hidden" name="action" value="save" id="form-action">

    <!-- Storage Driver Selection -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200 bg-gray-50">
            <h3 class="font-semibold text-gray-800">Storage Driver</h3>
            <p class="text-sm text-gray-500 mt-1">Choose where uploaded images and PDFs are stored.</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Local Option -->
                <label class="relative flex items-start p-4 border rounded-lg cursor-pointer transition-all
                    <?= $settings['storage_driver'] === 'local' ? 'border-gray-900 bg-gray-50 ring-1 ring-gray-900' : 'border-gray-200 hover:border-gray-400' ?>">
                    <input type="radio" name="storage_driver" value="local"
                           <?= $settings['storage_driver'] === 'local' ? 'checked' : '' ?>
                           onchange="toggleS3Fields()" class="mt-1 h-4 w-4 text-gray-900 border-gray-300 focus:ring-gray-900">
                    <div class="ml-3">
                        <span class="block text-sm font-semibold text-gray-900">Local Disk</span>
                        <span class="block text-xs text-gray-500 mt-1">Files stored in <code class="bg-gray-100 px-1 rounded">uploads/</code> directory on this server.</span>
                    </div>
                </label>
                <!-- S3 Option -->
                <label class="relative flex items-start p-4 border rounded-lg cursor-pointer transition-all
                    <?= $settings['storage_driver'] === 's3' ? 'border-gray-900 bg-gray-50 ring-1 ring-gray-900' : 'border-gray-200 hover:border-gray-400' ?>">
                    <input type="radio" name="storage_driver" value="s3"
                           <?= $settings['storage_driver'] === 's3' ? 'checked' : '' ?>
                           onchange="toggleS3Fields()" class="mt-1 h-4 w-4 text-gray-900 border-gray-300 focus:ring-gray-900">
                    <div class="ml-3">
                        <span class="block text-sm font-semibold text-gray-900">Amazon S3</span>
                        <span class="block text-xs text-gray-500 mt-1">Files stored in an S3 bucket (or compatible service like MinIO, DigitalOcean Spaces).</span>
                    </div>
                </label>
            </div>
        </div>
    </div>

    <!-- S3 Configuration -->
    <div id="s3-config" class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden <?= $settings['storage_driver'] !== 's3' ? 'hidden' : '' ?>">
        <div class="p-6 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-800">S3 Configuration</h3>
                <p class="text-sm text-gray-500 mt-1">Credentials and bucket details for AWS S3 or compatible storage.</p>
            </div>
            <button type="button" onclick="testS3Connection()" id="test-btn"
                    class="px-4 py-2 text-sm font-medium rounded-md border border-blue-300 text-blue-700 bg-blue-50 hover:bg-blue-100 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                Test Connection
            </button>
        </div>
        <div class="p-6 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="label">Bucket Name <span class="text-red-500">*</span></label>
                    <input type="text" name="s3_bucket" value="<?= htmlspecialchars($settings['s3_bucket']) ?>"
                           class="input" placeholder="my-collection-bucket">
                </div>
                <div>
                    <label class="label">Region</label>
                    <select name="s3_region" class="input">
                        <?php
                        $regions = [
                            'us-east-1' => 'US East (N. Virginia)',
                            'us-east-2' => 'US East (Ohio)',
                            'us-west-1' => 'US West (N. California)',
                            'us-west-2' => 'US West (Oregon)',
                            'eu-west-1' => 'EU (Ireland)',
                            'eu-west-2' => 'EU (London)',
                            'eu-central-1' => 'EU (Frankfurt)',
                            'ap-south-1' => 'Asia Pacific (Mumbai)',
                            'ap-southeast-1' => 'Asia Pacific (Singapore)',
                            'ap-southeast-2' => 'Asia Pacific (Sydney)',
                            'ap-northeast-1' => 'Asia Pacific (Tokyo)',
                            'sa-east-1' => 'South America (São Paulo)',
                            'me-south-1' => 'Middle East (Bahrain)',
                            'af-south-1' => 'Africa (Cape Town)',
                        ];
                        foreach ($regions as $code => $label):
                        ?>
                            <option value="<?= $code ?>" <?= $settings['s3_region'] === $code ? 'selected' : '' ?>><?= $label ?> (<?= $code ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="label">Access Key ID <span class="text-red-500">*</span></label>
                    <input type="text" name="s3_access_key" value="<?= htmlspecialchars($settings['s3_access_key']) ?>"
                           class="input font-mono" placeholder="AKIAIOSFODNN7EXAMPLE" autocomplete="off">
                </div>
                <div>
                    <label class="label">Secret Access Key <span class="text-red-500">*</span></label>
                    <input type="password" name="s3_secret_key" value="<?= htmlspecialchars($settings['s3_secret_key']) ?>"
                           class="input font-mono" placeholder="••••••••••••••••" autocomplete="off">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="label">Custom Endpoint <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="url" name="s3_endpoint" value="<?= htmlspecialchars($settings['s3_endpoint']) ?>"
                           class="input" placeholder="https://s3.example.com">
                    <p class="text-xs text-gray-400 mt-1">For S3-compatible services (MinIO, DigitalOcean Spaces, etc.)</p>
                </div>
                <div>
                    <label class="label">Path Prefix <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" name="s3_path_prefix" value="<?= htmlspecialchars($settings['s3_path_prefix']) ?>"
                           class="input" placeholder="collection/uploads">
                    <p class="text-xs text-gray-400 mt-1">Prefix for all S3 object keys within the bucket.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Status -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
        <h3 class="font-semibold text-gray-800 mb-3">Current Status</h3>
        <div class="flex items-center gap-3">
            <?php if ($settings['storage_driver'] === 's3' && !empty($settings['s3_bucket'])): ?>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                    ☁️ S3: <?= htmlspecialchars($settings['s3_bucket']) ?> (<?= htmlspecialchars($settings['s3_region']) ?>)
                </span>
            <?php else: ?>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                    💾 Local Disk
                </span>
            <?php endif; ?>
            <span class="text-xs text-gray-400">Changes take effect immediately on save.</span>
        </div>
    </div>

    <!-- Save Button -->
    <div class="flex justify-end gap-3">
        <a href="index.php" class="px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
        <button type="submit" class="px-5 py-2 text-sm font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800">
            Save Settings
        </button>
    </div>
</form>

<!-- Reusable input styles -->
<style>
.label { display: block; font-size: .875rem; font-weight: 500; color: #374151; margin-bottom: .25rem; }
.input { width: 100%; border: 1px solid #d1d5db; border-radius: .375rem; padding: .5rem .75rem; font-size: .875rem;
         outline: none; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
.input:focus { border-color: #111827; box-shadow: 0 0 0 1px #111827; }
</style>

<script>
function toggleS3Fields() {
    const driver = document.querySelector('input[name="storage_driver"]:checked').value;
    const s3Block = document.getElementById('s3-config');
    s3Block.classList.toggle('hidden', driver !== 's3');

    // Update radio card styling
    document.querySelectorAll('input[name="storage_driver"]').forEach(radio => {
        const label = radio.closest('label');
        if (radio.checked) {
            label.classList.add('border-gray-900', 'bg-gray-50', 'ring-1', 'ring-gray-900');
            label.classList.remove('border-gray-200');
        } else {
            label.classList.remove('border-gray-900', 'bg-gray-50', 'ring-1', 'ring-gray-900');
            label.classList.add('border-gray-200');
        }
    });
}

function testS3Connection() {
    document.getElementById('form-action').value = 'test_s3';
    document.getElementById('settings-form').submit();
}
</script>

<?= renderAdminFooter(); ?>
