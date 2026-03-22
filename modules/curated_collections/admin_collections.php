<?php
// modules/curated_collections/admin_collections.php

$action = $_GET['action'] ?? 'list';
$colId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'edit' || $action === 'add'): 
    $collection = ['title' => '', 'slug' => '', 'description' => '', 'is_public' => 1];
    $items = [];
    if ($colId > 0) {
        $stmt = $this->pdo->prepare("SELECT * FROM collections WHERE id = ?");
        $stmt->execute([$colId]);
        $collection = $stmt->fetch();
        
        $stmt = $this->pdo->prepare("SELECT i.id, i.title, i.reg_number FROM items i JOIN collection_items ci ON i.id = ci.item_id WHERE ci.collection_id = ? ORDER BY ci.sort_order ASC");
        $stmt->execute([$colId]);
        $items = $stmt->fetchAll();
    }
?>
    <div class="mb-6 flex justify-between items-center">
        <h2 class="text-xl font-bold"><?= $colId > 0 ? 'Edit Collection' : 'Create New Collection' ?></h2>
        <a href="<?= SITE_URL ?>/admin/module_page.php?m=curated_collections" class="text-sm text-gray-500 hover:text-gray-900">&larr; Back to List</a>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-md">Collection saved successfully.</div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-8">
            <div class="bg-white p-6 rounded-xl border border-gray-200">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                    <input type="hidden" name="save_collection" value="1">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input type="text" name="title" value="<?= htmlspecialchars($collection['title']) ?>" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Slug (URL identifier)</label>
                        <input type="text" name="slug" value="<?= htmlspecialchars($collection['slug']) ?>" required
                               placeholder="apollo-11-mission"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="5" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 outline-none transition-all"><?= htmlspecialchars($collection['description']) ?></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="is_public" id="is_public" value="1" <?= $collection['is_public'] ? 'checked' : '' ?> class="rounded border-gray-300">
                        <label for="is_public" class="text-sm text-gray-700">Make this collection public</label>
                    </div>
                    <div class="pt-4 border-t">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-all">Save Collection Details</button>
                    </div>
                </form>
            </div>

            <?php if ($colId > 0): ?>
            <div id="items" class="bg-white p-6 rounded-xl border border-gray-200">
                <h3 class="font-bold text-gray-900 mb-4">Items in this Collection</h3>
                <div id="collection-items-wrapper" class="space-y-2">
                    <?php if ($items): ?>
                        <div class="divide-y border rounded-lg">
                        <?php foreach ($items as $item): ?>
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 truncate"><?= htmlspecialchars($item['title']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($item['reg_number']) ?></p>
                                </div>
                                <form method="POST" onsubmit="return confirm('Remove this item from collection?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                                    <input type="hidden" name="remove_item" value="1">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold">Remove</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 text-center py-6 bg-gray-50 rounded-lg border border-dashed">No items added yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($colId > 0): ?>
        <div class="space-y-6">
            <?php if (!empty($collection['slug'])): ?>
            <div class="bg-white p-6 rounded-xl border border-gray-200">
                <h3 class="font-bold text-gray-900 mb-2 text-sm">Public URL</h3>
                <p class="text-[10px] text-gray-500 mb-3">Share this collection with others using the link below.</p>
                <?php $publicUrl = SITE_URL . '/collection.php?slug=' . urlencode($collection['slug']); ?>
                <div class="flex items-center gap-2 p-2 bg-gray-50 border border-gray-200 rounded-lg group">
                    <input type="text" readonly value="<?= htmlspecialchars($publicUrl) ?>" id="public-url-input" class="bg-transparent text-[10px] text-blue-600 font-mono w-full outline-none">
                    <button onclick="copyPublicUrl(this)" class="p-1 px-2 bg-white border border-gray-200 text-gray-500 text-[10px] font-bold rounded-md hover:bg-gray-100 transition shadow-sm whitespace-nowrap">Copy</button>
                    <a href="<?= $publicUrl ?>" target="_blank" class="p-1 px-2 bg-white border border-gray-200 text-gray-500 text-[10px] font-bold rounded-md hover:bg-gray-100 transition shadow-sm whitespace-nowrap">Open</a>
                </div>
            </div>
            <script>
                function copyPublicUrl(btn) {
                    const input = document.getElementById('public-url-input');
                    input.select();
                    document.execCommand('copy');
                    const originalText = btn.innerHTML;
                    btn.innerHTML = 'Copied!';
                    setTimeout(() => btn.innerHTML = originalText, 2000);
                }
            </script>
            <?php endif; ?>
            <div class="bg-white p-6 rounded-xl border border-gray-200">
                <h3 class="font-bold text-gray-900 mb-4">Add Items</h3>
                <form id="item-search-form" class="space-y-3 relative" onsubmit="event.preventDefault(); searchItems();">
                    <div class="relative">
                        <input type="text" id="item-search-q" autocomplete="off" oninput="debounceSearch()" placeholder="Type to search..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        <div id="autocomplete-results" class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-xl hidden max-h-80 overflow-y-auto">
                            <!-- Results inject here -->
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-200 transition">Search All Items</button>
                </form>
                <div id="search-results" class="mt-4 space-y-2 max-h-96 overflow-y-auto">
                    <!-- Manual search results inject here -->
                </div>
            </div>
        </div>
        <script>
            let searchTimeout = null;
            const ajaxUrl = '<?= SITE_URL ?>/admin/module_page.php?m=curated_collections';
            const colId = <?= (int)$colId ?>;

            function debounceSearch() {
                clearTimeout(searchTimeout);
                const q = document.getElementById('item-search-q').value;
                if (q.length < 2) {
                    document.getElementById('autocomplete-results').classList.add('hidden');
                    return;
                }
                searchTimeout = setTimeout(() => {
                    fetchSuggestions(q);
                }, 300);
            }

            async function fetchSuggestions(q) {
                const resultsDiv = document.getElementById('autocomplete-results');
                const response = await fetch(`${ajaxUrl}&action=search_ajax&exclude_col=${colId}&limit=8&q=${encodeURIComponent(q)}`);
                const data = await response.json();
                
                if (data.data && data.data.length > 0) {
                    resultsDiv.innerHTML = '';
                    data.data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'flex items-center justify-between p-3 hover:bg-blue-50 border-b last:border-0 transition-colors cursor-default';
                        div.innerHTML = `
                            <div class="min-w-0 pr-2">
                                <p class="font-bold text-gray-900 truncate text-xs">${item.title}</p>
                                <p class="text-[10px] text-gray-400 font-mono">${item.reg_number}</p>
                            </div>
                            <button type="button" onclick="addItem(${item.id}, this)" class="bg-blue-600 text-white px-2 py-1 rounded text-[10px] font-bold hover:bg-blue-700 transition-colors">Add</button>
                        `;
                        resultsDiv.appendChild(div);
                    });
                    resultsDiv.classList.remove('hidden');
                } else {
                    resultsDiv.classList.add('hidden');
                }
            }

            async function searchItems() {
                const q = document.getElementById('item-search-q').value;
                const resultsDiv = document.getElementById('search-results');
                const suggestionsDiv = document.getElementById('autocomplete-results');
                suggestionsDiv.classList.add('hidden');
                
                resultsDiv.innerHTML = '<p class="text-xs text-center text-gray-500 py-4 animate-pulse">Searching catalog...</p>';
                
                const response = await fetch(`${ajaxUrl}&action=search_ajax&exclude_col=${colId}&q=${encodeURIComponent(q)}`);
                const data = await response.json();
                
                resultsDiv.innerHTML = '';
                if (data.data && data.data.length > 0) {
                    data.data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'flex items-center justify-between p-3 border border-gray-100 rounded-xl text-xs hover:bg-gray-50 transition-all';
                        div.innerHTML = `
                            <div class="min-w-0 pr-2">
                                <p class="font-bold text-gray-900 truncate">${item.title}</p>
                                <p class="text-gray-400 text-[10px] font-mono">${item.reg_number}</p>
                            </div>
                            <button type="button" onclick="addItem(${item.id}, this)" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg font-bold hover:bg-blue-700 shadow-sm shadow-blue-500/20 transition-all">Add</button>
                        `;
                        resultsDiv.appendChild(div);
                    });
                } else {
                    resultsDiv.innerHTML = '<p class="text-xs text-center text-gray-500 bg-gray-50 py-6 rounded-xl border border-dashed">No items found matching "' + q + '".</p>';
                }
            }

            async function addItem(itemId, btn) {
                const originalContent = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<svg class="animate-spin h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
                
                const formData = new FormData();
                formData.append('csrf_token', '<?= ensureCsrfToken() ?>');
                formData.append('item_id', itemId);
                
                try {
                    const response = await fetch(`${ajaxUrl}&action=add_item_ajax&id=${colId}`, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        btn.innerHTML = '✓';
                        btn.className = 'bg-green-500 text-white px-2 py-1 rounded text-[10px] font-bold';
                        setTimeout(() => {
                            btn.closest('.flex').remove();
                        }, 500);
                        
                        loadCollectionItems();
                    } else {
                        throw new Error(result.error || 'Failed to add item');
                    }
                } catch (e) {
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                    alert('Error: ' + e.message);
                }
            }

            async function loadCollectionItems() {
                const wrapper = document.getElementById('collection-items-wrapper');
                try {
                    const response = await fetch(`${ajaxUrl}&action=get_collection_items_ajax&id=${colId}`);
                    const data = await response.json();
                    
                    if (data.data && data.data.length > 0) {
                        let html = '<div class="divide-y border rounded-lg">';
                        data.data.forEach(item => {
                            html += `
                                <div class="flex items-center justify-between p-3 hover:bg-gray-50 transition-colors">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 truncate">${escapeHtml(item.title)}</p>
                                        <p class="text-xs text-gray-500">${escapeHtml(item.reg_number)}</p>
                                    </div>
                                    <button type="button" onclick="removeItem(${item.id}, this)" class="text-red-500 hover:text-red-700 text-xs font-semibold px-2 py-1">Remove</button>
                                </div>
                            `;
                        });
                        html += '</div>';
                        wrapper.innerHTML = html;
                    } else {
                        wrapper.innerHTML = '<p class="text-sm text-gray-500 text-center py-6 bg-gray-50 rounded-lg border border-dashed">No items added yet.</p>';
                    }
                } catch (e) {
                    console.error('Failed to load items:', e);
                }
            }

            async function removeItem(itemId, btn) {
                if (!confirm('Remove this item from collection?')) return;
                
                const originalContent = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '...';
                
                const formData = new FormData();
                formData.append('csrf_token', '<?= ensureCsrfToken() ?>');
                formData.append('remove_item_ajax', '1'); // We will add this handler
                formData.append('item_id', itemId);
                
                try {
                    const response = await fetch(`${ajaxUrl}&action=remove_item_ajax&id=${colId}`, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        btn.closest('.flex').remove();
                        if (document.querySelectorAll('#collection-items-wrapper .flex').length === 0) {
                            loadCollectionItems();
                        }
                    } else {
                        throw new Error(result.error || 'Failed to remove item');
                    }
                } catch (e) {
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                    alert('Error: ' + e.message);
                }
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Close suggestions when clicking outside
            document.addEventListener('click', function(e) {
                const suggestionsResult = document.getElementById('autocomplete-results');
                const searchForm = document.getElementById('item-search-form');
                if (searchForm && !searchForm.contains(e.target)) {
                    suggestionsResult.classList.add('hidden');
                }
            });
        </script>
        <?php endif; ?>
    </div>

<?php else: 
    $stmt = $this->pdo->query("SELECT * FROM collections ORDER BY created_at DESC");
    $collections = $stmt->fetchAll();
?>
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold">Curated Collections</h2>
            <p class="text-sm text-gray-500">Manage themed groups of artifacts.</p>
        </div>
        <a href="<?= SITE_URL ?>/admin/module_page.php?m=curated_collections&action=add" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-700 transition shadow-md">Create Collection</a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs border-b border-gray-200">
                <tr>
                    <th class="px-6 py-4">Collection</th>
                    <th class="px-6 py-4">Slug</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($collections as $col): ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4">
                        <div class="font-bold text-gray-900"><?= htmlspecialchars($col['title']) ?></div>
                    </td>
                    <td class="px-6 py-4 font-mono text-gray-400"><?= htmlspecialchars($col['slug']) ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $col['is_public'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= $col['is_public'] ? 'Public' : 'Draft' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right whitespace-nowrap font-medium">
                        <a href="<?= SITE_URL ?>/admin/module_page.php?m=curated_collections&action=edit&id=<?= $col['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-2">Edit & Items</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$collections): ?>
                    <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">No collections created yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
