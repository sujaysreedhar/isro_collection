<?php
$csrfToken = ensureCsrfToken();
$service = $this->getService();
$history = $service->getBackupHistory();

$enabled = ($GLOBALS['appSettings']['auto_backup_enabled'] ?? '1') === '1';
$lastDbRun = (int)($GLOBALS['appSettings']['auto_backup_last_db_run'] ?? 0);
$lastMediaRun = (int)($GLOBALS['appSettings']['auto_backup_last_media_run'] ?? 0);
$lastFailureAt = (int)($GLOBALS['appSettings']['auto_backup_last_failure_at'] ?? 0);

$lastDbRunStr = $lastDbRun > 0 ? date('Y-m-d H:i:s', $lastDbRun) : 'Never';
$lastMediaRunStr = $lastMediaRun > 0 ? date('Y-m-d H:i:s', $lastMediaRun) : 'Never';
$backupStale = $enabled && (($lastDbRun === 0 || time() - $lastDbRun > 86400) || ($lastMediaRun === 0 || time() - $lastMediaRun > 604800));

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

<div class="space-y-8 animate-fadeIn">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-100 pb-5">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Automatic Backups</h1>
            <p class="text-sm text-slate-500 mt-1">Configure automated maintenance intervals. Schedule the CLI runner hourly to execute due backups.</p>
            <?php if ($backupStale): ?><p class="text-sm text-amber-700 mt-1">A backup is due. Check the scheduled runner and its logs.</p><?php endif; ?>
            <?php if ($lastFailureAt > max($lastDbRun, $lastMediaRun)): ?><p class="text-sm text-red-700 mt-1">The last backup attempt failed at <?= htmlspecialchars(date('Y-m-d H:i:s', $lastFailureAt)) ?>. Check the protected runner log.</p><?php endif; ?>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm font-semibold text-slate-600">Auto Backups Status:</span>
            <button id="status-toggle-btn" type="button" 
                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none <?= $enabled ? 'bg-indigo-600' : 'bg-slate-200' ?>" 
                    role="switch" aria-checked="<?= $enabled ? 'true' : 'false' ?>">
                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out <?= $enabled ? 'translate-x-5' : 'translate-x-0' ?>"></span>
            </button>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-6 text-white shadow-lg shadow-indigo-100 hover:shadow-xl hover:scale-[1.01] transition-all duration-300">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-indigo-100">Database Interval</span>
                <span class="rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-semibold text-white">Daily</span>
            </div>
            <div class="mt-4">
                <div class="text-xs text-indigo-100 font-mono">Last Run Time</div>
                <div class="text-lg font-bold mt-1"><?= $lastDbRunStr ?></div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-6 text-white shadow-lg shadow-emerald-100 hover:shadow-xl hover:scale-[1.01] transition-all duration-300">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-emerald-100">Uploaded Files Interval</span>
                <span class="rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-semibold text-white">7 Days</span>
            </div>
            <div class="mt-4">
                <div class="text-xs text-emerald-100 font-mono">Last Run Time</div>
                <div class="text-lg font-bold mt-1"><?= $lastMediaRunStr ?></div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl p-6 text-white shadow-lg shadow-blue-100 hover:shadow-xl hover:scale-[1.01] transition-all duration-300">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-blue-100">Retention Strategy</span>
                <span class="rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-semibold text-white">Active</span>
            </div>
            <div class="mt-4">
                <div class="text-xs text-blue-100 font-mono">Stored Snapshots</div>
                <div class="text-lg font-bold mt-1"><?= count($history) ?> / 5 Updates</div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- History Section -->
        <div class="lg:col-span-2 space-y-6">
            <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-slate-800">Stored Updates History</h2>
                        <p class="text-xs text-slate-500 mt-1">Older backups are automatically pruned to retain the 5 most recent snapshots.</p>
                    </div>
                </div>
                
                <div class="divide-y divide-slate-100">
                    <?php if (empty($history)): ?>
                        <div class="p-8 text-center text-slate-400">
                            <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                            <p class="mt-3 text-sm font-medium">No automatic backups have been created yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($history as $backup): ?>
                            <div class="p-6 hover:bg-slate-50/30 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-mono font-bold text-slate-700"><?= htmlspecialchars($backup['run_id']) ?></span>
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium <?= $backup['trigger'] === 'auto' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200/50' : 'bg-slate-100 text-slate-700 border border-slate-200' ?>">
                                            <?= ucfirst(htmlspecialchars($backup['trigger'])) ?>
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        Completed: <?= date('Y-m-d H:i:s', strtotime($backup['completed_at'])) ?>
                                    </div>
                                    <div class="flex flex-wrap gap-2 mt-2">
                                        <?php foreach ($backup['artifacts'] as $key => $art): ?>
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600 border border-slate-200">
                                                <svg class="h-3 w-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <?= htmlspecialchars($art['filename']) ?> (<?= formatBytes($art['bytes']) ?>)
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="flex items-center sm:self-center">
                                    <button type="button" 
                                            class="delete-backup-btn p-2 rounded-xl text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition-all duration-200"
                                            data-folder="<?= htmlspecialchars($backup['folder_name']) ?>"
                                            title="Delete backup">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- Sidebar Actions Section -->
        <div class="space-y-6">
            <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50">
                    <h2 class="font-bold text-slate-800">Manual Operations</h2>
                    <p class="text-xs text-slate-500 mt-1">Manually run a backup snapshot. The retention logic will execute immediately.</p>
                </div>
                <div class="p-6 space-y-4">
                    <button id="run-db-btn" type="button" 
                            class="w-full inline-flex justify-center items-center gap-2 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-700 shadow-md shadow-indigo-100 hover:shadow-lg transition-all duration-200">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                        </svg>
                        Run Database Backup Only
                    </button>

                    <button id="run-full-btn" type="button" 
                            class="w-full inline-flex justify-center items-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 shadow-md shadow-slate-100 hover:shadow-lg transition-all duration-200">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                        </svg>
                        Run Database + Media Backup
                    </button>
                    
                    <div id="operation-status" class="hidden text-sm p-3.5 rounded-xl text-center font-medium"></div>
                </div>
            </section>

            <section class="bg-indigo-50 border border-indigo-100 rounded-2xl p-6">
                <h3 class="font-bold text-indigo-900 flex items-center gap-2">
                    <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Automatic Intervals
                </h3>
                <ul class="mt-3 space-y-2 text-xs text-indigo-950/80 leading-relaxed list-disc list-inside">
                    <li><strong>Database backup:</strong> Runs automatically once every 24 hours.</li>
                    <li><strong>Uploads backup:</strong> Runs automatically once every 7 days (excludes `thumbs/` cache folder).</li>
                    <li><strong>Pruning:</strong> Keeps only the last 5 updates. Older folders are completely deleted.</li>
                    <li><strong>Scheduler required:</strong> Run <code>scripts/run_auto_backup.php</code> hourly from Task Scheduler or cron.</li>
                </ul>
            </section>
        </div>
    </div>
