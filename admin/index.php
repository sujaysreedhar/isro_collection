<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

// Core KPIs
$kpiItems = (int)$pdo->query("SELECT COUNT(id) FROM items")->fetchColumn();
$kpiStories = (int)$pdo->query("SELECT COUNT(id) FROM narratives")->fetchColumn();
$kpiMedia = (int)$pdo->query("SELECT COUNT(id) FROM media")->fetchColumn();

// Data quality checks
$itemsNoDesc = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE physical_description IS NULL OR physical_description = ''")->fetchColumn();
$itemsShortDesc = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE LENGTH(physical_description) < 50 AND LENGTH(physical_description) > 0")->fetchColumn();
$itemsNoImage = (int)$pdo->query("SELECT COUNT(*) FROM items i WHERE NOT EXISTS (SELECT 1 FROM media m WHERE m.item_id = i.id)")->fetchColumn();
$itemsHidden = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE is_visible = 0")->fetchColumn();

$mp = new MediaProcessor($pdo, $storage ?? null);
$orphanedFiles = $mp->orphanedFiles();
$orphanedFilesCount = count($orphanedFiles);

// Recent activity
$recentItems = $pdo->query("
    SELECT i.id, i.reg_number, i.title, c.name AS category_name
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    ORDER BY i.id DESC
    LIMIT 5
")->fetchAll();

// Storage usage with 10-minute cache
$totalStorageSize = 0;
$storageCacheKey = 'dashboard_storage_size';
$storageCacheTimeKey = 'dashboard_storage_size_time';

$cachedSize = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
$cachedSize->execute([$storageCacheKey]);
$cachedSize = $cachedSize->fetchColumn();

$cachedTime = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
$cachedTime->execute([$storageCacheTimeKey]);
$cachedTime = (int)$cachedTime->fetchColumn();

if ($cachedSize !== false && (time() - $cachedTime) < 600) {
    $totalStorageSize = (int)$cachedSize;
} else {
    if (isset($storage) && $storage instanceof StorageInterface) {
        $totalStorageSize = $storage->getTotalSize();
        $upsert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $upsert->execute([$storageCacheKey, $totalStorageSize, $totalStorageSize]);
        $upsert->execute([$storageCacheTimeKey, time(), time()]);
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function dashboardPercent(int $count, int $total): int {
    if ($total <= 0) {
        return 0;
    }

    return (int)round(($count / $total) * 100);
}

$formattedStorage = formatBytes($totalStorageSize);

$phpVersion = PHP_VERSION;
$gdEnabled = extension_loaded('gd');
$uploadsWritable = is_writable(__DIR__ . '/../uploads');
$dbHealthy = false;
try {
    $pdo->query("SELECT 1");
    $dbHealthy = true;
} catch (Exception $e) {
    $dbHealthy = false;
}

$storageType = $appSettings['storage_driver'] ?? 'local';
$storageStatus = ($storageType === 's3')
    ? (($storage instanceof S3Storage) ? 'ok' : 'error')
    : ($uploadsWritable ? 'ok' : 'error');
$storageLabel = ($storageType === 's3') ? 'S3 Storage' : 'Local Storage';
$storageValue = ($storageType === 's3') ? ($appSettings['s3_bucket'] ?: 'Not Configured') : ($uploadsWritable ? 'Active' : 'Locked');
$storageNote = ($storageType === 's3')
    ? 'Media is served from your configured bucket. Confirm credentials and bucket policies if uploads stall.'
    : 'Media is served locally. Keep regular backups of uploads/ and monitor disk space.';

$healthChecks = [
    ['label' => 'PHP Version', 'value' => $phpVersion, 'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'ok' : 'warn'],
    ['label' => 'GD Library', 'value' => $gdEnabled ? 'Enabled' : 'Missing', 'status' => $gdEnabled ? 'ok' : 'error'],
    ['label' => 'Database', 'value' => $dbHealthy ? 'Connected' : 'Offline', 'status' => $dbHealthy ? 'ok' : 'error'],
    ['label' => 'Uploads Folder', 'value' => $uploadsWritable ? 'Writable' : 'Locked', 'status' => $uploadsWritable ? 'ok' : 'error'],
    ['label' => $storageLabel, 'value' => $storageValue, 'status' => $storageStatus],
];

$statusStyles = [
    'ok' => [
        'dot' => 'bg-emerald-500',
        'badge' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
    ],
    'warn' => [
        'dot' => 'bg-amber-500',
        'badge' => 'border-amber-200 bg-amber-50 text-amber-700',
    ],
    'error' => [
        'dot' => 'bg-red-500',
        'badge' => 'border-red-200 bg-red-50 text-red-700',
    ],
];

$describedItems = max($kpiItems - $itemsNoDesc, 0);
$itemsWithMedia = max($kpiItems - $itemsNoImage, 0);
$visibleItems = max($kpiItems - $itemsHidden, 0);
$wellDescribedItems = max($kpiItems - $itemsNoDesc - $itemsShortDesc, 0);

$descriptionCoverage = dashboardPercent($describedItems, $kpiItems);
$richDescriptionCoverage = dashboardPercent($wellDescribedItems, $kpiItems);
$mediaCoverage = dashboardPercent($itemsWithMedia, $kpiItems);
$visibilityCoverage = dashboardPercent($visibleItems, $kpiItems);

$attentionItems = [
    [
        'label' => 'Missing descriptions',
        'count' => $itemsNoDesc,
        'summary' => 'Records without physical descriptions are harder to review and publish confidently.',
        'href' => SITE_URL . '/admin/items.php',
        'action' => 'Review items',
        'tone' => 'amber',
    ],
    [
        'label' => 'Short descriptions',
        'count' => $itemsShortDesc,
        'summary' => 'Brief descriptions may need richer context for catalog quality and search clarity.',
        'href' => SITE_URL . '/admin/items.php',
        'action' => 'Improve descriptions',
        'tone' => 'amber',
    ],
    [
        'label' => 'Items without media',
        'count' => $itemsNoImage,
        'summary' => 'Missing media makes the archive feel incomplete for both editors and visitors.',
        'href' => SITE_URL . '/admin/items.php',
        'action' => 'Attach media',
        'tone' => 'red',
    ],
    [
        'label' => 'Hidden items',
        'count' => $itemsHidden,
        'summary' => 'These records are excluded from public browsing until their visibility is restored.',
        'href' => SITE_URL . '/admin/items.php',
        'action' => 'Check visibility',
        'tone' => 'slate',
    ],
    [
        'label' => 'Orphaned uploads',
        'count' => $orphanedFilesCount,
        'summary' => 'Files without matching records can waste storage and complicate cleanup work.',
        'href' => SITE_URL . '/admin/settings.php',
        'action' => 'Inspect storage',
        'tone' => 'red',
    ],
];

usort($attentionItems, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});

$openIssuesCount = count(array_filter($attentionItems, static function (array $item): bool {
    return $item['count'] > 0;
}));

$heroStats = [
    [
        'label' => 'Items',
        'value' => number_format($kpiItems),
        'context' => $descriptionCoverage . '% described',
        'href' => SITE_URL . '/admin/items.php',
    ],
    [
        'label' => 'Stories',
        'value' => number_format($kpiStories),
        'context' => 'Published narratives',
        'href' => SITE_URL . '/admin/narratives.php',
    ],
    [
        'label' => 'Media',
        'value' => number_format($kpiMedia),
        'context' => $mediaCoverage . '% item coverage',
        'href' => SITE_URL . '/admin/items.php',
    ],
    [
        'label' => 'Storage',
        'value' => $formattedStorage,
        'context' => strtoupper($storageType),
        'href' => SITE_URL . '/admin/settings.php',
    ],
];

$coverageCards = [
    [
        'label' => 'Description Coverage',
        'value' => $descriptionCoverage . '%',
        'count' => number_format($describedItems) . ' of ' . number_format($kpiItems) . ' items',
        'detail' => $itemsNoDesc > 0
            ? number_format($itemsNoDesc) . ' records still need descriptions.'
            : 'Every item has a physical description.',
        'barClass' => 'bg-amber-500',
        'panelClass' => 'border-amber-200 bg-amber-50/70',
    ],
    [
        'label' => 'Rich Description Quality',
        'value' => $richDescriptionCoverage . '%',
        'count' => number_format($wellDescribedItems) . ' items are beyond the short-description threshold',
        'detail' => $itemsShortDesc > 0
            ? number_format($itemsShortDesc) . ' records could use fuller context.'
            : 'No short-description flags right now.',
        'barClass' => 'bg-blue-600',
        'panelClass' => 'border-blue-200 bg-blue-50/70',
    ],
    [
        'label' => 'Media Coverage',
        'value' => $mediaCoverage . '%',
        'count' => number_format($itemsWithMedia) . ' of ' . number_format($kpiItems) . ' items',
        'detail' => $itemsNoImage > 0
            ? number_format($itemsNoImage) . ' records still need an image or attachment.'
            : 'Every item has linked media.',
        'barClass' => 'bg-emerald-500',
        'panelClass' => 'border-emerald-200 bg-emerald-50/70',
    ],
    [
        'label' => 'Public Visibility',
        'value' => $visibilityCoverage . '%',
        'count' => number_format($visibleItems) . ' currently visible',
        'detail' => $itemsHidden > 0
            ? number_format($itemsHidden) . ' items are hidden from the public site.'
            : 'Everything is visible on the public site.',
        'barClass' => 'bg-slate-700',
        'panelClass' => 'border-slate-200 bg-slate-50/90',
    ],
];

echo renderAdminHeader("Dashboard");
?>

<div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div>
        <p class="text-xs font-bold uppercase tracking-[0.24em] text-blue-600">Dashboard</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">Collection operations at a glance</h1>
        <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-600 sm:text-base">
            Welcome back, <span class="font-semibold text-slate-800"><?= htmlspecialchars($_SESSION['admin_username']) ?></span>.
            This view prioritizes the records that need attention first, then surfaces overall coverage and recent work.
        </p>
    </div>
    <div class="flex flex-wrap gap-3">
        <a href="<?= SITE_URL ?>/admin/items.php" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-400 hover:bg-slate-50">
            Manage Items
        </a>
        <a href="<?= SITE_URL ?>/admin/narratives.php" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-400 hover:bg-slate-50">
            Review Stories
        </a>
        <a href="<?= SITE_URL ?>/admin/edit_item.php?id=0" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-blue-700">
            Add New Item
        </a>
    </div>
</div>

<div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-12">
    <section class="lg:col-span-7 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-200/70">
        <div class="border-b border-slate-100 bg-gradient-to-r from-slate-900 via-slate-900 to-blue-900 px-6 py-6 sm:px-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-blue-200">Overview</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-white">Archive pulse</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-300">
                        Scan collection volume, story output, media footprint, and storage status before drilling into maintenance work.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-semibold text-white/90">
                        <?= $openIssuesCount > 0 ? number_format($openIssuesCount) . ' issue groups need review' : 'No open cleanup flags' ?>
                    </span>
                    <span class="inline-flex items-center rounded-full border border-blue-300/20 bg-blue-400/10 px-3 py-1 text-xs font-semibold text-blue-100">
                        <?= $visibilityCoverage ?>% publicly visible
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-px bg-slate-200 sm:grid-cols-4">
            <?php foreach ($heroStats as $stat): ?>
                <a href="<?= $stat['href'] ?>" class="group bg-white px-5 py-5 transition-colors hover:bg-slate-50">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400"><?= htmlspecialchars($stat['label']) ?></p>
                    <p class="mt-3 text-3xl font-black tracking-tight text-slate-900"><?= htmlspecialchars($stat['value']) ?></p>
                    <p class="mt-2 text-sm text-slate-500 transition-colors group-hover:text-slate-700"><?= htmlspecialchars($stat['context']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="lg:col-span-5 rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-200/70">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-red-500">Priority Queue</p>
                <h2 class="mt-1 text-xl font-bold tracking-tight text-slate-900">Needs attention</h2>
            </div>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                <?= number_format($openIssuesCount) ?> active
            </span>
        </div>

        <div class="divide-y divide-slate-100">
            <?php
            $visibleAttentionItems = array_slice($attentionItems, 0, 4);
            foreach ($visibleAttentionItems as $item):
                $count = (int)$item['count'];
                $countClass = 'text-slate-500';
                $chipClass = 'border-slate-200 bg-slate-50 text-slate-600';

                if ($item['tone'] === 'red' && $count > 0) {
                    $countClass = 'text-red-600';
                    $chipClass = 'border-red-200 bg-red-50 text-red-700';
                } elseif ($item['tone'] === 'amber' && $count > 0) {
                    $countClass = 'text-amber-600';
                    $chipClass = 'border-amber-200 bg-amber-50 text-amber-700';
                }
            ?>
                <div class="px-6 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($item['label']) ?></p>
                            <p class="mt-1 text-sm leading-relaxed text-slate-500"><?= htmlspecialchars($item['summary']) ?></p>
                        </div>
                        <span class="inline-flex min-w-[3.25rem] items-center justify-center rounded-full border px-3 py-1 text-sm font-bold <?= $chipClass ?>">
                            <?= number_format($count) ?>
                        </span>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-sm font-semibold <?= $countClass ?>">
                            <?= $count > 0 ? 'Action recommended' : 'All clear' ?>
                        </span>
                        <a href="<?= $item['href'] ?>" class="text-sm font-semibold text-blue-600 transition-colors hover:text-blue-800">
                            <?= htmlspecialchars($item['action']) ?> &rarr;
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-12">
    <section class="lg:col-span-7 rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-200/70">
        <div class="border-b border-slate-100 px-6 py-5">
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-blue-600">Coverage Snapshot</p>
            <h2 class="mt-1 text-xl font-bold tracking-tight text-slate-900">Where the archive stands</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-500">
                These ratios make it easier to spot completeness gaps without reading through raw counts one by one.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
            <?php foreach ($coverageCards as $card): ?>
                <div class="rounded-2xl border p-5 <?= $card['panelClass'] ?>">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500"><?= htmlspecialchars($card['label']) ?></p>
                            <p class="mt-2 text-3xl font-black tracking-tight text-slate-900"><?= htmlspecialchars($card['value']) ?></p>
                        </div>
                        <span class="rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-slate-600">
                            <?= htmlspecialchars($card['count']) ?>
                        </span>
                    </div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/80">
                        <div class="h-full rounded-full <?= $card['barClass'] ?>" style="width: <?= (int)rtrim($card['value'], '%') ?>%"></div>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-slate-600"><?= htmlspecialchars($card['detail']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="lg:col-span-5 rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-200/70">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-blue-600">Recent Activity</p>
                <h2 class="mt-1 text-xl font-bold tracking-tight text-slate-900">Recently added items</h2>
            </div>
            <a href="<?= SITE_URL ?>/admin/items.php" class="text-sm font-semibold text-blue-600 transition-colors hover:text-blue-800">
                View all
            </a>
        </div>

        <?php if (count($recentItems) > 0): ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($recentItems as $item): ?>
                    <div class="flex items-center justify-between gap-4 px-6 py-4 transition-colors hover:bg-slate-50">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900"><?= htmlspecialchars($item['title']) ?></p>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <span class="font-mono font-semibold text-slate-400"><?= htmlspecialchars($item['reg_number']) ?></span>
                                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 font-semibold text-slate-600">
                                    <?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?>
                                </span>
                            </div>
                        </div>
                        <a href="<?= SITE_URL ?>/admin/edit_item.php?id=<?= $item['id'] ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition-all hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                            Edit
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="px-6 py-14 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                </div>
                <p class="mt-4 text-sm font-semibold text-slate-700">No items have been added yet.</p>
                <p class="mt-1 text-sm text-slate-500">Start the archive by creating the first collection record.</p>
                <a href="<?= SITE_URL ?>/admin/edit_item.php?id=0" class="mt-4 inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                    Add the first item
                </a>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
    <section class="lg:col-span-7 rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-200/70">
        <div class="border-b border-slate-100 px-6 py-5">
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-blue-600">System Status</p>
            <h2 class="mt-1 text-xl font-bold tracking-tight text-slate-900">Operational readiness</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-500">
                Core services are grouped here so you can confirm the admin stack is healthy without leaving the dashboard.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2 xl:grid-cols-5">
            <?php foreach ($healthChecks as $check): ?>
                <?php $styles = $statusStyles[$check['status']] ?? $statusStyles['ok']; ?>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="h-2.5 w-2.5 rounded-full <?= $styles['dot'] ?>"></span>
                        <span class="rounded-full border px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.16em] <?= $styles['badge'] ?>">
                            <?= htmlspecialchars($check['status']) ?>
                        </span>
                    </div>
                    <p class="mt-4 text-xs font-bold uppercase tracking-[0.16em] text-slate-400"><?= htmlspecialchars($check['label']) ?></p>
                    <p class="mt-2 text-sm font-semibold text-slate-900" title="<?= htmlspecialchars($check['value']) ?>">
                        <?= htmlspecialchars($check['value']) ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="lg:col-span-5 rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-200/70">
        <div class="border-b border-slate-100 px-6 py-5">
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-blue-600">Storage</p>
            <h2 class="mt-1 text-xl font-bold tracking-tight text-slate-900">Media footprint</h2>
        </div>

        <div class="p-6">
            <div class="rounded-3xl bg-slate-900 px-6 py-6 text-white shadow-lg shadow-slate-200/70">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-blue-300">Current usage</p>
                        <p class="mt-3 text-4xl font-black tracking-tight"><?= htmlspecialchars($formattedStorage) ?></p>
                        <p class="mt-2 text-sm text-slate-300"><?= htmlspecialchars($storageLabel) ?> is active.</p>
                    </div>
                    <span class="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-semibold text-white/90">
                        <?= strtoupper(htmlspecialchars($storageType)) ?>
                    </span>
                </div>
            </div>

            <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                <p class="text-sm font-semibold text-slate-900">Storage guidance</p>
                <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= htmlspecialchars($storageNote) ?></p>
            </div>

            <div class="mt-5 flex flex-wrap gap-3">
                <a href="<?= SITE_URL ?>/admin/settings.php" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700">
                    Open Storage Settings
                </a>
                <a href="<?= SITE_URL ?>" target="_blank" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50">
                    View Live Site
                </a>
            </div>
        </div>
    </section>
</div>

<?= renderAdminFooter(); ?>
