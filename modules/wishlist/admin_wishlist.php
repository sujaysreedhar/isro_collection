<?php
// admin_wishlist.php

$pdo = $this->pdo;

// Fetch all themes from thematic_taxonomy if available
$themes = [];
try {
    $themes = $pdo->query("SELECT id, name FROM module_themes ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // module_themes might not exist
}

// Fetch all wishlist items
$items = $pdo->query("SELECT * FROM module_wishlist_items ORDER BY priority DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch stores for each item
foreach ($items as &$item) {
    $stmt = $pdo->prepare("SELECT * FROM module_wishlist_stores WHERE wishlist_item_id = ?");
    $stmt->execute([$item['id']]);
    $item['stores'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($item);

// Calculate Stats
$totalItems = count($items);
$wantedItems = 0;
$totalCost = 0;
$themeCosts = [];

// Group items by theme for the view
$groupedItems = [];
foreach ($items as $item) {
    $themeId = $item['theme_id'] ?: 0;
    $groupedItems[$themeId][] = $item;
}

foreach ($groupedItems as $themeId => $themeItems) {
    $tCost = 0;
    foreach ($themeItems as $item) {
        if ($item['status'] !== 'collected') $wantedItems++;
        foreach ($item['stores'] as $store) {
            $p = preg_replace('/[^0-9.]/', '', $store['price']);
            if (is_numeric($p)) {
                $costVal = (float)$p;
                $tCost += $costVal;
                $totalCost += $costVal;
                break; 
            }
        }
    }
    $themeCosts[$themeId] = $tCost;
}

// Map theme names
$themeMap = [0 => 'General / Uncategorized'];
foreach ($themes as $theme) {
    $themeMap[$theme['id']] = $theme['name'];
}
?>

<style>
    .status-badge { @apply px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider; }
    .status-wanted { @apply bg-amber-50 text-amber-600 border border-amber-100; }
    .status-buying { @apply bg-blue-50 text-blue-600 border border-blue-100; }
    .status-collected { @apply bg-emerald-50 text-emerald-600 border border-emerald-100; }
    
    .wishlist-row { @apply transition-all duration-200 hover:bg-slate-50/80 border-b border-slate-100; }
    .wishlist-row:last-child { @apply border-b-0; }
    
    .priority-star { @apply text-amber-400; }
    .priority-star.empty { @apply text-slate-200; }
    
    .external-btn { @apply p-1.5 rounded-lg border border-slate-200 text-slate-400 hover:text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50 transition-all; }
</style>

<div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-900 tracking-tight">Collection Wishlist</h1>
        <p class="text-slate-500 text-sm mt-1 font-medium">Track future acquisitions and coordinate market availability.</p>
    </div>
    
    <div class="flex items-center gap-3">
        <div class="relative group">
            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input type="text" id="wishlist-search" placeholder="Filter wishlist..." class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all w-64 shadow-sm" onkeyup="filterWishlist()">
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-2.5 px-5 flex items-center gap-4 shadow-sm">
            <div class="text-center border-r border-slate-100 pr-4">
                <p class="text-[9px] font-black uppercase text-slate-400 tracking-widest">Pending</p>
                <p class="text-lg font-black text-slate-800"><?= $wantedItems ?></p>
            </div>
            <div class="text-center">
                <p class="text-[9px] font-black uppercase text-slate-400 tracking-widest">Est. Cost</p>
                <p class="text-lg font-black text-indigo-600"><?= number_format($totalCost, 2) ?></p>
            </div>
        </div>
        
        <a href="<?= SITE_URL ?>/admin/ajax.php?action=wishlist_export" class="p-2.5 bg-white border border-slate-200 text-slate-400 rounded-xl hover:text-emerald-600 hover:border-emerald-200 hover:bg-emerald-50 transition-all shadow-sm" title="Export CSV">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        </a>

        <button onclick="openBulkAddModal()" class="inline-flex items-center gap-2 px-4 py-3 bg-white border border-slate-200 text-slate-600 rounded-xl font-bold text-sm hover:bg-slate-50 transition-all active:scale-95 shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            Bulk
        </button>

        <button onclick="openWishlistModal()" class="inline-flex items-center gap-2 px-5 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200 active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
            Add
        </button>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-16 text-center">
        <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
        </div>
        <h3 class="text-xl font-bold text-slate-800">Your wishlist is currently empty</h3>
        <p class="text-slate-500 max-w-sm mx-auto mt-2">Start adding items you want to acquire. You can track prices, stores, and set priorities.</p>
        <button onclick="openWishlistModal()" class="mt-8 px-6 py-2 border-2 border-indigo-600 text-indigo-600 font-bold rounded-xl hover:bg-indigo-600 hover:text-white transition-all">Add Your First Item</button>
    </div>
<?php else: ?>
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-200 text-[11px] font-black uppercase tracking-widest text-slate-400">
                        <th class="py-4 px-6 w-16">Image</th>
                        <th class="py-4 px-6">Item Details</th>
                        <th class="py-4 px-6">Priority</th>
                        <th class="py-4 px-6">Status</th>
                        <th class="py-4 px-6">Best Price</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groupedItems as $themeId => $themeItems): ?>
                        <tr class="bg-slate-50/30 theme-header">
                            <td colspan="6" class="py-2 px-6">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="text-[10px] font-bold text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded"><?= htmlspecialchars($themeMap[$themeId]) ?></span>
                                        <span class="text-[10px] text-slate-400 font-bold"><?= count($themeItems) ?> items</span>
                                    </div>
                                    <div class="flex-1 h-px bg-slate-100"></div>
                                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                        Subtotal: <span class="text-slate-600 font-bold ml-1"><?= number_format($themeCosts[$themeId], 2) ?></span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php foreach ($themeItems as $item): ?>
                            <tr class="wishlist-row group" id="wishlist-item-<?= $item['id'] ?>">
                                <td class="py-4 px-6">
                                    <div class="w-12 h-12 rounded-lg bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                                        <?php if ($item['image_url']): ?>
                                            <img src="<?= SITE_URL ?>/uploads/thumbnails/<?= htmlspecialchars($item['image_url']) ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-800 leading-tight"><?= htmlspecialchars($item['name']) ?></span>
                                        <span class="text-xs text-slate-500 mt-1 line-clamp-1 max-w-xs"><?= htmlspecialchars($item['description'] ?: 'No description') ?></span>
                                        <div class="flex gap-2 mt-2">
                                            <a href="https://www.ebay.com/sch/i.html?_nkw=<?= urlencode($item['name']) ?>" target="_blank" class="external-btn" title="Search on eBay">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M11.666 12.51l-.547 3.013h2.385l.547-3.013h-2.385zm-.397-2.182h2.385l.536-2.951h-2.385l-.536 2.951zm3.879 2.182l.547-3.013h2.385l-.547 3.013h-2.385zm.536-2.951h2.385l.535-2.951h-2.385l-.535 2.951zm.804 7.376h-2.385l-.536 2.951h2.385l.536-2.951zm-3.879 0h-2.385l-.536 2.951h2.385l.536-2.951zm-3.879 0H6.345l-.536 2.951h2.385l.536-2.951zm.536-2.951h2.385l.547-3.013H8.805l-.547 3.013zM1.464 24h21.072l1.464-8.064H0L1.464 24zM24 0H0l1.464 8.064h21.072L24 0z"/></svg>
                                            </a>
                                            <a href="https://www.google.com/search?q=<?= urlencode($item['name']) ?>+for+sale" target="_blank" class="external-btn" title="Search on Google">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C20.187 1.44 17.4 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z"/></svg>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex gap-0.5">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <svg class="w-3 h-3 priority-star <?= $i <= $item['priority'] ? '' : 'empty' ?>" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                                        <?php endfor; ?>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="status-badge status-<?= $item['status'] ?>"><?= $item['status'] ?></span>
                                </td>
                                <td class="py-4 px-6">
                                    <?php 
                                    $minPrice = null;
                                    foreach ($item['stores'] as $s) {
                                        if ($s['price']) {
                                            $p = (float)preg_replace('/[^0-9.]/', '', $s['price']);
                                            if ($minPrice === null || $p < $minPrice) $minPrice = $p;
                                        }
                                    }
                                    ?>
                                    <span class="font-bold text-slate-700"><?= $minPrice !== null ? number_format($minPrice, 2) : '—' ?></span>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <?php if ($item['status'] !== 'collected'): ?>
                                            <button onclick="migrateToItems(<?= $item['id'] ?>)" class="p-2 text-emerald-500 hover:bg-emerald-50 rounded-lg transition-colors" title="Move to Collection">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="editWishlistItem(<?= htmlspecialchars(json_encode($item)) ?>)" class="p-2 text-indigo-500 hover:bg-indigo-50 rounded-lg transition-colors" title="Edit">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </button>
                                        <button onclick="deleteWishlistItem(<?= $item['id'] ?>)" class="p-2 text-red-400 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Wishlist Modal -->
<div id="wishlist-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="flex min-h-screen items-center justify-center p-4 md:p-8">
        <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-md" onclick="closeWishlistModal()"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl max-w-2xl w-full overflow-hidden z-10 flex flex-col max-h-[90vh]">
            <div class="p-8 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <div>
                    <h3 id="modal-title" class="text-2xl font-black text-slate-900 tracking-tight">Add Wishlist Item</h3>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Item Configuration</p>
                </div>
                <button onclick="closeWishlistModal()" class="text-slate-400 hover:text-slate-600 bg-white border border-slate-200 p-2.5 rounded-2xl shadow-sm transition-all hover:rotate-90">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <form id="wishlist-form" class="flex-1 overflow-y-auto p-8 space-y-8" enctype="multipart/form-data">
                <input type="hidden" name="id" id="item-id">
                <input type="hidden" name="existing_image" id="existing-image">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                
                <div class="flex gap-8 items-start">
                    <div class="w-32 shrink-0">
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-3">Reference Image</label>
                        <div id="image-preview-container" class="w-32 h-32 rounded-2xl bg-slate-100 border-2 border-dashed border-slate-200 overflow-hidden flex flex-col items-center justify-center cursor-pointer hover:bg-slate-200 hover:border-slate-300 transition-all group relative">
                            <input type="file" name="image" id="image-input" class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*" onchange="previewImage(this)">
                            <div id="preview-placeholder" class="text-center p-4">
                                <svg class="w-8 h-8 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                <span class="text-[10px] font-bold text-slate-400 mt-1 block">Upload</span>
                            </div>
                            <img id="image-preview" class="hidden w-full h-full object-cover">
                        </div>
                    </div>
                    
                    <div class="flex-1 space-y-6">
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-3">Item Name *</label>
                            <input type="text" name="name" id="item-name" required placeholder="e.g. 1920 Mumbai Handstamp" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:bg-white transition-all text-slate-800 font-bold placeholder:text-slate-300">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-3">Theme</label>
                                <select name="theme_id" id="item-theme" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:bg-white transition-all text-slate-800 font-bold">
                                    <option value="">General</option>
                                    <?php foreach ($themes as $theme): ?>
                                        <option value="<?= $theme['id'] ?>"><?= htmlspecialchars($theme['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-3">Priority (1-5)</label>
                                <select name="priority" id="item-priority" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:bg-white transition-all text-slate-800 font-bold">
                                    <option value="1">1 - Nice to have</option>
                                    <option value="2">2 - Growing interest</option>
                                    <option value="3">3 - Active search</option>
                                    <option value="4">4 - High priority</option>
                                    <option value="5">5 - Critical target</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-3">Status</label>
                        <select name="status" id="item-status" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:bg-white transition-all text-slate-800 font-bold">
                            <option value="wanted">Wanted</option>
                            <option value="buying">Negotiating / Buying</option>
                            <option value="collected">Collected</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-3">Description / Notes</label>
                        <textarea name="description" id="item-description" rows="1" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:bg-white transition-all text-slate-800 font-medium"></textarea>
                    </div>
                </div>
                
                <div class="border-t border-slate-100 pt-8">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h4 class="text-sm font-black text-slate-800">Store Links & Availability</h4>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Where can this be found?</p>
                        </div>
                        <button type="button" onclick="addStoreRow()" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-black rounded-xl hover:bg-slate-200 transition-all active:scale-95">+ Add Link</button>
                    </div>
                    <div id="stores-container" class="space-y-4">
                        <!-- Store rows injected here -->
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-8 border-t border-slate-100">
                    <button type="button" onclick="closeWishlistModal()" class="px-8 py-4 text-slate-500 font-black hover:bg-slate-50 rounded-2xl transition-all uppercase tracking-widest text-xs">Cancel</button>
                    <button type="submit" class="px-10 py-4 bg-indigo-600 text-white font-black rounded-2xl hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-100 active:scale-95 uppercase tracking-widest text-xs">Save Item Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Add Modal -->
<div id="bulk-add-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="flex min-h-screen items-center justify-center p-4 md:p-8">
        <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-md" onclick="closeBulkAddModal()"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl max-w-xl w-full overflow-hidden z-10 flex flex-col max-h-[90vh]">
            <div class="p-8 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <div>
                    <h3 class="text-2xl font-black text-slate-900 tracking-tight">Bulk Import</h3>
                    <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-1">Paste your list below</p>
                </div>
                <button onclick="closeBulkAddModal()" class="text-slate-400 hover:text-slate-600 bg-white border border-slate-200 p-2.5 rounded-2xl shadow-sm transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <form id="bulk-add-form" class="p-8 space-y-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                
                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-3">Item List (Bullet points or new lines)</label>
                    <textarea name="bulk_text" id="bulk-text" rows="10" placeholder="- Item One&#10;- Item Two&#10;- Item Three" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:bg-white transition-all text-slate-800 font-medium placeholder:text-slate-300"></textarea>
                    <p class="text-[10px] text-slate-400 mt-2 italic">We'll automatically clean up bullets like -, *, • or numbers.</p>
                </div>
                
                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-3">Apply Theme to All</label>
                    <select name="theme_id" id="bulk-theme" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:bg-white transition-all text-slate-800 font-bold">
                        <option value="">General</option>
                        <?php foreach ($themes as $theme): ?>
                            <option value="<?= $theme['id'] ?>"><?= htmlspecialchars($theme['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
                    <button type="button" onclick="closeBulkAddModal()" class="px-8 py-4 text-slate-500 font-black hover:bg-slate-50 rounded-2xl transition-all uppercase tracking-widest text-xs">Cancel</button>
                    <button type="submit" class="px-10 py-4 bg-indigo-600 text-white font-black rounded-2xl hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-100 active:scale-95 uppercase tracking-widest text-xs">Import List</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const AJAX_URL = '<?= SITE_URL ?>/admin/ajax.php';
const STORE_ROW_TEMPLATE = `
    <div class="store-row grid grid-cols-1 md:grid-cols-4 gap-4 p-5 bg-slate-50/50 rounded-2xl border border-slate-100 relative group animate-in fade-in slide-in-from-top-2">
        <button type="button" onclick="this.closest('.store-row').remove()" class="absolute -top-2 -right-2 w-7 h-7 bg-white border border-slate-200 text-slate-400 hover:text-red-500 rounded-xl shadow-sm flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all hover:rotate-90">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
        <div class="md:col-span-1">
            <input type="text" placeholder="Store (e.g. eBay)" class="store-name w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
        </div>
        <div class="md:col-span-2">
            <input type="url" placeholder="https://..." class="store-url w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-xs focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
        </div>
        <div class="md:col-span-1">
            <input type="text" placeholder="Price (e.g. $45)" class="store-price w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-xs font-black text-indigo-600 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
        </div>
        <div class="md:col-span-4">
            <input type="text" placeholder="Notes (condition, variant...)" class="store-notes w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-xs focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
        </div>
    </div>
`;

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('image-preview').src = e.target.result;
            document.getElementById('image-preview').classList.remove('hidden');
            document.getElementById('preview-placeholder').classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function openWishlistModal() {
    document.getElementById('modal-title').innerText = 'Add Wishlist Item';
    document.getElementById('wishlist-form').reset();
    document.getElementById('item-id').value = '';
    document.getElementById('existing-image').value = '';
    document.getElementById('image-preview').classList.add('hidden');
    document.getElementById('preview-placeholder').classList.remove('hidden');
    document.getElementById('stores-container').innerHTML = '';
    addStoreRow();
    document.getElementById('wishlist-modal').classList.remove('hidden');
}

function closeWishlistModal() {
    document.getElementById('wishlist-modal').classList.add('hidden');
}

function openBulkAddModal() {
    document.getElementById('bulk-add-form').reset();
    document.getElementById('bulk-add-modal').classList.remove('hidden');
}

function closeBulkAddModal() {
    document.getElementById('bulk-add-modal').classList.add('hidden');
}

document.getElementById('bulk-add-form').onsubmit = function(e) {
    e.preventDefault();
    const text = document.getElementById('bulk-text').value;
    const themeId = document.getElementById('bulk-theme').value;
    const csrfToken = this.querySelector('input[name="csrf_token"]').value;
    
    // Parse the list
    const items = text.split('\n')
        .map(line => line.trim())
        .map(line => line.replace(/^[\-\*\•\d\.\)\s]+/, '')) // Remove bullets/numbers/spaces at start
        .filter(line => line.length > 0);
        
    if (items.length === 0) {
        alert('Please enter at least one item.');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'wishlist_bulk_add');
    formData.append('csrf_token', csrfToken);
    formData.append('theme_id', themeId);
    items.forEach((item, index) => {
        formData.append(`items[${index}]`, item);
    });
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerText = 'Importing ' + items.length + ' items...';
    
    fetch(AJAX_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            window.location.reload();
        } else {
            alert(res.message || 'Error in bulk import');
            submitBtn.disabled = false;
            submitBtn.innerText = 'Import List';
        }
    });
};

function addStoreRow(data = {}) {
    const div = document.createElement('div');
    div.innerHTML = STORE_ROW_TEMPLATE;
    const row = div.firstElementChild;
    
    if (data.store_name) row.querySelector('.store-name').value = data.store_name;
    if (data.store_url) row.querySelector('.store-url').value = data.store_url;
    if (data.price) row.querySelector('.store-price').value = data.price;
    if (data.notes) row.querySelector('.store-notes').value = data.notes;
    
    document.getElementById('stores-container').appendChild(row);
}

function editWishlistItem(item) {
    document.getElementById('modal-title').innerText = 'Edit Item Configuration';
    document.getElementById('item-id').value = item.id;
    document.getElementById('item-name').value = item.name;
    document.getElementById('item-theme').value = item.theme_id || '';
    document.getElementById('item-status').value = item.status;
    document.getElementById('item-priority').value = item.priority || 1;
    document.getElementById('item-description').value = item.description || '';
    document.getElementById('existing-image').value = item.image_url || '';
    
    if (item.image_url) {
        document.getElementById('image-preview').src = '<?= SITE_URL ?>/uploads/thumbnails/' + item.image_url;
        document.getElementById('image-preview').classList.remove('hidden');
        document.getElementById('preview-placeholder').classList.add('hidden');
    } else {
        document.getElementById('image-preview').classList.add('hidden');
        document.getElementById('preview-placeholder').classList.remove('hidden');
    }
    
    document.getElementById('stores-container').innerHTML = '';
    if (item.stores && item.stores.length > 0) {
        item.stores.forEach(store => addStoreRow(store));
    } else {
        addStoreRow();
    }
    
    document.getElementById('wishlist-modal').classList.remove('hidden');
}

document.getElementById('wishlist-form').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const id = formData.get('id');
    const action = id ? 'wishlist_edit' : 'wishlist_add';
    
    // Manual serialization of stores for PHP loop
    let storeIndex = 0;
    document.querySelectorAll('.store-row').forEach(row => {
        const name = row.querySelector('.store-name').value;
        const url = row.querySelector('.store-url').value;
        const price = row.querySelector('.store-price').value;
        const notes = row.querySelector('.store-notes').value;
        if (name || url) {
            formData.append(`stores[${storeIndex}][name]`, name);
            formData.append(`stores[${storeIndex}][url]`, url);
            formData.append(`stores[${storeIndex}][price]`, price);
            formData.append(`stores[${storeIndex}][notes]`, notes);
            storeIndex++;
        }
    });
    
    formData.set('action', action);
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerText;
    submitBtn.disabled = true;
    submitBtn.innerText = 'Synchronizing...';
    
    fetch(AJAX_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            window.location.reload();
        } else {
            alert(res.message || 'Error saving item');
            submitBtn.disabled = false;
            submitBtn.innerText = originalText;
        }
    });
};

function deleteWishlistItem(id) {
    if (!confirm('Are you sure you want to purge this item from your wishlist? This cannot be undone.')) return;
    
    const formData = new FormData();
    formData.append('action', 'wishlist_delete');
    formData.append('id', id);
    formData.append('csrf_token', '<?= htmlspecialchars(ensureCsrfToken()) ?>');
    
    fetch(AJAX_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const row = document.getElementById('wishlist-item-' + id);
            row.classList.add('opacity-0', 'scale-95');
            setTimeout(() => row.remove(), 300);
        }
    });
}

