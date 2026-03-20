<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

// Fetch KPIs
$kpiItems   = $pdo->query("SELECT COUNT(id) FROM items")->fetchColumn();
$kpiStories = $pdo->query("SELECT COUNT(id) FROM narratives")->fetchColumn();
$kpiMedia   = $pdo->query("SELECT COUNT(id) FROM media")->fetchColumn();

// Data Health Report
$itemsNoDesc    = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE physical_description IS NULL OR physical_description = ''")->fetchColumn();
$itemsShortDesc = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE LENGTH(physical_description) < 50 AND LENGTH(physical_description) > 0")->fetchColumn();
$itemsNoImage   = (int)$pdo->query("SELECT COUNT(*) FROM items i WHERE NOT EXISTS (SELECT 1 FROM media m WHERE m.item_id = i.id)")->fetchColumn();
$itemsHidden    = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE is_visible = 0")->fetchColumn();

// Orphaned files check via MediaProcessor
require_once __DIR__ . '/../MediaProcessor.php';
$mp = new MediaProcessor($pdo, $storage ?? null);
$orphanedFiles = $mp->orphanedFiles();

// Fetch 5 most recent items for a quick overview
$recentItems = $pdo->query("
    SELECT i.id, i.reg_number, i.title, c.name as category_name 
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    ORDER BY i.id DESC LIMIT 5
")->fetchAll();

echo renderAdminHeader("Dashboard");
?>

<div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Overview</h1>
        <p class="text-base text-slate-500 mt-1">Welcome back, <span class="font-medium text-slate-700"><?= htmlspecialchars($_SESSION['admin_username']) ?></span>. Here is the pulse of your archive.</p>
    </div>
    <div class="flex gap-3">
        <a href="<?= SITE_URL ?>/admin/items.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-semibold rounded-lg shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">Manage Items</a>
        <a href="<?= SITE_URL ?>/admin/edit_item.php?id=0" class="px-4 py-2 bg-blue-600 border border-transparent text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all">Add New Item</a>
    </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
    <div class="bg-white rounded-2xl shadow-sm shadow-slate-200/50 border border-slate-200 p-6 relative overflow-hidden group hover:shadow-md transition-shadow">
        <div class="absolute top-0 right-0 p-4 opacity-10 transform translate-x-1/4 -translate-y-1/4 group-hover:scale-110 transition-transform">
            <svg class="w-24 h-24 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>
        </div>
        <div class="relative flex items-center justify-between">
            <div>
                <p class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-1">Total Items</p>
                <p class="text-4xl font-black text-slate-900"><?= number_format((int)$kpiItems) ?></p>
            </div>
            <div class="w-14 h-14 bg-gradient-to-br from-blue-50 to-blue-100/50 text-blue-600 rounded-2xl flex items-center justify-center border border-blue-200/50 shadow-sm">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl shadow-sm shadow-slate-200/50 border border-slate-200 p-6 relative overflow-hidden group hover:shadow-md transition-shadow">
        <div class="absolute top-0 right-0 p-4 opacity-10 transform translate-x-1/4 -translate-y-1/4 group-hover:scale-110 transition-transform">
            <svg class="w-24 h-24 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
        </div>
        <div class="relative flex items-center justify-between">
            <div>
                <p class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-1">Active Stories</p>
                <p class="text-4xl font-black text-slate-900"><?= number_format((int)$kpiStories) ?></p>
            </div>
            <div class="w-14 h-14 bg-gradient-to-br from-emerald-50 to-emerald-100/50 text-emerald-600 rounded-2xl flex items-center justify-center border border-emerald-200/50 shadow-sm">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl shadow-sm shadow-slate-200/50 border border-slate-200 p-6 relative overflow-hidden group hover:shadow-md transition-shadow">
        <div class="absolute top-0 right-0 p-4 opacity-10 transform translate-x-1/4 -translate-y-1/4 group-hover:scale-110 transition-transform">
            <svg class="w-24 h-24 text-purple-600" fill="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2 2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
        <div class="relative flex items-center justify-between">
            <div>
                <p class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-1">Media Files</p>
                <p class="text-4xl font-black text-slate-900"><?= number_format((int)$kpiMedia) ?></p>
            </div>
            <div class="w-14 h-14 bg-gradient-to-br from-purple-50 to-purple-100/50 text-purple-600 rounded-2xl flex items-center justify-center border border-purple-200/50 shadow-sm">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16zm0 0l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2 2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Left Column: Data Health -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-sm shadow-slate-200/50 border border-slate-200 overflow-hidden h-full">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <h3 class="text-base font-bold text-slate-900">Data Health Report</h3>
            </div>
            <div class="divide-y divide-slate-100">
                <!-- Data issues check -->
                <?php 
                    $healthItems = [
                        ['label' => 'No Description', 'count' => $itemsNoDesc, 'alert' => true, 'color' => 'amber'],
                        ['label' => 'Short Description', 'count' => $itemsShortDesc, 'alert' => true, 'color' => 'amber'],
                        ['label' => 'No Media Attached', 'count' => $itemsNoImage, 'alert' => true, 'color' => 'red'],
                        ['label' => 'Hidden from Public', 'count' => $itemsHidden, 'alert' => false, 'color' => 'slate'],
                        ['label' => 'Orphaned Uploads', 'count' => count($orphanedFiles), 'alert' => true, 'color' => 'red'],
                    ];
                ?>
                <?php foreach($healthItems as $hi): ?>
                <div class="px-6 py-4 flex items-center justify-between hover:bg-slate-50 transition-colors">
                    <div class="flex items-center space-x-3">
                        <?php if ($hi['count'] > 0 && $hi['alert']): ?>
                            <span class="w-2.5 h-2.5 rounded-full bg-<?= $hi['color'] ?>-500 shadow-sm shadow-<?= $hi['color'] ?>-500/50 flex-shrink-0 animate-pulse"></span>
                        <?php elseif ($hi['count'] > 0 && !$hi['alert']): ?>
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-400 flex-shrink-0"></span>
                        <?php else: ?>
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 flex-shrink-0"></span>
                        <?php endif; ?>
                        <p class="text-sm font-medium text-slate-700"><?= $hi['label'] ?></p>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-base font-bold <?= $hi['count'] > 0 && $hi['alert'] ? 'text-'.$hi['color'].'-600' : 'text-slate-500' ?>"><?= $hi['count'] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 text-center">
                <a href="<?= SITE_URL ?>/admin/items.php" class="text-xs font-bold text-blue-600 hover:text-blue-800 uppercase tracking-widest transition-colors">Review Catalog &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Right Column: Recent Activity -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-sm shadow-slate-200/50 border border-slate-200 overflow-hidden h-full">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <h3 class="text-base font-bold text-slate-900">Recently Added</h3>
                <a href="<?= SITE_URL ?>/admin/items.php" class="text-sm font-semibold text-blue-600 hover:text-blue-800 transition-colors">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-white text-slate-400 uppercase font-bold text-[11px] tracking-wider border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-4">Item Details</th>
                            <th class="px-6 py-4 hidden sm:table-cell">Category</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (count($recentItems) > 0): ?>
                            <?php foreach ($recentItems as $item): ?>
                            <tr class="hover:bg-slate-50 transition-colors group">
                                <td class="px-6 py-4 font-medium text-slate-900">
                                    <div class="flex flex-col">
                                        <span class="text-base"><?= htmlspecialchars($item['title']) ?></span>
                                        <span class="text-xs font-bold text-slate-400 font-mono mt-0.5"><?= htmlspecialchars($item['reg_number']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 hidden sm:table-cell align-middle">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                        <?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap align-middle">
                                    <a href="<?= SITE_URL ?>/admin/edit_item.php?id=<?= $item['id'] ?>" class="inline-flex items-center justify-center p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-all border border-transparent hover:border-blue-100">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-slate-400">
                                        <svg class="w-12 h-12 mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                        <span class="font-medium text-sm text-slate-500">No items found in the archive.</span>
                                        <a href="<?= SITE_URL ?>/admin/edit_item.php?id=0" class="mt-2 text-sm text-blue-600 hover:underline">Add the first item</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= renderAdminFooter(); ?>