</div>

<script>
(() => {
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const toggleBtn = document.getElementById('status-toggle-btn');
    const runDbBtn = document.getElementById('run-db-btn');
    const runFullBtn = document.getElementById('run-full-btn');
    const statusDiv = document.getElementById('operation-status');

    // Toggle logic
    toggleBtn.addEventListener('click', async () => {
        const isChecked = toggleBtn.getAttribute('aria-checked') === 'true';
        const nextState = !isChecked;
        toggleBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'auto_backup_save_settings');
            formData.append('csrf_token', csrfToken);
            formData.append('auto_backup_enabled', nextState ? '1' : '0');

            const response = await fetch(<?= json_encode(SITE_URL . '/admin/ajax.php') ?>, {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Failed to update settings.');
            }

            toggleBtn.setAttribute('aria-checked', nextState ? 'true' : 'false');
            if (nextState) {
                toggleBtn.classList.remove('bg-slate-200');
                toggleBtn.classList.add('bg-indigo-600');
                toggleBtn.firstElementChild.classList.remove('translate-x-0');
                toggleBtn.firstElementChild.classList.add('translate-x-5');
            } else {
                toggleBtn.classList.remove('bg-indigo-600');
                toggleBtn.classList.add('bg-slate-200');
                toggleBtn.firstElementChild.classList.remove('translate-x-5');
                toggleBtn.firstElementChild.classList.add('translate-x-0');
            }
        } catch (err) {
            alert(err.message || 'Error updating settings');
        } finally {
            toggleBtn.disabled = false;
        }
    });

    const runBackup = async (type) => {
        runDbBtn.disabled = true;
        runFullBtn.disabled = true;
        statusDiv.className = 'text-sm p-3.5 rounded-xl text-center font-medium bg-slate-100 text-slate-700';
        statusDiv.textContent = 'Running backup snapshot...';
        statusDiv.classList.remove('hidden');

        try {
            const formData = new FormData();
            formData.append('action', 'auto_backup_run');
            formData.append('csrf_token', csrfToken);
            formData.append('type', type);

            const response = await fetch(<?= json_encode(SITE_URL . '/admin/ajax.php') ?>, {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Backup execution failed.');
            }

            statusDiv.className = 'text-sm p-3.5 rounded-xl text-center font-medium bg-emerald-50 text-emerald-800 border border-emerald-100';
            statusDiv.textContent = 'Success! Backup complete.';
            setTimeout(() => {
                window.location.reload();
            }, 800);
        } catch (err) {
            statusDiv.className = 'text-sm p-3.5 rounded-xl text-center font-medium bg-rose-50 text-rose-800 border border-rose-100';
            statusDiv.textContent = err.message || 'Backup failed.';
            runDbBtn.disabled = false;
            runFullBtn.disabled = false;
        }
    };

    runDbBtn.addEventListener('click', () => runBackup('db'));
    runFullBtn.addEventListener('click', () => runBackup('db_media'));

    // Delete logic
    document.querySelectorAll('.delete-backup-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const folder = btn.getAttribute('data-folder');
            if (!confirm('Are you sure you want to delete this backup folder permanently?')) {
                return;
            }

            btn.disabled = true;
            try {
                const formData = new FormData();
                formData.append('action', 'auto_backup_delete');
                formData.append('csrf_token', csrfToken);
                formData.append('folder_name', folder);

                const response = await fetch(<?= json_encode(SITE_URL . '/admin/ajax.php') ?>, {
                    method: 'POST',
                    body: formData,
                });

                const data = await response.json();
                if (!data.success) {
                    throw new Error('Unable to delete backup.');
                }

                window.location.reload();
            } catch (err) {
                alert(err.message || 'Error deleting backup');
                btn.disabled = false;
            }
        });
    });
})();
</script>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
    animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