function migrateToItems(id) {
    if (!confirm('Move this item to your main collection? It will be marked as Collected.')) return;
    
    const formData = new FormData();
    formData.append('action', 'wishlist_migrate');
    formData.append('id', id);
    formData.append('csrf_token', '<?= htmlspecialchars(ensureCsrfToken()) ?>');
    
    fetch(AJAX_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (confirm('Item successfully migrated! Visit the collection manager to add final details?')) {
                window.location.href = res.redirect;
            } else {
                window.location.reload();
            }
        } else {
            alert(res.message || 'Migration sync failed');
        }
    });
}

function filterWishlist() {
    const q = document.getElementById('wishlist-search').value.toLowerCase();
    const rows = document.querySelectorAll('.wishlist-row');
    const headers = document.querySelectorAll('.theme-header');
    
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        if (text.includes(q)) {
            row.classList.remove('hidden');
        } else {
            row.classList.add('hidden');
        }
    });
    
    // Hide theme headers if all items in theme are hidden
    headers.forEach(header => {
        let sibling = header.nextElementSibling;
        let hasVisible = false;
        while (sibling && !sibling.classList.contains('theme-header') && sibling.tagName === 'TR') {
            if (!sibling.classList.contains('hidden') && sibling.classList.contains('wishlist-row')) {
                hasVisible = true;
                break;
            }
            sibling = sibling.nextElementSibling;
        }
        if (hasVisible) {
            header.classList.remove('hidden');
        } else {
            header.classList.add('hidden');
        }
    });
}
</script>
