<?php
require_once __DIR__ . '/PeopleSupport.php';
$action = in_array($_GET['action'] ?? '', ['add', 'edit'], true) ? $_GET['action'] : 'list';
$personId = max(0, (int)($_GET['id'] ?? 0));
$pe = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$formErrors = $GLOBALS['people_form_errors'] ?? [];
$person = null;
$linkedItems = [];
if ($personId > 0 && $action === 'edit') {
    $stmt = $this->pdo->prepare('SELECT * FROM people WHERE id = ?');
    $stmt->execute([$personId]);
    $person = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$person) { $formErrors[] = 'This biography no longer exists.'; $action = 'list'; }
}
if ($action !== 'list' && isset($GLOBALS['people_form_values'])) $person = array_merge($person ?: [], $GLOBALS['people_form_values']);
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$visibility = in_array($_GET['visibility'] ?? '', ['public', 'private'], true) ? $_GET['visibility'] : '';
$page = max(1, (int)($_GET['p'] ?? 1));
$people = [];
$total = 0;
if ($action === 'list') {
    $where = [];
    $params = [];
    if ($q !== '') { $where[] = '(p.name LIKE ? OR p.slug LIKE ? OR p.short_description LIKE ?)'; $params = array_fill(0, 3, '%' . substr($q, 0, 255) . '%'); }
    if ($visibility !== '') { $where[] = 'p.is_public = ?'; $params[] = $visibility === 'public' ? 1 : 0; }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM people p' . $whereSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $page = min($page, max(1, (int)ceil($total / 25)));
    $offset = ($page - 1) * 25;
    $stmt = $this->pdo->prepare('SELECT p.*, (SELECT COUNT(DISTINCT ip.item_id) FROM item_people ip JOIN items i ON i.id = ip.item_id WHERE ip.person_id = p.id) AS linked_count FROM people p' . $whereSql . ' ORDER BY p.name ASC LIMIT 25 OFFSET ' . $offset);
    $stmt->execute($params);
    $people = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$csrfToken = ensureCsrfToken();
$msg = $_GET['msg'] ?? '';
?>
<style>
.people-workspace{max-width:1200px;margin:auto;padding:24px}.people-workspace .people-panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px}.people-workspace label{display:block;font-size:14px;font-weight:600;color:#334155;margin-bottom:6px}.people-workspace input:not([type=checkbox]):not([type=file]),.people-workspace textarea,.people-workspace select{width:100%;border:1px solid #cbd5e1;border-radius:8px;background:#fff;padding:10px 12px;color:#0f172a;font-size:14px}.people-workspace textarea{resize:vertical;line-height:1.7}.people-workspace input:focus-visible,.people-workspace textarea:focus-visible,.people-workspace select:focus-visible,.people-workspace button:focus-visible,.people-workspace a:focus-visible{outline:3px solid #93c5fd;outline-offset:2px}.people-workspace .people-button{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:9px 14px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;color:#334155;font-size:14px;font-weight:600;cursor:pointer}.people-workspace .people-primary{background:#2563eb;border-color:#2563eb;color:#fff}.people-workspace .people-button:disabled{opacity:.6;cursor:wait}.people-workspace .people-help{color:#64748b;font-size:13px;line-height:1.5;margin-top:6px}.people-workspace .people-stack{display:grid;gap:20px}.people-workspace .people-table{width:100%;border-collapse:collapse;text-align:left;font-size:14px}.people-table th,.people-table td{padding:16px;border-bottom:1px solid #e2e8f0}.people-table th{background:#f8fafc;color:#475569;font-size:12px}.people-photo{width:160px;height:180px;object-fit:contain;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px}.people-status{padding:12px 16px;border:1px solid #cbd5e1;background:#f8fafc;border-radius:8px;font-size:14px}.people-error{border-color:#fecaca;background:#fef2f2;color:#991b1b}.people-success{border-color:#bbf7d0;background:#f0fdf4;color:#166534}.people-workspace .people-link-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px;border:1px solid #e2e8f0;border-radius:8px}.people-form-columns>*{min-width:0}.people-stack>*{min-width:0}.people-help{overflow-wrap:anywhere}.people-workspace input[type=file]{width:100%;max-width:100%;min-width:0;font-size:13px}.people-form-columns{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,1fr);gap:24px}.people-two-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.people-heading{display:flex;justify-content:space-between;align-items:start;gap:16px;margin-bottom:24px}.people-filters{display:flex;align-items:end;gap:12px;flex-wrap:wrap;margin-bottom:20px}.people-filters>div:first-child{flex:1;min-width:180px}.people-facts-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.5fr) 40px;gap:8px}.people-facts-row input{min-width:0}#people-search-results button{width:100%;text-align:left;display:block;padding:12px;border-bottom:1px solid #e2e8f0;background:#fff}#people-search-results button:hover,#people-search-results button:focus-visible{background:#eff6ff}@media(max-width:800px){.people-workspace{padding:16px}.people-form-columns{grid-template-columns:1fr}.people-workspace .people-panel{padding:16px}.people-heading{flex-direction:column}.people-two-columns{grid-template-columns:1fr}}
</style>
<div class="people-workspace">
    <div class="people-heading">
        <div><h1 class="text-2xl font-bold text-slate-900">People &amp; Biographies</h1><p class="people-help">Create historical profiles and connect their stories to collection items.</p></div>
        <?php if ($action === 'list'): ?><a href="?m=people&amp;action=add" class="people-button people-primary">Add biography</a>
        <?php else: ?><a href="?m=people" class="people-button">Back to biographies</a><?php endif; ?>
    </div>
    <?php if ($formErrors): ?><div class="people-status people-error mb-6" role="alert"><p class="font-semibold">Please review the following:</p><ul class="list-disc pl-5"><?php foreach ($formErrors as $error): ?><li><?= $pe($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($msg === 'saved'): ?><p class="people-status people-success mb-6" role="status">Biography saved successfully.</p><?php elseif ($msg === 'deleted'): ?><p class="people-status people-success mb-6" role="status">Biography deleted. Its collection item links have been removed.</p><?php endif; ?>
    <?php if ($action === 'list'): ?>
        <form method="get" class="people-filters"><input type="hidden" name="m" value="people"><div><label for="people-query">Search biographies</label><input id="people-query" type="search" name="q" value="<?= $pe($q) ?>" placeholder="Name, slug or description"></div><div><label for="people-visibility">Visibility</label><select id="people-visibility" name="visibility"><option value="">All profiles</option><option value="public" <?= $visibility === 'public' ? 'selected' : '' ?>>Public</option><option value="private" <?= $visibility === 'private' ? 'selected' : '' ?>>Private</option></select></div><button class="people-button" type="submit">Filter</button><?php if ($q !== '' || $visibility !== ''): ?><a class="people-button" href="?m=people">Clear</a><?php endif; ?></form>
        <p class="people-help mb-3"><?= number_format($total) ?> <?= $total === 1 ? 'biography' : 'biographies' ?><?= $q !== '' || $visibility !== '' ? ' matching your filters' : '' ?></p>
        <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto"><table class="people-table"><thead><tr><th scope="col">Person</th><th scope="col">Visibility</th><th scope="col">Linked items</th><th scope="col">Actions</th></tr></thead><tbody>
        <?php if (!$people): ?><tr><td colspan="4" class="text-center py-12 text-slate-500"><?= $q !== '' || $visibility !== '' ? 'No profiles match these filters. Try a different search.' : 'Start by adding a biography, then link it to items in your collection.' ?></td></tr><?php endif; ?>
        <?php foreach ($people as $p): ?><tr><td><div class="flex items-center gap-3"><?php if ($p['profile_image']): ?><img src="<?= $pe(PeopleSupport::imageUrl($p['profile_image'])) ?>" alt="" width="48" height="56" style="width:48px;height:56px;object-fit:contain;background:#f8fafc" loading="lazy"><?php endif; ?><div><a class="font-semibold text-blue-700" href="?m=people&amp;action=edit&amp;id=<?= (int)$p['id'] ?>"><?= $pe($p['name']) ?></a><p class="people-help"><?= $pe($p['short_description'] ?: $p['slug']) ?></p></div></div></td><td><span class="<?= $p['is_public'] ? 'text-green-700' : 'text-slate-600' ?>"><?= $p['is_public'] ? 'Public' : 'Private' ?></span></td><td><?= (int)$p['linked_count'] ?></td><td><div class="flex gap-2"><a class="people-button" href="?m=people&amp;action=edit&amp;id=<?= (int)$p['id'] ?>">Edit<span class="sr-only"> <?= $pe($p['name']) ?></span></a><form method="post" action="?m=people&amp;action=delete&amp;id=<?= (int)$p['id'] ?>" onsubmit="return confirm('Delete this biography and its item links? Collection items will be kept.')"><input type="hidden" name="csrf_token" value="<?= $pe($csrfToken) ?>"><button class="people-button" type="submit" style="color:#b91c1c">Delete<span class="sr-only"> <?= $pe($p['name']) ?></span></button></form></div></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if ($total > 25): ?><nav class="flex justify-between items-center mt-5" aria-label="Biographies pages"><?php $pageArgs = ['m' => 'people', 'q' => $q, 'visibility' => $visibility]; ?><?php if ($page > 1): ?><a class="people-button" href="?<?= $pe(http_build_query($pageArgs + ['p' => $page - 1])) ?>">Previous</a><?php else: ?><span></span><?php endif; ?><span class="people-help">Page <?= $page ?> of <?= (int)ceil($total / 25) ?></span><?php if ($page * 25 < $total): ?><a class="people-button" href="?<?= $pe(http_build_query($pageArgs + ['p' => $page + 1])) ?>">Next</a><?php else: ?><span></span><?php endif; ?></nav><?php endif; ?>
    <?php else: ?>
        <form id="people-editor" method="post" enctype="multipart/form-data" class="people-form-columns">
            <input type="hidden" name="csrf_token" value="<?= $pe($csrfToken) ?>"><input type="hidden" name="save_person" value="1">
            <div class="people-stack"><section class="people-panel people-stack"><h2 class="text-lg font-bold">Biographical information</h2>
                <div class="people-two-columns"><div><label for="person-name">Full name <span aria-hidden="true">*</span></label><input id="person-name" name="name" maxlength="255" required value="<?= $pe($person['name'] ?? '') ?>"></div><div><label for="person-slug">URL slug <span aria-hidden="true">*</span></label><input id="person-slug" name="slug" maxlength="255" pattern="[a-zA-Z0-9]+([-_][a-zA-Z0-9]+)*" required value="<?= $pe($person['slug'] ?? '') ?>" aria-describedby="slug-help"><p class="people-help" id="slug-help">Letters, numbers, hyphens and underscores. Keep an existing slug to preserve shared links.</p></div></div>
                <div class="people-two-columns"><div><label for="person-birth">Birth date</label><input id="person-birth" name="birth_date" maxlength="100" value="<?= $pe($person['birth_date'] ?? '') ?>" placeholder="For example, 1850 or 12 May 1850"></div><div><label for="person-death">Death date</label><input id="person-death" name="death_date" maxlength="100" value="<?= $pe($person['death_date'] ?? '') ?>"><p class="people-help">Leave empty when not applicable or unknown.</p></div></div>
                <div><label for="person-summary">Short description</label><input id="person-summary" name="short_description" maxlength="500" value="<?= $pe($person['short_description'] ?? '') ?>" aria-describedby="summary-help"><p class="people-help" id="summary-help">A one-line introduction, up to 500 characters.</p></div>
                <div><label for="person-biography">Biography</label><textarea id="person-biography" name="biography" rows="16" maxlength="200000" aria-describedby="biography-help"><?= $pe($person['biography'] ?? '') ?></textarea><p class="people-help" id="biography-help">Write in plain text. Paragraphs and line breaks are preserved.</p></div>
            </section>
            <?php if ($personId > 0): ?><section class="people-panel people-stack"><h2 class="text-lg font-bold">Linked collection items</h2><div><label for="item-search">Find an item to link</label><input id="item-search" type="search" autocomplete="off" placeholder="Search title or registration number" aria-controls="people-search-results"><div id="people-search-results" class="border border-slate-200 rounded-lg mt-2" hidden></div></div><p id="people-link-feedback" role="status" aria-live="polite" class="people-help"></p><div id="linked-items-list" class="people-stack" aria-busy="true"><p class="people-help">Loading linked items...</p></div></section><?php else: ?><p class="people-status">Save this biography to start linking collection items.</p><?php endif; ?>
            </div>
            <aside class="people-stack" style="align-content:start"><section class="people-panel people-stack"><h2 class="text-lg font-bold">Publication</h2><div class="flex items-center gap-3"><input type="checkbox" name="is_public" id="is-public" value="1" <?= ($person['is_public'] ?? 1) ? 'checked' : '' ?> style="width:20px;height:20px"><label for="is-public" style="margin:0">Display publicly</label></div><p class="people-help">Private biographies are excluded from public browsing, search, and related profiles.</p><button id="people-save" class="people-button people-primary" type="submit">Save biography</button><?php if ($personId > 0 && !empty($person['is_public'])): ?><a class="people-button" href="<?= $pe(SITE_URL . '/person/' . rawurlencode($person['slug'])) ?>" target="_blank" rel="noopener">View public biography</a><?php endif; ?></section>
            <section class="people-panel people-stack"><h2 class="text-lg font-bold">Profile photo</h2><img id="photo-preview" class="people-photo" src="<?= $pe(PeopleSupport::imageUrl($person['profile_image'] ?? null)) ?>" alt="Profile photo preview" <?= empty($person['profile_image']) ? 'hidden' : '' ?>><div><label for="photo-input">Choose a photo</label><input id="photo-input" type="file" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="photo-help"><p id="photo-help" class="people-help">JPEG, PNG, GIF or WebP, up to 20 MiB. The complete original is preserved and previews fit without cropping.</p><p id="photo-error" class="people-help text-red-700" role="alert"></p></div><?php if (!empty($person['profile_image'])): ?><div class="flex gap-2 items-center"><input type="checkbox" id="remove-photo" name="remove_photo" value="1"><label for="remove-photo" style="margin:0">Remove current photo</label></div><?php endif; ?></section>
            <section class="people-panel people-stack"><div class="flex justify-between items-center gap-3"><h2 class="font-bold">Infobox facts</h2><button class="people-button" type="button" id="add-fact">Add fact</button></div><div id="infobox-rows" class="people-stack"><?php $infobox = json_decode($person['infobox_data'] ?? '[]', true); if (!is_array($infobox)) $infobox = []; foreach ($infobox as $row): ?><div class="people-facts-row"><input name="info_key[]" aria-label="Fact label" maxlength="200" value="<?= $pe($row['label'] ?? '') ?>" placeholder="Label"><input name="info_val[]" aria-label="Fact value" maxlength="2000" value="<?= $pe($row['value'] ?? '') ?>" placeholder="Value"><button class="people-button remove-fact" type="button" aria-label="Remove fact">&times;</button></div><?php endforeach; ?></div><p class="people-help">Add occupation, nationality, sources, or other relevant facts.</p></section></aside>
        </form>
        <script>
        (() => {
            const editor = document.getElementById('people-editor');
            const slug = document.getElementById('person-slug');
            let slugTouched = <?= $personId > 0 || !empty($person['slug']) ? 'true' : 'false' ?>;
            slug.addEventListener('input', () => { slugTouched = true; });
            document.getElementById('person-name').addEventListener('input', event => { if (!slugTouched) slug.value = event.target.value.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); });
            editor.addEventListener('submit', () => { const button = document.getElementById('people-save'); button.disabled = true; button.textContent = 'Saving...'; });
            let photoUrl;
            document.getElementById('photo-input').addEventListener('change', event => {
                const file = event.target.files[0]; const error = document.getElementById('photo-error'); error.textContent = '';
                if (!file) return;
                if (!['image/jpeg','image/png','image/gif','image/webp'].includes(file.type) || file.size > 20 * 1024 * 1024) { error.textContent = 'Choose a JPEG, PNG, GIF or WebP image up to 20 MiB.'; event.target.value = ''; return; }
                if (photoUrl) URL.revokeObjectURL(photoUrl);
                photoUrl = URL.createObjectURL(file); const preview = document.getElementById('photo-preview'); preview.src = photoUrl; preview.hidden = false;
                const remove = document.getElementById('remove-photo'); if (remove) remove.checked = false;
            });
            const facts = document.getElementById('infobox-rows');
            facts.addEventListener('click', event => { const button = event.target.closest('.remove-fact'); if (button) button.parentElement.remove(); });
            document.getElementById('add-fact').addEventListener('click', () => {
                if (facts.children.length >= 50) return;
                const row = document.createElement('div'); row.className = 'people-facts-row';
                row.innerHTML = '<input name="info_key[]" aria-label="Fact label" maxlength="200" placeholder="Label"><input name="info_val[]" aria-label="Fact value" maxlength="2000" placeholder="Value"><button class="people-button remove-fact" type="button" aria-label="Remove fact">&times;</button>';
                facts.append(row); row.querySelector('input').focus();
            });
            const search = document.getElementById('item-search'); if (!search) return;
            const results = document.getElementById('people-search-results'); const list = document.getElementById('linked-items-list'); const feedback = document.getElementById('people-link-feedback');
            const csrf = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const base = '?m=people&id=<?= $personId ?>&action=';
            async function request(action, itemId) {
                const options = {};
                if (itemId !== undefined) { const form = new FormData(); form.append('item_id', itemId); form.append('csrf_token', csrf); options.method = 'POST'; options.body = form; }
                const response = await fetch(base + action, options); const data = await response.json();
                if (!response.ok || data.error) throw new Error(data.error || 'Unable to load collection items.'); return data;
            }
            async function refresh() {
                list.setAttribute('aria-busy', 'true');
                try {
                    const data = await request('get_linked_items_ajax'); list.replaceChildren();
                    if (!data.data.length) { const empty = document.createElement('p'); empty.className = 'people-help'; empty.textContent = 'No items linked yet. Use the search above to add one.'; list.append(empty); }
                    data.data.forEach(item => { const row = document.createElement('div'); row.className = 'people-link-row'; const info = document.createElement('div'); const title = document.createElement('a'); title.href = <?= json_encode(SITE_URL . '/admin/edit_item.php?id=', JSON_HEX_TAG) ?> + Number(item.id); title.className = 'font-semibold text-blue-700'; title.textContent = item.title; const meta = document.createElement('p'); meta.className = 'people-help'; meta.textContent = item.reg_number || ''; info.append(title, meta); const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'people-button'; remove.textContent = 'Unlink'; remove.setAttribute('aria-label', 'Unlink ' + item.title); remove.addEventListener('click', async () => { remove.disabled = true; try { await request('unlink_item_ajax', Number(item.id)); feedback.textContent = 'Item unlinked. The collection record was kept.'; await refresh(); } catch(error) { feedback.textContent = error.message; remove.disabled = false; } }); row.append(info, remove); list.append(row); });
                } catch(error) { list.textContent = error.message; } finally { list.setAttribute('aria-busy', 'false'); }
            }
            let timer; let searchController;
            search.addEventListener('input', () => {
                clearTimeout(timer); if (searchController) searchController.abort(); results.hidden = true;
                const q = search.value.trim(); if (q.length < 2) { feedback.textContent = ''; return; }
                timer = setTimeout(async () => { searchController = new AbortController(); feedback.textContent = 'Searching...';
                    try { const response = await fetch(base + 'search_items_ajax&exclude_person=<?= $personId ?>&q=' + encodeURIComponent(q), {signal:searchController.signal}); const data = await response.json(); if (!response.ok) throw new Error(data.error || 'Search failed.'); if (search.value.trim() !== q) return; results.replaceChildren(); feedback.textContent = data.data.length ? 'Choose an item to link.' : 'No unlinked items match this search.'; data.data.forEach(item => { const button = document.createElement('button'); button.type = 'button'; button.textContent = item.title + (item.reg_number ? ' (' + item.reg_number + ')' : ''); button.addEventListener('click', async () => { button.disabled = true; try { await request('link_item_ajax', Number(item.id)); results.hidden = true; search.value = ''; feedback.textContent = 'Item linked successfully.'; await refresh(); } catch(error) { feedback.textContent = error.message; button.disabled = false; } }); results.append(button); }); results.hidden = !data.data.length; } catch(error) { if (error.name !== 'AbortError') feedback.textContent = error.message; }
                }, 250);
            });
            refresh();
        })();
        </script>
    <?php endif; ?>
</div>
