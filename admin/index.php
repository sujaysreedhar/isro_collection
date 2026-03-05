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

<div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-900 leading-tight">Overview</h1>
    <p class="text-sm text-gray-500 mt-1">Welcome back, <?= htmlspecialchars($_SESSION['admin_username']) ?>. Here is what is happening in the collection.</p>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-gray-500 mb-1">Total Items</p>
            <p class="text-3xl font-bold text-gray-900"><?= number_format((int)$kpiItems) ?></p>
        </div>
        <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-gray-500 mb-1">Active Stories</p>
            <p class="text-3xl font-bold text-gray-900"><?= number_format((int)$kpiStories) ?></p>
        </div>
        <div class="w-12 h-12 bg-green-50 text-green-600 rounded-full flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-gray-500 mb-1">Media Files</p>
            <p class="text-3xl font-bold text-gray-900"><?= number_format((int)$kpiMedia) ?></p>
        </div>
        <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-full flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
    </div>
</div>

<!-- Data Health Report -->
<div class="mb-8 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-800">Data Health Report</h3>
        <span class="text-xs text-gray-400 uppercase font-bold tracking-wider">Potential Issues</span>
    </div>
    <div class="divide-y divide-gray-100">
        <div class="px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <?php if ($itemsNoDesc > 0): ?>
                    <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                <?php else: ?>
                    <span class="w-2 h-2 rounded-full bg-green-400 flex-shrink-0"></span>
                <?php endif; ?>
                <p class="text-sm text-gray-700">Items missing physical description</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-lg font-bold <?= $itemsNoDesc > 0 ? 'text-amber-600' : 'text-green-600' ?>"><?= $itemsNoDesc ?></span>
                <?php if ($itemsNoDesc > 0): ?>
                    <a href="<?= SITE_URL ?>/admin/items.php" class="text-xs text-blue-600 hover:underline font-medium">Review &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <?php if ($itemsNoImage > 0): ?>
                    <span class="w-2 h-2 rounded-full bg-red-400 flex-shrink-0"></span>
                <?php else: ?>
                    <span class="w-2 h-2 rounded-full bg-green-400 flex-shrink-0"></span>
                <?php endif; ?>
                <p class="text-sm text-gray-700">Items with no attached images</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-lg font-bold <?= $itemsNoImage > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= $itemsNoImage ?></span>
                <?php if ($itemsNoImage > 0): ?>
                    <a href="<?= SITE_URL ?>/admin/items.php" class="text-xs text-blue-600 hover:underline font-medium">Review &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="w-2 h-2 rounded-full <?= $itemsHidden > 0 ? 'bg-gray-400' : 'bg-green-400' ?> flex-shrink-0"></span>
                <p class="text-sm text-gray-700">Hidden (not visible on public site)</p>
            </div>
            <span class="text-lg font-bold text-gray-500"><?= $itemsHidden ?></span>
        </div>
        <div class="px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <?php if ($itemsShortDesc > 0): ?>
                    <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                <?php else: ?>
                    <span class="w-2 h-2 rounded-full bg-green-400 flex-shrink-0"></span>
                <?php endif; ?>
                <p class="text-sm text-gray-700">Items with description shorter than 50 characters</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-lg font-bold <?= $itemsShortDesc > 0 ? 'text-amber-600' : 'text-green-600' ?>"><?= $itemsShortDesc ?></span>
                <?php if ($itemsShortDesc > 0): ?>
                    <a href="<?= SITE_URL ?>/admin/items.php" class="text-xs text-blue-600 hover:underline font-medium">Review &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <?php if (count($orphanedFiles) > 0): ?>
                    <span class="w-2 h-2 rounded-full bg-red-400 flex-shrink-0"></span>
                <?php else: ?>
                    <span class="w-2 h-2 rounded-full bg-green-400 flex-shrink-0"></span>
                <?php endif; ?>
                <div>
                    <p class="text-sm text-gray-700">Orphaned files <span class="text-gray-400 font-normal">(in uploads/ but not in database)</span></p>
                    <?php if (count($orphanedFiles) > 0): ?>
                    <p class="text-xs text-gray-400 mt-1"><?= implode(', ', array_slice(array_map('htmlspecialchars', $orphanedFiles), 0, 3)) ?><?= count($orphanedFiles) > 3 ? ' …' : '' ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <span class="text-lg font-bold <?= count($orphanedFiles) > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= count($orphanedFiles) ?></span>
        </div>
    </div>
</div>

<!-- Quick Activity -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
        <h3 class="text-lg font-semibold text-gray-800">Recently Added Items</h3>
        <a href="<?= SITE_URL ?>/admin/items.php" class="text-sm font-medium text-blue-600 hover:text-blue-800">View All</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-gray-50 text-gray-500 uppercase font-semibold text-xs border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3">Reg #</th>
                    <th class="px-6 py-3">Title</th>
                    <th class="px-6 py-3">Category</th>
                    <th class="px-6 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (count($recentItems) > 0): ?>
                    <?php foreach ($recentItems as $item): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900"><?= htmlspecialchars($item['reg_number']) ?></td>
                        <td class="px-6 py-4"><?= htmlspecialchars($item['title']) ?></td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                <?= htmlspecialchars($item['category_name'] ?? 'None') ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <a href="<?= SITE_URL ?>/admin/edit_item.php?id=<?= $item['id'] ?>" class="text-blue-600 hover:text-blue-900 font-medium">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500">No items found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderAdminFooter(); ?>
