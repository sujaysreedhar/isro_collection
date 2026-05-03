<?php
$csrfToken = ensureCsrfToken();
$webhookKey = htmlspecialchars($this->getWebhookKey(), ENT_QUOTES, 'UTF-8');
$latest = $this->getLatestStatus();
$latestStatus = $latest['success'] ?? null;
$latestType = htmlspecialchars((string) ($latest['requested_type'] ?? 'Never run'), ENT_QUOTES, 'UTF-8');
$latestCompleted = htmlspecialchars((string) ($latest['completed_at'] ?? 'Not available'), ENT_QUOTES, 'UTF-8');
$latestMessage = htmlspecialchars((string) ($latest['message'] ?? ''), ENT_QUOTES, 'UTF-8');
$storageDir = htmlspecialchars($this->getStorageDir(), ENT_QUOTES, 'UTF-8');
$webhookSample = htmlspecialchars(SITE_URL . '/runback?type=files_db&key=' . rawurlencode($this->getWebhookKey()), ENT_QUOTES, 'UTF-8');
?>

<div class="space-y-6">
    <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-bold text-gray-900">Backup Manager</h1>
        <p class="text-sm text-gray-500">Create three-part backups for the database, uploads, and application files. The public webhook stores artifacts on the server and responds with plain text only.</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <section class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="font-semibold text-gray-900">Webhook Settings</h2>
                    <p class="text-sm text-gray-500 mt-1">Use the token-protected URL below to trigger backups remotely.</p>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label for="backup-webhook-key" class="block text-sm font-medium text-gray-700 mb-1">Webhook Key</label>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <input id="backup-webhook-key" type="text" value="<?= $webhookKey ?>" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            <button id="generate-key-btn" type="button" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Generate</button>
                            <button id="save-key-btn" type="button" class="px-4 py-2 rounded-lg bg-gray-900 text-sm font-medium text-white hover:bg-gray-800">Save Key</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Webhook URL</label>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono text-gray-700 break-all" id="webhook-preview"><?= $webhookSample ?></div>
                        <p class="text-xs text-gray-500 mt-2">Expected response: <code>OK</code> on success, <code>NOT</code> on failure.</p>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="font-semibold text-gray-900">Manual Backup Run</h2>
                    <p class="text-sm text-gray-500 mt-1">Run the same workflow from the admin area without exposing the artifacts publicly.</p>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label for="backup-type" class="block text-sm font-medium text-gray-700 mb-1">Backup Type</label>
                        <select id="backup-type" class="w-full sm:w-auto border border-gray-300 rounded-lg px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            <option value="files_db">files_db (database + uploads + app)</option>
                            <option value="files">files (uploads + app)</option>
                            <option value="db">db only</option>
                            <option value="media">media only</option>
                            <option value="app">app only</option>
                            <option value="media_db">media_db</option>
                            <option value="app_db">app_db</option>
                        </select>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                        <button id="run-backup-btn" type="button" class="px-5 py-2.5 rounded-lg bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700">Run Backup</button>
                        <span id="run-backup-status" class="text-sm text-gray-500">Ready.</span>
                    </div>
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <section class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="font-semibold text-gray-900">Latest Status</h2>
                </div>
                <div class="p-6 space-y-3">
                    <div class="flex items-center gap-2">
                        <?php if ($latestStatus === true): ?>
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">OK</span>
                        <?php elseif ($latestStatus === false): ?>
                            <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">NOT</span>
                        <?php else: ?>
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">No runs yet</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-400">Type</div>
                        <div class="text-sm text-gray-700"><?= $latestType ?></div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-400">Completed</div>
                        <div class="text-sm text-gray-700"><?= $latestCompleted ?></div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-400">Storage</div>
                        <div class="text-sm text-gray-700 break-all"><?= $storageDir ?></div>
                    </div>
                    <?php if ($latestMessage !== ''): ?>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-400">Message</div>
                        <div class="text-sm text-red-600"><?= $latestMessage ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="bg-white rounded-lg border border-amber-200 bg-amber-50 shadow-sm">
                <div class="p-6">
                    <h2 class="font-semibold text-amber-900">Behavior</h2>
                    <ul class="mt-3 space-y-2 text-sm text-amber-900/80">
                        <li>Public route: <code>/runback?type=files_db&amp;key=YOUR_KEY</code></li>
                        <li>Response body: only <code>OK</code> or <code>NOT</code></li>
                        <li>Artifacts stay on the server inside the protected backup directory</li>
                    </ul>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
(() => {
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const webhookInput = document.getElementById('backup-webhook-key');
    const webhookPreview = document.getElementById('webhook-preview');
    const saveKeyBtn = document.getElementById('save-key-btn');
    const generateKeyBtn = document.getElementById('generate-key-btn');
    const runBtn = document.getElementById('run-backup-btn');
    const runStatus = document.getElementById('run-backup-status');
    const backupType = document.getElementById('backup-type');

    const updatePreview = () => {
        const key = encodeURIComponent(webhookInput.value.trim());
        webhookPreview.textContent = <?= json_encode(SITE_URL . '/runback?type=files_db&key=') ?> + key;
    };

    const randomKey = () => {
        const bytes = new Uint8Array(24);
        window.crypto.getRandomValues(bytes);
        return Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('');
    };

    webhookInput.addEventListener('input', updatePreview);
    updatePreview();

    generateKeyBtn.addEventListener('click', () => {
        webhookInput.value = randomKey();
        updatePreview();
    });

    saveKeyBtn.addEventListener('click', async () => {
        saveKeyBtn.disabled = true;
        saveKeyBtn.textContent = 'Saving...';

        try {
            const formData = new FormData();
            formData.append('action', 'backup_manager_save_settings');
            formData.append('csrf_token', csrfToken);
            formData.append('backup_manager_webhook_key', webhookInput.value.trim());

            const response = await fetch(<?= json_encode(SITE_URL . '/admin/ajax.php') ?>, {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Unable to save settings.');
            }

            webhookInput.value = data.key || webhookInput.value.trim();
            updatePreview();
            saveKeyBtn.textContent = 'Saved';
            setTimeout(() => {
                saveKeyBtn.textContent = 'Save Key';
                saveKeyBtn.disabled = false;
            }, 900);
        } catch (error) {
            alert(error.message || 'Unable to save settings.');
            saveKeyBtn.textContent = 'Save Key';
            saveKeyBtn.disabled = false;
        }
    });

    runBtn.addEventListener('click', async () => {
        runBtn.disabled = true;
        runStatus.textContent = 'Running backup...';

        try {
            const formData = new FormData();
            formData.append('action', 'backup_manager_run');
            formData.append('csrf_token', csrfToken);
            formData.append('type', backupType.value);

            const response = await fetch(<?= json_encode(SITE_URL . '/admin/ajax.php') ?>, {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Backup failed.');
            }

            runStatus.textContent = 'Backup complete: ' + (data.parts || []).join(', ');
            window.location.reload();
        } catch (error) {
            runStatus.textContent = error.message || 'Backup failed.';
        } finally {
            runBtn.disabled = false;
        }
    });
})();
</script>
