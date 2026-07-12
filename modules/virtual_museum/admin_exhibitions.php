<?php
// modules/virtual_museum/admin_exhibitions.php
// Rendered inside admin/module_page.php content area (admin layout wraps it).

/** @var PDO $pdo */
global $pdo;

$csrf = function_exists('ensureCsrfToken') ? ensureCsrfToken() : '';

// Source option lists
$categories = [];
$collections = [];
$tags = [];
try { $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC); } catch (\PDOException $e) {}
try { $collections = $pdo->query("SELECT id, title AS name FROM collections ORDER BY title")->fetchAll(PDO::FETCH_ASSOC); } catch (\PDOException $e) {}
try { $tags = $pdo->query("SELECT id, name FROM tags ORDER BY name")->fetchAll(PDO::FETCH_ASSOC); } catch (\PDOException $e) {}

// Items available for manual exhibitions (bounded)
$itemPool = [];
try {
    $itemPool = $pdo->query("SELECT id, reg_number, title FROM items WHERE is_visible = 1 ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {}

// Existing exhibitions + their manual item ids
$exhibitions = [];
try { $exhibitions = $pdo->query("SELECT * FROM museum_exhibitions ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC); } catch (\PDOException $e) {}

$manualMap = [];
try {
    $rows = $pdo->query("SELECT exhibition_id, item_id FROM museum_exhibition_items ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) { $manualMap[(int) $r['exhibition_id']][] = (int) $r['item_id']; }
} catch (\PDOException $e) {}

$optionsJson = json_encode([
    'category'   => $categories,
    'collection' => $collections,
    'tag'        => $tags,
], JSON_UNESCAPED_SLASHES);

$sourceLabel = function ($ex) use ($categories, $collections, $tags) {
    $find = function ($list, $id) {
        foreach ($list as $o) { if ((int) $o['id'] === (int) $id) return $o['name']; }
        return '#' . $id;
    };
    switch ($ex['source_type']) {
        case 'category':   return 'Category · ' . $find($categories, $ex['source_id']);
        case 'collection': return 'Collection · ' . $find($collections, $ex['source_id']);
        case 'tag':        return 'Tag · ' . $find($tags, $ex['source_id']);
        case 'manual':     return 'Hand-picked items';
    }
    return $ex['source_type'];
};
?>

<div class="max-w-6xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">Virtual Museum</h1>
            <p class="text-sm text-slate-400 mt-1">Build walkable 3D exhibitions from your collection.</p>
        </div>
        <button id="vm-new-btn" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">+ New Exhibition</button>
    </div>

    <!-- List -->
    <div class="bg-slate-800/60 border border-slate-700 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-900/60 text-slate-400 text-xs uppercase tracking-wider">
                <tr>
                    <th class="text-left px-4 py-3">Title</th>
                    <th class="text-left px-4 py-3">Source</th>
                    <th class="text-left px-4 py-3">Theme</th>
                    <th class="text-left px-4 py-3">Status</th>
                    <th class="text-right px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/70">
                <?php if (empty($exhibitions)): ?>
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No exhibitions yet. Create your first one.</td></tr>
                <?php else: foreach ($exhibitions as $ex): ?>
                    <tr class="text-slate-200 hover:bg-slate-700/30"
                        data-ex='<?= htmlspecialchars(json_encode([
                            'id' => (int) $ex['id'],
                            'title' => $ex['title'],
                            'description' => $ex['description'],
                            'source_type' => $ex['source_type'],
                            'source_id' => $ex['source_id'],
                            'item_limit' => (int) $ex['item_limit'],
                            'theme' => $ex['theme'],
                            'is_published' => (int) $ex['is_published'],
                            'items' => $manualMap[(int) $ex['id']] ?? [],
                        ], JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>'>
                        <td class="px-4 py-3 font-semibold"><?= htmlspecialchars($ex['title']) ?></td>
                        <td class="px-4 py-3 text-slate-400"><?= htmlspecialchars($sourceLabel($ex)) ?></td>
                        <td class="px-4 py-3 text-slate-400"><?= htmlspecialchars(str_replace('_', ' ', $ex['theme'])) ?></td>
                        <td class="px-4 py-3">
                            <?php if ((int) $ex['is_published'] === 1): ?>
                                <span class="px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 text-xs font-semibold">Published</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-full bg-slate-500/15 text-slate-400 text-xs font-semibold">Draft</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="<?= SITE_URL ?>/museum/<?= rawurlencode($ex['slug']) ?>" target="_blank" class="text-sky-400 hover:text-sky-300 mr-3">View</a>
                            <button class="vm-edit text-indigo-400 hover:text-indigo-300 mr-3">Edit</button>
                            <button class="vm-delete text-rose-400 hover:text-rose-300" data-id="<?= (int) $ex['id'] ?>" data-title="<?= htmlspecialchars($ex['title'], ENT_QUOTES) ?>">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Editor modal -->
<div id="vm-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4">
    <div class="bg-slate-800 border border-slate-600 rounded-2xl w-full max-w-2xl max-h-[92vh] overflow-y-auto shadow-2xl">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700">
            <h2 id="vm-modal-title" class="text-lg font-bold text-white">New Exhibition</h2>
            <button id="vm-close" class="text-slate-400 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6 space-y-5">
            <input type="hidden" id="vm-id" value="0">

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Title</label>
                <input id="vm-title" type="text" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-600 text-white focus:border-indigo-500 outline-none" placeholder="e.g. Highlights of Fine Art">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Description</label>
                <textarea id="vm-description" rows="2" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-600 text-white focus:border-indigo-500 outline-none" placeholder="Shown on the museum landing page."></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Items come from</label>
                    <select id="vm-source-type" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-600 text-white outline-none">
                        <option value="category">A category</option>
                        <option value="collection">A curated collection</option>
                        <option value="tag">A tag</option>
                        <option value="manual">Hand-picked items</option>
                    </select>
                </div>
                <div id="vm-source-wrap">
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Source</label>
                    <select id="vm-source-id" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-600 text-white outline-none"></select>
                </div>
            </div>

            <div id="vm-manual-wrap" class="hidden">
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Pick items <span class="text-slate-500">(Ctrl/Cmd-click for multiple)</span></label>
                <select id="vm-item-ids" multiple size="10" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-600 text-white outline-none text-sm">
                    <?php foreach ($itemPool as $it): ?>
                        <option value="<?= (int) $it['id'] ?>"><?= htmlspecialchars(($it['reg_number'] ? $it['reg_number'] . ' — ' : '') . $it['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Max items in room</label>
                    <input id="vm-item-limit" type="number" min="1" max="100" value="40" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-600 text-white outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Room theme</label>
                    <select id="vm-theme" class="w-full px-3 py-2 rounded-lg bg-slate-900 border border-slate-600 text-white outline-none">
                        <option value="classic">Classic (warm gallery)</option>
                        <option value="dark">Dark (spotlit)</option>
                        <option value="white_cube">White cube (modern)</option>
                    </select>
                </div>
            </div>

            <label class="flex items-center gap-2.5 cursor-pointer">
                <input id="vm-published" type="checkbox" class="w-4 h-4 rounded accent-indigo-500">
                <span class="text-sm text-slate-300">Published (visible to visitors)</span>
            </label>

            <div id="vm-error" class="hidden text-sm text-rose-400"></div>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-700">
            <button id="vm-cancel" class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm">Cancel</button>
            <button id="vm-save" class="px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">Save Exhibition</button>
        </div>
    </div>
</div>

<script>
(function () {
    const OPTIONS = <?= $optionsJson ?>;
    const CSRF = <?= json_encode($csrf) ?>;
    const AJAX = <?= json_encode(SITE_URL . '/admin/ajax.php') ?>;

    const $ = (id) => document.getElementById(id);
    const modal = $('vm-modal');

    function openModal() { modal.classList.remove('hidden'); modal.classList.add('flex'); }
    function closeModal() { modal.classList.add('hidden'); modal.classList.remove('flex'); $('vm-error').classList.add('hidden'); }

    function populateSources(type, selectedId) {
        const sel = $('vm-source-id');
        sel.innerHTML = '';
        (OPTIONS[type] || []).forEach((o) => {
            const opt = document.createElement('option');
            opt.value = o.id; opt.textContent = o.name;
            if (String(o.id) === String(selectedId)) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    function onTypeChange(selectedId) {
        const type = $('vm-source-type').value;
        if (type === 'manual') {
            $('vm-source-wrap').classList.add('hidden');
            $('vm-manual-wrap').classList.remove('hidden');
        } else {
            $('vm-source-wrap').classList.remove('hidden');
            $('vm-manual-wrap').classList.add('hidden');
            populateSources(type, selectedId);
        }
    }

    $('vm-source-type').addEventListener('change', () => onTypeChange(null));

    $('vm-new-btn').addEventListener('click', () => {
        $('vm-modal-title').textContent = 'New Exhibition';
        $('vm-id').value = '0';
        $('vm-title').value = '';
        $('vm-description').value = '';
        $('vm-source-type').value = 'category';
        $('vm-item-limit').value = '40';
        $('vm-theme').value = 'classic';
        $('vm-published').checked = false;
        Array.from($('vm-item-ids').options).forEach((o) => o.selected = false);
        onTypeChange(null);
        openModal();
    });

    document.querySelectorAll('.vm-edit').forEach((btn) => {
        btn.addEventListener('click', () => {
            const ex = JSON.parse(btn.closest('tr').dataset.ex);
            $('vm-modal-title').textContent = 'Edit Exhibition';
            $('vm-id').value = ex.id;
            $('vm-title').value = ex.title || '';
            $('vm-description').value = ex.description || '';
            $('vm-source-type').value = ex.source_type;
            $('vm-item-limit').value = ex.item_limit || 40;
            $('vm-theme').value = ex.theme || 'classic';
            $('vm-published').checked = ex.is_published == 1;
            onTypeChange(ex.source_id);
            const selected = new Set((ex.items || []).map(String));
            Array.from($('vm-item-ids').options).forEach((o) => { o.selected = selected.has(o.value); });
            openModal();
        });
    });

    document.querySelectorAll('.vm-delete').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!confirm('Delete exhibition "' + btn.dataset.title + '"? This cannot be undone.')) return;
            const body = new URLSearchParams();
            body.set('action', 'museum_delete');
            body.set('csrf_token', CSRF);
            body.set('id', btn.dataset.id);
            fetch(AJAX, { method: 'POST', body, headers: { 'X-CSRF-Token': CSRF } })
                .then((r) => r.json())
                .then((d) => { if (d.success) location.reload(); else alert(d.error || 'Delete failed.'); })
                .catch(() => alert('Network error.'));
        });
    });

    $('vm-save').addEventListener('click', () => {
        const type = $('vm-source-type').value;
        const body = new URLSearchParams();
        body.set('action', 'museum_save');
        body.set('csrf_token', CSRF);
        body.set('id', $('vm-id').value);
        body.set('title', $('vm-title').value.trim());
        body.set('description', $('vm-description').value.trim());
        body.set('source_type', type);
        body.set('item_limit', $('vm-item-limit').value);
        body.set('theme', $('vm-theme').value);
        if ($('vm-published').checked) body.set('is_published', '1');
        if (type === 'manual') {
            Array.from($('vm-item-ids').selectedOptions).forEach((o) => body.append('item_ids[]', o.value));
        } else {
            body.set('source_id', $('vm-source-id').value);
        }

        const errEl = $('vm-error');
        errEl.classList.add('hidden');
        fetch(AJAX, { method: 'POST', body, headers: { 'X-CSRF-Token': CSRF } })
            .then((r) => r.json())
            .then((d) => {
                if (d.success) { location.reload(); }
                else { errEl.textContent = d.error || 'Save failed.'; errEl.classList.remove('hidden'); }
            })
            .catch(() => { errEl.textContent = 'Network error.'; errEl.classList.remove('hidden'); });
    });

    $('vm-close').addEventListener('click', closeModal);
    $('vm-cancel').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    onTypeChange(null);
})();
</script>
