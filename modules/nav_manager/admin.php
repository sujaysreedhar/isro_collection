<?php
/**
 * modules/nav_manager/admin.php
 * 
 * Administrative interface for managing navigation menus.
 */
if (!defined('SITE_URL')) exit;
global $pdo;

// Fetch themes/common assets if needed, but we'll use local styling
?>
<style>
    :root {
        --nav-glass-bg: rgba(255, 255, 255, 0.7);
        --nav-glass-border: rgba(255, 255, 255, 0.4);
        --nav-accent: #6366f1;
    }

    .glass-card {
        background: var(--nav-glass-bg);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--nav-glass-border);
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
    }

    .menu-item-row {
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .menu-item-row:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    .nested-placeholder {
        background: rgba(99, 102, 241, 0.05);
        border: 2px dashed var(--nav-accent);
        height: 50px;
        margin: 5px 0;
        border-radius: 8px;
    }

    .sortable-ghost {
        opacity: 0.4;
        background: #f3f4f6;
    }

    .nested-list {
        min-height: 5px;
    }
</style>

<?php
// Build the catalogue of "available pages" from active modules + built-ins
$activeModules = json_decode($appSettings['active_modules'] ?? '[]', true) ?: [];

// Static built-in pages always available
$builtinPages = [
    ['label' => 'Home',              'url' => '',              'slug' => 'home',        'icon' => '🏠'],
    ['label' => 'Search / Explore',  'url' => 'search.php',   'slug' => 'explore',     'icon' => '🔍'],
];

// Module-contributed frontend pages (only shown if module is active)
$moduleFrontends = [
    'user_galleries'     => ['label' => 'Visual Gallery',  'url' => 'gallery.php',       'slug' => 'gallery',     'icon' => '🖼️'],
    'postmark_atlas'     => ['label' => 'Postmark Atlas',  'url' => 'atlas.php',         'slug' => 'atlas',       'icon' => '📍'],
    'curated_collections'=> ['label' => 'Collections',     'url' => 'collections.php',   'slug' => 'collections', 'icon' => '📚'],
    'timeline'           => ['label' => 'Timeline',        'url' => 'timeline.php',      'slug' => 'timeline',    'icon' => '🕰️'],
    'people'             => ['label' => 'People',          'url' => 'people',            'slug' => 'people',      'icon' => '👤'],
    'blog'               => ['label' => 'Blog',            'url' => 'blog',              'slug' => 'blog',        'icon' => '📰'],
    'contact_us'         => ['label' => 'Contact Us',      'url' => 'contact.php',       'slug' => 'contact',     'icon' => '✉️'],
    'api_export'         => ['label' => 'API Explorer',    'url' => 'api.php',           'slug' => 'api',         'icon' => '🔗'],
    'exhibition_planner' => ['label' => 'Exhibitions',     'url' => 'exhibitions',       'slug' => 'exhibitions', 'icon' => '🏛️'],
    'set_manager'        => ['label' => 'Checklists',      'url' => 'checklists',        'slug' => 'checklists',  'icon' => '☑️'],
];

$availablePages = $builtinPages;
foreach ($moduleFrontends as $moduleSlug => $page) {
    if (in_array($moduleSlug, $activeModules)) {
        $availablePages[] = $page;
        // postmark_atlas also contributes Route Planner
        if ($moduleSlug === 'postmark_atlas') {
            $availablePages[] = ['label' => 'Route Planner', 'url' => 'route-planner.php', 'slug' => 'routes', 'icon' => '🗺️'];
        }
    }
}
?>
<style>
    .page-chip {
        cursor: grab;
        user-select: none;
        transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
        touch-action: none;
    }
    .page-chip:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(99,102,241,0.18);
    }
    .page-chip:active {
        cursor: grabbing;
        opacity: 0.7;
    }
    .page-chip.being-dragged {
        opacity: 0.5;
        transform: scale(0.95);
    }
    .drop-zone-active {
        background: rgba(99,102,241,0.06) !important;
        border-color: #6366f1 !important;
        box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
    }
    .drop-indicator {
        height: 3px;
        background: #6366f1;
        border-radius: 2px;
        margin: 2px 0;
        display: none;
    }
    .drop-indicator.visible {
        display: block;
    }
</style>

