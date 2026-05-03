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

// Group items by theme
$groupedItems = [];
foreach ($items as $item) {
    $themeId = $item['theme_id'] ?: 0;
    $groupedItems[$themeId][] = $item;
}

// Fetch stores for each item
foreach ($items as &$item) {
    $stmt = $pdo->prepare("SELECT * FROM module_wishlist_stores WHERE wishlist_item_id = ?");
    $stmt->execute([$item['id']]);
    $item['stores'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($item);

// Map theme names
$themeMap = [0 => 'Uncategorized'];
foreach ($themes as $theme) {
    $themeMap[$theme['id']] = $theme['name'];
}
?>

<style>
    .status-badge { @apply px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider; }
    .status-wanted { @apply bg-yellow-100 text-yellow-700 border border-yellow-200; }
    .status-buying { @apply bg-blue-100 text-blue-700 border border-blue-200; }
    .status-collected { @apply bg-green-100 text-green-700 border border-green-200; }
    
    .wishlist-card { @apply bg-white border border-slate-200 rounded-xl overflow-hidden transition-all duration-200 hover:shadow-md hover:border-slate-300; }
    .store-link { @apply flex items-center justify-between p-2 rounded-lg bg-slate-50 border border-slate-100 text-xs text-slate-600 hover:bg-slate-100 transition-colors; }
</style>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Collection Wishlist</h1>
        <p class="text-slate-500 text-sm mt-1">Plan your future acquisitions and track store availability.</p>
    </div>
    <button onclick="openWishlistModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg font-semibold text-sm hover:bg-indigo-700 transition-all shadow-md shadow-indigo-200">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        Add Wishlist Item
    </button>
</div>

<?php if (empty($groupedItems)): ?>
    <div class="bg-white border-2 border-dashed border-slate-200 rounded-2xl p-12 text-center">
        <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
        </div>
        <h3 class="text-lg font-semibold text-slate-700">Your wishlist is empty</h3>
        <p class="text-slate-500 max-w-sm mx-auto mt-2">Start adding items you want to add to your collection. You can organize them by theme and track where to buy them.</p>
        <button onclick="openWishlistModal()" class="mt-6 text-indigo-600 font-bold hover:underline">Add your first item &rarr;</button>
    </div>
<?php else: ?>
    <div class="space-y-10">
        <?php foreach ($groupedItems as $themeId => $themeItems): ?>
            <div class="theme-group">
                <div class="flex items-center gap-3 mb-4">
                    <h2 class="text-sm font-bold uppercase tracking-widest text-slate-400"><?= htmlspecialchars($themeMap[$themeId]) ?></h2>
                    <div class="flex-1 h-px bg-slate-200"></div>
                    <span class="text-xs font-medium text-slate-400"><?= count($themeItems) ?> items</span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($themeItems as $item): ?>
                        <div class="wishlist-card flex flex-col" id="wishlist-item-<?= $item['id'] ?>">
                            <div class="p-5 flex-1">
                                <div class="flex justify-between items-start mb-3">
                                    <span class="status-badge status-<?= $item['status'] ?>"><?= $item['status'] ?></span>
                                    <div class="flex gap-1">
                                        <button onclick="editWishlistItem(<?= htmlspecialchars(json_encode($item)) ?>)" class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </button>
                                        <button onclick="deleteWishlistItem(<?= $item['id'] ?>)" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <h3 class="text-lg font-bold text-slate-800 mb-1"><?= htmlspecialchars($item['name']) ?></h3>
                                <p class="text-sm text-slate-500 line-clamp-2 mb-4"><?= htmlspecialchars($item['description'] ?: 'No description provided.') ?></p>
                                
                                <?php if (!empty($item['stores'])): ?>
                                    <div class="space-y-2 mt-4">
                                        <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Purchase Options</p>
                                        <?php foreach ($item['stores'] as $store): ?>
                                            <a href="<?= htmlspecialchars($store['store_url']) ?>" target="_blank" class="store-link">
                                                <div class="flex items-center gap-2 overflow-hidden">
                                                    <div class="w-6 h-6 rounded bg-indigo-100 flex items-center justify-center text-indigo-600 text-[10px] font-bold shrink-0">
                                                        <?= strtoupper(substr($store['store_name'] ?: 'S', 0, 1)) ?>
                                                    </div>
                                                    <span class="truncate font-semibold"><?= htmlspecialchars($store['store_name'] ?: 'Store') ?></span>
                                                </div>
                                                <?php if ($store['price']): ?>
                                                    <span class="text-indigo-600 font-bold"><?= htmlspecialchars($store['price']) ?></span>
                                                <?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="p-4 bg-slate-50 border-t border-slate-100 flex gap-2">
                                <?php if ($item['status'] !== 'collected'): ?>
                                    <button onclick="migrateToItems(<?= $item['id'] ?>)" class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 bg-indigo-50 text-indigo-700 text-xs font-bold rounded-lg hover:bg-indigo-100 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        I Got It! Migrate to Collection
                                    </button>
                                <?php else: ?>
                                    <div class="flex-1 text-center py-2 text-xs font-bold text-green-600 uppercase tracking-widest italic">
                                        In Collection
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Wishlist Modal -->
<div id="wishlist-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="flex min-h-screen items-center justify-center p-4 md:p-8">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeWishlistModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden z-10 flex flex-col max-h-[90vh]">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 id="modal-title" class="text-xl font-bold text-slate-800">Add Wishlist Item</h3>
                <button onclick="closeWishlistModal()" class="text-slate-400 hover:text-slate-600 p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <form id="wishlist-form" class="flex-1 overflow-y-auto p-6 space-y-6">
                <input type="hidden" name="id" id="item-id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold uppercase text-slate-400 tracking-wider mb-2">Item Name *</label>
                        <input type="text" name="name" id="item-name" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all text-slate-800 font-medium">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-400 tracking-wider mb-2">Theme</label>
                        <select name="theme_id" id="item-theme" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all text-slate-800 font-medium">
                            <option value="">No Theme</option>
                            <?php foreach ($themes as $theme): ?>
                                <option value="<?= $theme['id'] ?>"><?= htmlspecialchars($theme['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-400 tracking-wider mb-2">Status</label>
                        <select name="status" id="item-status" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all text-slate-800 font-medium">
                            <option value="wanted">Wanted</option>
                            <option value="buying">Buying</option>
                            <option value="collected">Collected</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-400 tracking-wider mb-2">Description / Notes</label>
                    <textarea name="description" id="item-description" rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all text-slate-800"></textarea>
                </div>
                
                <div class="border-t border-slate-100 pt-6">
                    <div class="flex justify-between items-center mb-4">
                        <label class="block text-xs font-bold uppercase text-slate-400 tracking-wider">Store Links & Portals</label>
                        <button type="button" onclick="addStoreRow()" class="text-indigo-600 text-xs font-bold hover:underline">+ Add Store</button>
                    </div>
                    <div id="stores-container" class="space-y-4">
                        <!-- Store rows injected here -->
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
                    <button type="button" onclick="closeWishlistModal()" class="px-6 py-3 text-slate-600 font-bold hover:bg-slate-100 rounded-xl transition-all">Cancel</button>
                    <button type="submit" class="px-8 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100">Save Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const AJAX_URL = '<?= SITE_URL ?>/admin/ajax.php';
const STORE_ROW_TEMPLATE = `
    <div class="store-row grid grid-cols-1 md:grid-cols-3 gap-3 p-4 bg-slate-50 rounded-xl border border-slate-100 relative group">
        <button type="button" onclick="this.closest('.store-row').remove()" class="absolute -top-2 -right-2 w-6 h-6 bg-white border border-slate-200 text-slate-400 hover:text-red-500 rounded-full shadow-sm flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
        <div class="md:col-span-1">
            <input type="text" placeholder="Store Name" class="store-name w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-semibold focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div class="md:col-span-1">
            <input type="url" placeholder="URL (e.g. eBay, Amazon)" class="store-url w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div class="md:col-span-1">
            <input type="text" placeholder="Price (e.g. $45)" class="store-price w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div class="md:col-span-3">
            <input type="text" placeholder="Additional notes/variants" class="store-notes w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
    </div>
`;

function openWishlistModal() {
    document.getElementById('modal-title').innerText = 'Add Wishlist Item';
    document.getElementById('wishlist-form').reset();
    document.getElementById('item-id').value = '';
    document.getElementById('stores-container').innerHTML = '';
    addStoreRow();
    document.getElementById('wishlist-modal').classList.remove('hidden');
}

function closeWishlistModal() {
    document.getElementById('wishlist-modal').classList.add('hidden');
}

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
    document.getElementById('modal-title').innerText = 'Edit Wishlist Item';
    document.getElementById('item-id').value = item.id;
    document.getElementById('item-name').value = item.name;
    document.getElementById('item-theme').value = item.theme_id || '';
    document.getElementById('item-status').value = item.status;
    document.getElementById('item-description').value = item.description || '';
    
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
    
    // Manual serialization of stores
    const stores = [];
    document.querySelectorAll('.store-row').forEach(row => {
        const name = row.querySelector('.store-name').value;
        const url = row.querySelector('.store-url').value;
        const price = row.querySelector('.store-price').value;
        const notes = row.querySelector('.store-notes').value;
        if (name || url) {
            stores.push({ name, url, price, notes });
        }
    });
    
    // Add stores to formData
    formData.set('action', action);
    // FormData doesn't support nested objects easily, so we add them individually
    stores.forEach((store, index) => {
        formData.append(`stores[${index}][name]`, store.name);
        formData.append(`stores[${index}][url]`, store.url);
        formData.append(`stores[${index}][price]`, store.price);
        formData.append(`stores[${index}][notes]`, store.notes);
    });
    
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
        }
    });
};

function deleteWishlistItem(id) {
    if (!confirm('Are you sure you want to delete this wishlist item?')) return;
    
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
            document.getElementById('wishlist-item-' + id).remove();
        }
    });
}

function migrateToItems(id) {
    if (!confirm('This will create a new entry in your main Collection. Continue?')) return;
    
    const formData = new FormData();
    formData.append('action', 'wishlist_migrate');
    formData.append('id', id);
    formData.append('csrf_token', '<?= htmlspecialchars(ensureCsrfToken()) ?>');
    
    const btn = event.currentTarget;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin h-4 w-4 mr-2" viewBox="0 0 24 24">...</svg> Migrating...';
    
    fetch(AJAX_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (confirm('Item migrated! Go to item details to add images and more info?')) {
                window.location.href = res.redirect;
            } else {
                window.location.reload();
            }
        } else {
            alert(res.message || 'Migration failed');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });
}
</script>
