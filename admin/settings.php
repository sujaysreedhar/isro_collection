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

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Storage Settings</h1>
        <p class="text-sm text-gray-500 mt-1">Configure where uploaded media files are stored.</p>
    </div>
    <a href="storage_migration.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all shadow-sm">
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4-4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
        Storage Migration Tool
    </a>
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

<!-- Tabs Navigation -->
<div class="mb-6 border-b border-gray-200">
    <nav class="-mb-px flex gap-6" aria-label="Tabs">
        <button type="button" onclick="switchTab('config')" id="nav-btn-config" class="tab-btn whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-gray-900 text-gray-900">Configuration</button>
        <?php if ($settings['storage_driver'] !== 's3'): ?>
        <button type="button" onclick="switchTab('orphans')" id="nav-btn-orphans" class="tab-btn whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Orphaned Media Cleanup</button>
        <?php endif; ?>
    </nav>
</div>

<div id="tab-config" class="tab-content">
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
</div>

<?php if ($settings['storage_driver'] !== 's3'): ?>
<div id="tab-orphans" class="tab-content hidden space-y-6">
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <div>
                <h3 class="font-semibold text-gray-800">Orphaned Media Cleanup</h3>
                <p class="text-sm text-gray-500 mt-1">Scan the local <code>uploads/</code> directory for physical files not linked to any database records.</p>
            </div>
            <button type="button" id="scan-orphans-btn" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition flex items-center shadow-sm">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                Scan Now
            </button>
        </div>
        <div class="p-6">
            <div id="scan-loading" class="hidden text-center py-10">
                <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-gray-500 text-sm font-medium">Scanning filesystem and mapping database records...</p>
            </div>
            
            <div id="scan-results" class="hidden">
                <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-100">
                    <h4 class="font-bold text-gray-800"><span id="orphan-count">0</span> orphaned file(s) found</h4>
                    <button type="button" id="delete-selected-btn" class="px-3 py-1.5 bg-red-100 text-red-700 text-sm font-medium rounded hover:bg-red-200 transition disabled:opacity-50 disabled:cursor-not-allowed">Delete Selected</button>
                </div>
                <!-- Data Table -->
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left w-10">
                                    <input type="checkbox" id="select-all-orphans" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">File Path</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wider text-xs">Size</th>
                            </tr>
                        </thead>
                        <tbody id="orphan-list" class="bg-white divide-y divide-gray-200">
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div id="scan-empty" class="hidden text-center py-10">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 mb-4">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <p class="text-gray-600 font-medium">No orphaned files found.</p>
                <p class="text-gray-400 text-sm mt-1">Your uploads directory is clean.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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

function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('border-gray-900', 'text-gray-900');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    
    document.getElementById('tab-' + tabId).classList.remove('hidden');
    const activeBtn = document.getElementById('nav-btn-' + tabId);
    activeBtn.classList.remove('border-transparent', 'text-gray-500');
    activeBtn.classList.add('border-gray-900', 'text-gray-900');
}

// Scanner logic
const scanBtn = document.getElementById('scan-orphans-btn');
if (scanBtn) {
    scanBtn.addEventListener('click', async () => {
        document.getElementById('scan-empty').classList.add('hidden');
        document.getElementById('scan-results').classList.add('hidden');
        document.getElementById('scan-loading').classList.remove('hidden');
        scanBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'scan_orphans');
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            const res = await fetch('ajax.php', { method: 'POST', body: formData });
            const data = await res.json();
            
            document.getElementById('scan-loading').classList.add('hidden');
            scanBtn.disabled = false;

            if (data.success) {
                if (data.orphans.length === 0) {
                    document.getElementById('scan-empty').classList.remove('hidden');
                } else {
                    document.getElementById('orphan-count').innerText = data.orphans.length;
                    const tbody = document.getElementById('orphan-list');
                    tbody.innerHTML = '';
                    
                    data.orphans.forEach(file => {
                        const tr = document.createElement('tr');
                        tr.className = 'hover:bg-gray-50';
                        tr.innerHTML = `
                            <td class="px-4 py-3">
                                <input type="checkbox" class="orphan-cb rounded border-gray-300 text-blue-600 focus:ring-blue-500" value="${file.path}">
                            </td>
                            <td class="px-4 py-3 text-gray-800 font-mono text-xs break-all">${file.path}</td>
                            <td class="px-4 py-3 text-right text-gray-500 text-xs">${file.size_formatted}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                    
                    document.getElementById('scan-results').classList.remove('hidden');
                    updateDeleteBtn();
                }
            } else {
                alert('Scan failed: ' + (data.message || 'Unknown error'));
            }
        } catch (e) {
            alert('Error connecting to server.');
            document.getElementById('scan-loading').classList.add('hidden');
            scanBtn.disabled = false;
        }
    });

    // Checkbox logic
    document.getElementById('select-all-orphans').addEventListener('change', (e) => {
        document.querySelectorAll('.orphan-cb').forEach(cb => cb.checked = e.target.checked);
        updateDeleteBtn();
    });

    document.getElementById('orphan-list').addEventListener('change', (e) => {
        if(e.target.classList.contains('orphan-cb')) updateDeleteBtn();
    });

    function updateDeleteBtn() {
        const checked = document.querySelectorAll('.orphan-cb:checked').length;
        const btn = document.getElementById('delete-selected-btn');
        btn.disabled = checked === 0;
        btn.innerText = `Delete Selected (${checked})`;
    }

    document.getElementById('delete-selected-btn').addEventListener('click', async () => {
        const checked = Array.from(document.querySelectorAll('.orphan-cb:checked')).map(cb => cb.value);
        if (!checked.length) return;
        
        if (!confirm(`Are you sure you want to permanently delete ${checked.length} file(s)? This cannot be undone.`)) return;

        const btn = document.getElementById('delete-selected-btn');
        btn.disabled = true;
        btn.innerText = 'Deleting...';

        try {
            const formData = new FormData();
            formData.append('action', 'delete_orphans');
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            checked.forEach(path => formData.append('paths[]', path));

            const res = await fetch('ajax.php', { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                // Re-scan
                scanBtn.click();
            } else {
                alert('Deletion failed: ' + (data.message || 'Unknown error'));
                updateDeleteBtn();
            }
        } catch (e) {
            alert('Error connecting to server.');
            updateDeleteBtn();
        }
    });
}
</script>

<?= renderAdminFooter(); ?>