<div class="p-6 max-w-7xl mx-auto" id="nav-manager-app">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Navigation Menus</h1>
            <p class="text-gray-500 mt-1 uppercase text-xs font-bold tracking-widest">Global Site Structure</p>
        </div>
        <div class="flex items-center gap-3">
            <select id="menu-selector" class="bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2.5 min-w-[200px]">
                <option value="">Select a Menu...</option>
            </select>
            <button onclick="openItemModal()" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm whitespace-nowrap">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Link
            </button>
        </div>
    </div>

    <!-- Two-column layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left: Menu Items (takes 2 cols) -->
        <div class="lg:col-span-2">
            <div id="menu-container" class="space-y-2 min-h-[200px] rounded-2xl p-1 transition-all" id="drop-target">
                <div class="text-center py-20 glass-card rounded-2xl border-dashed border-2 border-gray-200">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No menu selected</h3>
                    <p class="mt-1 text-sm text-gray-500">Pick a menu from the dropdown above, or drag a page from the right panel.</p>
                </div>
            </div>
        </div>

        <!-- Right: Quick Add panel (1 col) -->
        <div class="lg:col-span-1">
            <div class="glass-card rounded-2xl p-5 sticky top-24">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-7 h-7 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Quick Add</h3>
                        <p class="text-xs text-gray-400">Drag onto the menu or click to add</p>
                    </div>
                </div>

                <div id="quick-add-chips" class="flex flex-col gap-2">
                    <?php foreach ($availablePages as $page): ?>
                    <div class="page-chip flex items-center gap-3 px-3 py-2.5 bg-white border border-gray-200 rounded-xl hover:border-indigo-300 hover:bg-indigo-50/50"
                         draggable="true"
                         data-label="<?= htmlspecialchars($page['label']) ?>"
                         data-url="<?= htmlspecialchars($page['url']) ?>"
                         data-slug="<?= htmlspecialchars($page['slug']) ?>">
                        <span class="text-xl leading-none"><?= $page['icon'] ?></span>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($page['label']) ?></div>
                            <div class="text-xs text-gray-400 font-mono truncate"><?= $page['url'] ?: '/' ?></div>
                        </div>
                        <button onclick="quickAddPage('<?= htmlspecialchars($page['label']) ?>','<?= htmlspecialchars($page['url']) ?>','<?= htmlspecialchars($page['slug']) ?>')"
                                title="Add to menu"
                                class="flex-shrink-0 w-6 h-6 rounded-full bg-indigo-100 hover:bg-indigo-600 text-indigo-600 hover:text-white flex items-center justify-center transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                            </svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-5 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400 text-center">
                        💡 Only active modules appear here.<br>Enable more modules to unlock pages.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Add/Edit Modal -->
