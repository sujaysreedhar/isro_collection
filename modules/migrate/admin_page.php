<?php
/** @var MigrateService $service */
/** @var array|null $result */

$overview = $service->overview();
$csrf = ensureCsrfToken();

function migrate_format_count(int $value): string
{
    return number_format($value);
}
?>

<div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div>
        <p class="text-xs font-bold uppercase tracking-[0.22em] text-blue-600">Migration</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900">Move site content safely</h1>
        <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-600">
            Export and import catalog entries, collections, content metadata, and media files. User accounts, API keys, subscribers,
            comments, trade requests, galleries, and settings are excluded.
        </p>
    </div>
</div>

<?php if ($result): ?>
    <?php $success = !empty($result['success']); ?>
    <div class="mb-6 rounded-xl border <?= $success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-5 py-4">
        <p class="font-bold"><?= htmlspecialchars($success ? ($result['message'] ?? 'Migration completed.') : 'Migration failed') ?></p>
        <?php if (!$success && !empty($result['message'])): ?>
            <p class="mt-1 text-sm"><?= htmlspecialchars($result['message']) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                <div class="rounded-lg bg-white/70 px-3 py-2">
                    <span class="block text-xs font-bold uppercase text-slate-500">Rows imported</span>
                    <span class="text-lg font-black"><?= migrate_format_count(array_sum($result['imported_rows'] ?? [])) ?></span>
                </div>
                <div class="rounded-lg bg-white/70 px-3 py-2">
                    <span class="block text-xs font-bold uppercase text-slate-500">Files copied</span>
                    <span class="text-lg font-black"><?= migrate_format_count((int)($result['copied_files'] ?? 0)) ?></span>
                </div>
                <div class="rounded-lg bg-white/70 px-3 py-2">
                    <span class="block text-xs font-bold uppercase text-slate-500">Mode</span>
                    <span class="text-lg font-black"><?= htmlspecialchars((string)($result['mode'] ?? 'import')) ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!$overview['zip_available']): ?>
    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-800">
        <p class="font-bold">PHP Zip extension is unavailable.</p>
        <p class="mt-1 text-sm">Migration export and import need ZipArchive enabled on this server.</p>
    </div>
<?php endif; ?>

<div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Content Tables</p>
        <p class="mt-3 text-3xl font-black text-slate-900"><?= migrate_format_count((int)$overview['table_count']) ?></p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Rows Ready</p>
        <p class="mt-3 text-3xl font-black text-slate-900"><?= migrate_format_count((int)$overview['row_count']) ?></p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">User Tables Excluded</p>
        <p class="mt-3 text-3xl font-black text-slate-900"><?= migrate_format_count(count($overview['excluded_tables'])) ?></p>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5">
            <h2 class="text-xl font-bold text-slate-900">Export package</h2>
            <p class="mt-1 text-sm leading-relaxed text-slate-500">
                Downloads a zip with table data, a manifest, and available files from uploads.
            </p>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="migrate_action" value="export">
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50" <?= !$overview['zip_available'] ? 'disabled' : '' ?>>
                Download Migration Zip
            </button>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5">
            <h2 class="text-xl font-bold text-slate-900">Import package</h2>
            <p class="mt-1 text-sm leading-relaxed text-slate-500">
                Import adds package rows without deleting current content. Replace mode clears current content first.
            </p>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="migrate_action" value="import">

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Migration zip</label>
                <input type="file" name="migration_file" accept=".zip,application/zip" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="replace_current_data" value="1" class="mt-1 rounded border-amber-300 text-amber-600 focus:ring-amber-500" onchange="document.getElementById('replace-confirmation-wrap').classList.toggle('hidden', !this.checked)">
                    <span>
                        <span class="block text-sm font-bold text-amber-900">Delete current content before import</span>
                        <span class="mt-1 block text-sm leading-relaxed text-amber-800">
                            This removes current catalog content and related content files before loading the package. User data is not imported.
                        </span>
                    </span>
                </label>
                <div id="replace-confirmation-wrap" class="mt-4 hidden">
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-amber-900">Confirmation text</label>
                    <input type="text" name="replace_confirmation" placeholder="DELETE CURRENT CONTENT" class="w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm font-semibold text-amber-900 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
                </div>
            </div>

            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" <?= !$overview['zip_available'] ? 'disabled' : '' ?>>
                Import Migration Zip
            </button>
        </form>
    </section>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900">Included data</h2>
        <div class="mt-4 max-h-80 overflow-y-auto rounded-lg border border-slate-100">
            <table class="w-full text-left text-sm">
                <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-[0.14em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Table</th>
                        <th class="px-4 py-3 text-right">Rows</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($overview['tables'] as $table => $count): ?>
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-slate-700"><?= htmlspecialchars($table) ?></td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900"><?= migrate_format_count((int)$count) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900">Excluded user data</h2>
        <div class="mt-4 flex flex-wrap gap-2">
            <?php foreach ($overview['excluded_tables'] as $table): ?>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold text-slate-600"><?= htmlspecialchars($table) ?></span>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($overview['replace_cleanup_tables'])): ?>
            <div class="mt-5 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-bold">Replace mode cleanup</p>
                <p class="mt-1 leading-relaxed">
                    Replace mode also clears user-linked records that would otherwise point at deleted content:
                    <?= htmlspecialchars(implode(', ', $overview['replace_cleanup_tables'])) ?>.
                </p>
            </div>
        <?php endif; ?>
    </section>
</div>