<div id="item-modal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="closeItemModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white rounded-2xl shadow-2xl p-6 border border-gray-100">
        <h2 id="modal-title" class="text-xl font-bold text-gray-900 mb-6">Edit Menu Item</h2>
        
        <form id="item-form" class="space-y-4" onsubmit="saveItem(event)">
            <input type="hidden" id="item-id">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                <input type="text" id="item-label" required class="block w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">URL / Path</label>
                <input type="text" id="item-url" placeholder="e.g. search.php or https://..." class="block w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all">
                <p class="text-xs text-gray-400 mt-1">Relative paths are recommended for internal links.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Active Slug (Optional)</label>
                <input type="text" id="item-slug" placeholder="e.g. explore" class="block w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all">
                <p class="text-xs text-gray-400 mt-1">Used to highlight the item when $currentMenu matches this value.</p>
            </div>

            <div class="flex items-center gap-6 pt-2">
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="item-visible" checked class="sr-only peer">
                    <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    <span class="ms-3 text-sm font-medium text-gray-700">Visible</span>
                </label>
                
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="item-target" class="sr-only peer">
                    <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    <span class="ms-3 text-sm font-medium text-gray-700">New Tab</span>
                </label>
            </div>

            <div class="flex justify-end gap-3 mt-8">
                <button type="button" onclick="closeItemModal()" class="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg shadow-sm transition-all">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
    const API_URL = '<?= SITE_URL ?>/admin/ajax.php';
    const CSRF_TOKEN = '<?= ensureCsrfToken() ?>';
    
    let currentMenuId = null;
    let menuItems = [];

    // Init: Load Menus
    async function loadMenus() {
        try {
            const res = await fetch(`${API_URL}?action=nav_manager_get_menus`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            if (data.success) {
                const selector = document.getElementById('menu-selector');
                selector.innerHTML = '<option value="">Select a Menu...</option>';
                data.menus.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = m.name;
                    selector.appendChild(opt);
                });
            } else {
                console.error('nav_manager_get_menus failed:', data.error ?? data);
            }
        } catch (e) {
            console.error('loadMenus error:', e);
        }
    }

    document.getElementById('menu-selector').addEventListener('change', (e) => {
        currentMenuId = e.target.value;
        if (currentMenuId) loadMenuItems();
        else document.getElementById('menu-container').innerHTML = '';
    });

    async function loadMenuItems() {
        const res = await fetch(`${API_URL}?action=nav_manager_get_items&menu_id=${currentMenuId}`);
        const data = await res.json();
        if (data.success) {
            menuItems = data.items;
            renderMenu();
        }
    }

    function renderMenu() {
        const container = document.getElementById('menu-container');
        container.innerHTML = '';
        
        // Build Tree structure from flat array
        const map = {};
        const roots = [];
        
        menuItems.forEach(item => {
            map[item.id] = { ...item, children: [] };
        });
        
        menuItems.forEach(item => {
            if (item.parent_id && map[item.parent_id]) {
                map[item.parent_id].children.push(map[item.id]);
            } else {
                roots.push(map[item.id]);
            }
        });

        const list = document.createElement('div');
        list.className = 'nested-list space-y-2';
        list.id = 'root-list';
        
        roots.sort((a,b) => a.sort_order - b.sort_order).forEach(root => {
            list.appendChild(createItemEl(root));
        });

        container.appendChild(list);
        initSortable();
    }

    function createItemEl(item) {
        const div = document.createElement('div');
        div.className = 'menu-item-row group';
        div.dataset.id = item.id;
        
        div.innerHTML = `
            <div class="glass-card rounded-xl p-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="cursor-move text-gray-300 hover:text-indigo-600 transition-colors">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M7 10h10v2H7zM7 13h10v2H7z"/></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                             <span class="font-bold text-gray-900 text-lg">${item.label}</span>
                             ${!item.is_visible ? '<span class="px-2 py-0.5 rounded text-[10px] bg-red-100 text-red-600 font-bold uppercase">Hidden</span>' : ''}
                        </div>
                        <span class="text-xs text-gray-400 font-mono">${item.url || '#'}</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button onclick="editItem(${item.id})" class="p-2 text-gray-500 hover:text-indigo-600 transform hover:scale-110 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    </button>
                    <button onclick="deleteItem(${item.id})" class="p-2 text-gray-500 hover:text-red-600 transform hover:scale-110 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
            <div class="nested-list pl-12 mt-2 space-y-2"></div>
        `;

        const sublist = div.querySelector('.nested-list');
        if (item.children) {
            item.children.sort((a,b) => a.sort_order - b.sort_order).forEach(child => {
                sublist.appendChild(createItemEl(child));
            });
        }

        return div;
    }

    let sortables = [];
    function initSortable() {
        sortables.forEach(s => s.destroy());
        sortables = [];
        
        document.querySelectorAll('.nested-list').forEach(el => {
            sortables.push(new Sortable(el, {
                group: 'nested',
                animation: 200,
                fallbackOnBody: true,
                swapThreshold: 0.65,
                ghostClass: 'sortable-ghost',
                handle: '.cursor-move',
                onEnd: saveOrder
            }));
        });
    }

    async function saveOrder() {
        const orderData = [];
        const processList = (parentEl, parentId = null) => {
            Array.from(parentEl.children).forEach((el, index) => {
                if (el.dataset.id) {
                    orderData.push({ id: el.dataset.id, parent_id: parentId });
                    const sublist = el.querySelector('.nested-list');
                    if (sublist) processList(sublist, el.dataset.id);
                }
            });
        };

        processList(document.getElementById('root-list'));

        const fd = new FormData();
        fd.append('action', 'nav_manager_save_order');
        fd.append('menu_id', currentMenuId);
        fd.append('csrf_token', CSRF_TOKEN);
        
        orderData.forEach((item, i) => {
           fd.append(`order[${i}][id]`, item.id);
           fd.append(`order[${i}][parent_id]`, item.parent_id || '');
        });

        const res = await fetch(API_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) alert(data.error);
        // We don't reload menu to keep smooth interaction, unless there was an error
    }

    function openItemModal(id = 0) {
        if (!currentMenuId) {
            alert('Please select a menu first');
            return;
        }
        document.getElementById('item-id').value = id;
        document.getElementById('modal-title').textContent = id ? 'Edit Menu Item' : 'Add Menu Item';
        
        if (id) {
            const item = menuItems.find(i => i.id == id);
            document.getElementById('item-label').value = item.label;
            document.getElementById('item-url').value = item.url;
            document.getElementById('item-slug').value = item.slug ?? '';
            document.getElementById('item-visible').checked = !!parseInt(item.is_visible);
            document.getElementById('item-target').checked = !!parseInt(item.target_blank);
        } else {
            document.getElementById('item-form').reset();
            document.getElementById('item-visible').checked = true;
        }
        
        document.getElementById('item-modal').classList.remove('hidden');
    }

    function closeItemModal() {
        document.getElementById('item-modal').classList.add('hidden');
    }

    function editItem(id) {
        openItemModal(id);
    }

    async function saveItem(e) {
        e.preventDefault();
        const fd = new FormData(document.getElementById('item-form'));
        fd.append('action', 'nav_manager_save_item');
        fd.append('id', document.getElementById('item-id').value);
        fd.append('menu_id', currentMenuId);
        fd.append('label', document.getElementById('item-label').value);
        fd.append('url', document.getElementById('item-url').value);
        fd.append('slug', document.getElementById('item-slug').value);
        fd.append('is_visible', document.getElementById('item-visible').checked ? 1 : 0);
        fd.append('target_blank', document.getElementById('item-target').checked ? 1 : 0);
        fd.append('csrf_token', CSRF_TOKEN);

        const res = await fetch(API_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            closeItemModal();
            loadMenuItems();
        } else {
            alert(data.error);
        }
    }

    async function deleteItem(id) {
        if (!confirm('Are you sure you want to delete this link? Sub-links will be moved up a level.')) return;
        
        const fd = new FormData();
        fd.append('action', 'nav_manager_delete_item');
        fd.append('id', id);
        fd.append('csrf_token', CSRF_TOKEN);

        const res = await fetch(API_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) loadMenuItems();
        else alert(data.error);
    }

    // ── Quick Add: immediately save a chip to the menu ─────────────────────────
    async function quickAddPage(label, url, slug) {
        if (!currentMenuId) {
            alert('Please select a menu first before adding pages.');
            return;
        }
        const fd = new FormData();
        fd.append('action', 'nav_manager_save_item');
        fd.append('id', 0);
        fd.append('menu_id', currentMenuId);
        fd.append('label', label);
        fd.append('url', url);
        fd.append('slug', slug);
        fd.append('is_visible', 1);
        fd.append('target_blank', 0);
        fd.append('csrf_token', CSRF_TOKEN);

        const btn = event?.currentTarget;
        if (btn) { btn.disabled = true; btn.textContent = '…'; }

        const res = await fetch(API_URL, { method: 'POST', body: fd });
        const data = await res.json();

        if (btn) { btn.disabled = false; btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>'; }

        if (data.success) {
            // Brief flash on chip
            const chip = btn?.closest('.page-chip');
            if (chip) {
                chip.classList.add('ring-2', 'ring-green-400', 'bg-green-50');
                setTimeout(() => chip.classList.remove('ring-2', 'ring-green-400', 'bg-green-50'), 800);
            }
            await loadMenuItems();
        } else {
            alert(data.error || 'Could not add page.');
        }
    }

    // ── Drag-and-drop from chips to menu ──────────────────────────────────────
    let draggedChip = null;

    function initChipDragDrop() {
        document.querySelectorAll('.page-chip').forEach(chip => {
            chip.addEventListener('dragstart', e => {
                draggedChip = {
                    label: chip.dataset.label,
                    url: chip.dataset.url,
                    slug: chip.dataset.slug,
                };
                chip.classList.add('being-dragged');
                e.dataTransfer.effectAllowed = 'copy';
                e.dataTransfer.setData('text/plain', chip.dataset.label);
            });
            chip.addEventListener('dragend', () => {
                chip.classList.remove('being-dragged');
                draggedChip = null;
                document.getElementById('menu-container').classList.remove('drop-zone-active');
            });
        });

        const container = document.getElementById('menu-container');
        container.addEventListener('dragover', e => {
            if (!draggedChip) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            container.classList.add('drop-zone-active');
        });
        container.addEventListener('dragleave', e => {
            if (!container.contains(e.relatedTarget)) {
                container.classList.remove('drop-zone-active');
            }
        });
        container.addEventListener('drop', async e => {
            e.preventDefault();
            container.classList.remove('drop-zone-active');
            if (!draggedChip) return;
            if (!currentMenuId) {
                alert('Please select a menu first.');
                return;
            }
            const { label, url, slug } = draggedChip;
            draggedChip = null;

            // Show a brief drop animation
            const flash = document.createElement('div');
            flash.className = 'rounded-xl p-3 bg-indigo-50 border-2 border-indigo-300 text-sm font-semibold text-indigo-700 text-center animate-pulse';
            flash.textContent = `Adding "${label}"…`;
            container.prepend(flash);

            const fd = new FormData();
            fd.append('action', 'nav_manager_save_item');
            fd.append('id', 0);
            fd.append('menu_id', currentMenuId);
            fd.append('label', label);
            fd.append('url', url);
            fd.append('slug', slug);
            fd.append('is_visible', 1);
            fd.append('target_blank', 0);
            fd.append('csrf_token', CSRF_TOKEN);

            const res = await fetch(API_URL, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                await loadMenuItems();
            } else {
                flash.remove();
                alert(data.error || 'Could not add page.');
            }
        });
    }

    loadMenus().then(() => initChipDragDrop());
</script>
