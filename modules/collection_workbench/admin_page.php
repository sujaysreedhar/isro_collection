<?php
if (empty($_SESSION['admin_id'])) { http_response_code(403); return; }
$h = static fn($value) => htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$baseUrl = SITE_URL . '/admin/module_page.php?m=collection_workbench';
$tab = is_string($_GET['tab'] ?? null) && in_array($_GET['tab'], ['storage', 'completeness', 'bulk'], true) ? $_GET['tab'] : 'storage';
$fieldLabels = ['category_id' => 'Categories (replace all)', 'is_visible' => 'Public visibility', 'album' => 'Album', 'page_number' => 'Page', 'sleeve' => 'Sleeve', 'box_id' => 'Box', 'shelf' => 'Shelf', 'location_notes' => 'Location notes', 'private_notes' => 'Private notes'];
?>
<style>
.cwb{--cw-border:#dce1e8;--cw-muted:#526174;--cw-blue:#175fbd;color:#172233;max-width:1440px;margin:0 auto}.cwb *{box-sizing:border-box}.cwb h1{font-size:28px;font-weight:750;letter-spacing:-.03em}.cwb h2{font-size:19px;font-weight:700;margin:0 0 12px}.cwb p{line-height:1.6}.cwb .muted{color:var(--cw-muted);font-size:14px}.cwb header{margin-bottom:24px}.cwb .tabs{display:flex;flex-wrap:wrap;gap:8px;border-bottom:1px solid var(--cw-border);padding-bottom:12px;margin-bottom:24px}.cwb .tabs a{padding:9px 14px;border-radius:6px;font-size:14px;font-weight:650;color:#415067}.cwb .tabs a[aria-current=page]{background:#eaf2fc;color:#1558a6}.cwb .panel{background:white;border:1px solid var(--cw-border);border-radius:10px;padding:24px;margin-bottom:24px}.cwb .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.cwb .wide{grid-column:1/-1}.cwb label{display:block;font-size:14px;font-weight:600;margin-bottom:6px}.cwb input:not([type=checkbox]),.cwb select,.cwb textarea{width:100%;border:1px solid #bac4d1;border-radius:6px;padding:9px 11px;color:#172233;background:#fff;font:inherit;font-size:14px;min-height:40px}.cwb input[type=checkbox]{width:18px;height:18px;vertical-align:middle;accent-color:var(--cw-blue)}.cwb :is(input,select,textarea,button,a):focus-visible{outline:3px solid #3786ef;outline-offset:3px}.cwb .btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:9px 15px;border:1px solid #bbc5d2;border-radius:6px;background:#fff;color:#253448;font-size:14px;font-weight:650;cursor:pointer;text-decoration:none;white-space:nowrap}.cwb .btn:hover{background:#f1f5f9}.cwb .primary{color:#fff;background:var(--cw-blue);border-color:var(--cw-blue)}.cwb .primary:hover{background:#124e9e}.cwb .danger{color:#ad2d32;border-color:#efc7c9}.cwb .actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-top:18px}.cwb .table-wrap{overflow:auto;border:1px solid var(--cw-border);border-radius:8px}.cwb table{border-collapse:collapse;width:100%;font-size:14px;text-align:left}.cwb th{background:#f4f6f9;color:#526174;font-size:12px;font-weight:700;padding:12px 14px;white-space:nowrap}.cwb td{padding:13px 14px;border-top:1px solid #e9edf2;vertical-align:top}.cwb tbody tr:hover{background:#f8fafc}.cwb td a:not(.btn){color:var(--cw-blue);text-decoration:underline;text-underline-offset:3px}.cwb .notice{padding:14px 18px;border:1px solid;border-radius:8px;margin-bottom:20px;font-size:14px}.cwb .error{background:#fff1f1;border-color:#f2bfc3;color:#92282e}.cwb .success{background:#eefaf3;border-color:#b2dec2;color:#1b643b}.cwb .info{background:#eff5ff;border-color:#c0d7f5;color:#24517d}.cwb .badge{display:inline-block;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:650;background:#eef3f9;color:#425775}.cwb .missing{background:#fff6df;color:#895c09}.cwb .owned{background:#e7f5eb;color:#276141}.cwb .checklist-row{display:flex;gap:20px;justify-content:space-between;align-items:center;padding:18px 0;border-bottom:1px solid #e9edf2}.cwb .checklist-row:last-child{border:0}.cwb .checklist-row h3{font-size:16px;font-weight:650;margin-bottom:5px}.cwb .progress{font-size:24px;font-weight:750;white-space:nowrap}.cwb .progress small{font-size:13px;color:var(--cw-muted);font-weight:400}.cwb .entry{padding:20px 0;border-bottom:1px solid var(--cw-border)}.cwb .entry:last-child{border:0}.cwb .entry.missing-entry{border-left:3px solid #dba32c;padding-left:16px}.cwb .entry-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px}.cwb .change{padding:14px;border:1px solid var(--cw-border);border-radius:6px}.cwb .change>label{display:flex;gap:9px;align-items:center}.cwb .search{display:flex;align-items:flex-end;gap:12px}.cwb .search>div{flex:1}.cwb .before{color:#7d4048}.cwb .after{color:#1e693d}.cwb .pagination{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:16px}.cwb .break{overflow-wrap:anywhere;white-space:pre-wrap}.cwb .empty{padding:32px;text-align:center;color:var(--cw-muted)}
@media(max-width:720px){.cwb .panel{padding:16px}.cwb .grid,.cwb .entry-grid{grid-template-columns:1fr}.cwb .search{align-items:stretch;flex-direction:column}.cwb h1{font-size:24px}.cwb .checklist-row{align-items:flex-start;gap:12px}.cwb .progress{font-size:20px}}
</style>
<div class="cwb">
<header><h1>Collection Workbench</h1><p class="muted">Know what you own, what is missing, and where to find it.</p></header>
<nav class="tabs" aria-label="Collection workbench">
<?php foreach (['storage' => 'Storage & private details', 'completeness' => 'Collection completeness', 'bulk' => 'Bulk editing'] as $key => $label): ?>
<a href="<?= $h($baseUrl . '&tab=' . $key) ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>><?= $h($label) ?></a>
<?php endforeach; ?>
</nav>
<?php if ($error): ?><div class="notice error" role="alert"><?= $h($error) ?></div><?php endif; ?>
<?php if ($success || isset($_GET['saved'])): ?><div class="notice success" role="status"><?= $h($success ?: 'Checklist created. Add the items you expect to collect below.') ?></div><?php endif; ?>
<?php if (!$inventory->ready()): ?>
<div class="notice info"><h2>Database update required</h2><p>Apply the documented changes in <code>update.sql</code> using the database updater, then reload this page. Existing collection data is retained.</p><a class="btn" href="<?= $h(SITE_URL . '/update.php') ?>">Open database updater</a></div>
<?php else:
try {
$categories = $inventory->categories();
$categoryNames = [];
foreach ($categories as $category) $categoryNames[(int)$category['id']] = $category['name'];
if ($tab === 'storage' || $tab === 'bulk'):
    $search = is_string($_GET['q'] ?? null) ? $_GET['q'] : '';
    $result = $inventory->search($search, max(1, (int)($_GET['p'] ?? 1)));
    $fromSelection = $tab === 'bulk' && is_string($_GET['ids'] ?? null) && $_GET['ids'] !== '';
    if ($fromSelection) {
        $selectedRows = $inventory->selectedItems(explode(',', $_GET['ids']));
        $result = ['rows' => $selectedRows, 'total' => count($selectedRows), 'page' => 1];
    }
    $itemId = (int)($_GET['item'] ?? 0);
?>
<?php if ($tab === 'storage' && $itemId > 0):
    $details = $inventory->privateDetails($itemId);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'save_private' && $error) $details = array_replace($details, array_intersect_key($_POST, $details));
?>
<section class="panel" aria-labelledby="cw-edit-title">
<h2 id="cw-edit-title"><?= $h($details['reg_number']) ?> · <?= $h($details['title']) ?></h2>
<p class="muted" style="margin-bottom:20px">These fields are private. Public catalogue pages, album pages, and item downloads do not include them.</p>
<form method="post">
<?= Csrf::input() ?><input type="hidden" name="action" value="save_private"><input type="hidden" name="item_id" value="<?= $itemId ?>">
<div class="grid">
<?php foreach (['album' => 'Album', 'page_number' => 'Page', 'sleeve' => 'Sleeve', 'box_id' => 'Box', 'shelf' => 'Shelf'] as $key => $label): ?>
<div><label for="cw-<?= $key ?>"><?= $h($label) ?></label><input id="cw-<?= $key ?>" name="<?= $key ?>" maxlength="<?= $key === 'page_number' ? 50 : 100 ?>" value="<?= $h($details[$key]) ?>"></div>
<?php endforeach; ?>
<div><label for="cw-currency">Currency</label><input id="cw-currency" name="currency" pattern="[A-Za-z]{3}" maxlength="3" required value="<?= $h($details['currency'] ?: 'INR') ?>" placeholder="INR"></div>
<div><label for="cw-purchase-price">Purchase price</label><input id="cw-purchase-price" name="purchase_price" type="number" min="0" max="9999999999999.99" step="0.01" value="<?= $h($details['purchase_price']) ?>"></div>
<div><label for="cw-valuation">Current valuation</label><input id="cw-valuation" name="current_value" type="number" min="0" max="9999999999999.99" step="0.01" value="<?= $h($details['current_value']) ?>"></div>
<div><label for="cw-purchase-date">Purchase date</label><input id="cw-purchase-date" type="date" name="purchase_date" value="<?= $h($details['purchase_date']) ?>"></div>
<div class="wide"><label for="cw-location-notes">Location notes</label><textarea id="cw-location-notes" name="location_notes" maxlength="10000" rows="2"><?= $h($details['location_notes']) ?></textarea></div>
<div class="wide"><label for="cw-private-notes">Private notes</label><textarea id="cw-private-notes" name="private_notes" maxlength="20000" rows="3"><?= $h($details['private_notes']) ?></textarea></div>
</div>
<div class="actions"><button class="btn primary" type="submit">Save private details</button><a class="btn" href="<?= $h(SITE_URL . '/admin/edit_item.php?id=' . $itemId) ?>">Edit catalogue record</a><a class="btn" href="<?= $h($baseUrl) ?>">Close</a></div>
</form></section>
<?php endif; ?>
<?php if ($tab === 'bulk' && !empty($_SESSION['collection_bulk_preview']) && (int)($_SESSION['collection_bulk_preview']['admin_id'] ?? 0) === (int)$_SESSION['admin_id']):
    $preview = $_SESSION['collection_bulk_preview'];
    $display = static function($key, $value) use ($categoryNames) {
        if ($key === 'category_id') return $categoryNames[(int)$value] ?? 'No category';
        if ($key === 'is_visible') return (int)$value ? 'Public' : 'Hidden';
        return ($value === '' || $value === null) ? '(empty)' : (string)$value;
    };
?>
<section class="panel" aria-labelledby="cw-preview-title">
<h2 id="cw-preview-title">Review changes to <?= count($preview['ids']) ?> <?= count($preview['ids']) === 1 ? 'item' : 'items' ?></h2>
<p class="muted" style="margin-bottom:16px">Nothing has been saved. Confirm within 15 minutes. If any reviewed values change, this preview will be rejected.</p>
<div class="table-wrap"><table><thead><tr><th scope="col">Item</th><th scope="col">Field</th><th scope="col">Current value</th><th scope="col">After saving</th></tr></thead><tbody>
<?php foreach ($preview['rows'] as $row): foreach ($preview['changes'] as $key => $value): ?>
<tr><td><?= $h($row['reg_number']) ?><br><?= $h($row['title']) ?></td><td><?= $h($fieldLabels[$key]) ?></td><td class="before break"><?php if ($key === 'category_id'): ?><?= $h(implode(', ', array_map(static fn($id) => $categoryNames[$id] ?? (string)$id, $row['category_ids'])) ?: 'No categories') ?><?php else: ?><?= $h($display($key, $row[$key] ?? null)) ?><?php endif; ?></td><td class="after break"><?= $h($display($key, $value)) ?></td></tr>
<?php endforeach; endforeach; ?>
</tbody></table></div>
<div class="actions"><form method="post"><?= Csrf::input() ?><input type="hidden" name="action" value="bulk_confirm"><input type="hidden" name="preview_token" value="<?= $h($preview['token']) ?>"><button class="btn primary" type="submit">Confirm and save <?= count($preview['ids']) ?> <?= count($preview['ids']) === 1 ? 'item' : 'items' ?></button></form><form method="post"><?= Csrf::input() ?><input type="hidden" name="action" value="bulk_cancel"><button class="btn" type="submit">Cancel preview</button></form></div>
</section>
<?php endif; ?>
<section class="panel">
<h2><?= $tab === 'bulk' ? 'Select items to edit' : 'Find a physical item' ?></h2>
<?php if ($fromSelection): ?>
<div class="notice info">Showing <?= count($result['rows']) ?> valid records selected from Manage Items. Uncheck any record you want to leave unchanged. <a href="<?= $h($baseUrl . '&tab=bulk') ?>" style="text-decoration:underline">Browse all items instead</a>.</div>
<?php else: ?>
<form method="get" class="search" style="margin-bottom:20px">
<input type="hidden" name="m" value="collection_workbench"><input type="hidden" name="tab" value="<?= $h($tab) ?>">
<div><label for="cw-search">Search title, registration number, album, box, shelf or sleeve</label><input id="cw-search" name="q" maxlength="200" value="<?= $h($search) ?>" type="search"></div><button class="btn" type="submit">Search</button>
</form>
<?php endif; ?>
<?php if ($tab === 'bulk'): ?><form method="post"><?= Csrf::input() ?><input type="hidden" name="action" value="bulk_preview"><?php endif; ?>
<div class="table-wrap"><table><thead><tr>
<?php if ($tab === 'bulk'): ?><th scope="col"><input type="checkbox" id="cw-select-all" aria-label="Select all items on this page"></th><?php endif; ?>
<th scope="col">Record</th><th scope="col">Title</th><th scope="col">Album / page</th><th scope="col">Sleeve / box / shelf</th><th scope="col">Visibility</th><?php if ($tab === 'storage'): ?><th scope="col">Action</th><?php endif; ?>
</tr></thead><tbody>
<?php foreach ($result['rows'] as $row): ?>
<tr><?php if ($tab === 'bulk'): ?><td><input type="checkbox" class="cw-item-select" name="item_ids[]" value="<?= (int)$row['id'] ?>" aria-label="<?= $h('Select ' . $row['title']) ?>" <?= $fromSelection ? 'checked' : '' ?>></td><?php endif; ?>
<td><?= $h($row['reg_number']) ?><br><span class="muted">ID <?= (int)$row['id'] ?></span></td><td><?= $h($row['title']) ?></td><td><?= $h($row['album'] ?: 'Unassigned') ?><br><span class="muted"><?= $h($row['page_number'] ? 'Page ' . $row['page_number'] : '') ?></span></td><td><?= $h(implode(' · ', array_filter([$row['sleeve'], $row['box_id'], $row['shelf']])) ?: 'Unassigned') ?></td><td><span class="badge"><?= $row['is_visible'] ? 'Public record' : 'Hidden record' ?></span></td><?php if ($tab === 'storage'): ?><td><a href="<?= $h($baseUrl . '&item=' . (int)$row['id']) ?>">Private details</a></td><?php endif; ?></tr>
<?php endforeach; ?>
<?php if (!$result['rows']): ?><tr><td colspan="7" class="empty">No items match this search. Add collection items or try another search.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="pagination"><span class="muted"><?= (int)$result['total'] ?> items · Page <?= (int)$result['page'] ?> · Up to 100 per page</span>
<?php if ($result['page'] > 1): ?><a class="btn" href="<?= $h($baseUrl . '&tab=' . $tab . '&q=' . rawurlencode($search) . '&p=' . ($result['page'] - 1)) ?>">Previous</a><?php endif; ?>
<?php if ($result['page'] * 100 < $result['total']): ?><a class="btn" href="<?= $h($baseUrl . '&tab=' . $tab . '&q=' . rawurlencode($search) . '&p=' . ($result['page'] + 1)) ?>">Next</a><?php endif; ?>
</div>
<?php if ($tab === 'bulk'): ?>
<h2 style="margin-top:28px">Choose changes</h2><p class="muted" style="margin-bottom:16px">Only checked fields will change. A checked empty field clears that value. Changing categories replaces all categories on each selected item.</p>
<div class="grid">
<?php foreach ($fieldLabels as $key => $label): ?>
<div class="change"><label><input type="checkbox" name="apply[<?= $key ?>]" value="1"> Change <?= $h($label) ?></label>
<?php if ($key === 'category_id'): ?><select name="change[category_id]" aria-label="New category"><option value="">Choose category</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= $h($category['name']) ?></option><?php endforeach; ?></select>
<?php elseif ($key === 'is_visible'): ?><select name="change[is_visible]" aria-label="New public visibility"><option value="1">Public</option><option value="0">Hidden</option></select>
<?php elseif (in_array($key, ['location_notes', 'private_notes'], true)): ?><textarea name="change[<?= $key ?>]" rows="2" maxlength="<?= $key === 'private_notes' ? 20000 : 10000 ?>" aria-label="<?= $h('New ' . $label) ?>"></textarea>
<?php else: ?><input name="change[<?= $key ?>]" maxlength="<?= $key === 'page_number' ? 50 : 100 ?>" aria-label="<?= $h('New ' . $label) ?>">
<?php endif; ?></div>
<?php endforeach; ?>
</div><div class="actions"><button class="btn primary" type="submit">Preview selected changes</button></div></form>
<?php endif; ?>
</section>
<?php elseif ($tab === 'completeness'):
    $dimension = is_string($_GET['dimension'] ?? null) && isset(CollectionInventory::DIMENSIONS[$_GET['dimension']]) ? $_GET['dimension'] : '';
    $checklistId = (int)($_GET['checklist'] ?? 0);
    if ($checklistId > 0):
        $checklist = $inventory->checklist($checklistId);
        $entries = $inventory->entries($checklistId);
        $owned = count(array_filter($entries, static fn($row) => $row['owned_item_id'] !== null));
?>
<section class="panel"><a href="<?= $h($baseUrl . '&tab=completeness') ?>" class="btn" style="margin-bottom:18px">All checklists</a><h2><?= $h($checklist['name']) ?></h2><p class="muted"><?= $h(CollectionInventory::DIMENSIONS[$checklist['dimension']] ?? $checklist['dimension']) ?>: <?= $h($checklist['group_value']) ?></p><p style="margin-top:12px"><strong><?= $owned ?> / <?= count($entries) ?> owned</strong> · <?= count($entries) - $owned ?> missing<?= $entries ? ' · ' . (int)round($owned / count($entries) * 100) . '% complete' : '' ?></p><p class="muted" style="margin-top:8px">Add every expected stamp or cover. Link its collection record ID when acquired; leave it blank while missing. Find IDs in Storage & private details. Hidden items still count as owned in this private admin view.</p>
<?php foreach ($entries as $entry): ?>
<article class="entry <?= $entry['owned_item_id'] === null ? 'missing-entry' : '' ?>"><span class="badge <?= $entry['owned_item_id'] === null ? 'missing' : 'owned' ?>" style="margin-bottom:12px"><?= $entry['owned_item_id'] === null ? 'Missing' : 'Owned' ?></span>
<form method="post"><?= Csrf::input() ?><input type="hidden" name="action" value="save_entry"><input type="hidden" name="checklist_id" value="<?= $checklistId ?>"><input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>"><div class="entry-grid">
<div><label for="entry-label-<?= (int)$entry['id'] ?>">Expected item</label><input id="entry-label-<?= (int)$entry['id'] ?>" name="label" required maxlength="255" value="<?= $h($entry['label']) ?>"></div>
<div><label for="entry-ref-<?= (int)$entry['id'] ?>">Catalogue reference</label><input id="entry-ref-<?= (int)$entry['id'] ?>" name="catalogue_reference" maxlength="120" value="<?= $h($entry['catalogue_reference']) ?>"></div>
<div><label for="entry-item-<?= (int)$entry['id'] ?>">Owned record ID</label><input id="entry-item-<?= (int)$entry['id'] ?>" name="item_id" type="number" min="1" max="2147483647" value="<?= $h($entry['item_id']) ?>" placeholder="Missing"></div>
</div><?php if ($entry['owned_item_id'] !== null): ?><p class="muted" style="margin-top:8px"><?= $h($entry['reg_number']) ?> · <?= $h($entry['title']) ?></p><?php endif; ?><div class="actions"><button class="btn" type="submit">Save expected item</button></div></form>
<form method="post" style="margin-top:8px" data-cw-remove><?= Csrf::input() ?><input type="hidden" name="action" value="remove_entry"><input type="hidden" name="checklist_id" value="<?= $checklistId ?>"><input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>"><button class="btn danger" type="submit">Remove from checklist</button></form></article>
<?php endforeach; ?>
<?php if (!$entries): ?><p class="empty">No expected items yet. Add the first one below to begin tracking completeness.</p><?php endif; ?>
</section>
<section class="panel"><h2>Add expected item</h2><form method="post"><?= Csrf::input() ?><input type="hidden" name="action" value="save_entry"><input type="hidden" name="checklist_id" value="<?= $checklistId ?>"><div class="entry-grid"><div><label for="new-entry-label">Expected stamp or cover</label><input id="new-entry-label" name="label" required maxlength="255"></div><div><label for="new-entry-reference">Catalogue reference</label><input id="new-entry-reference" name="catalogue_reference" maxlength="120"></div><div><label for="new-entry-item">Owned record ID (optional)</label><input id="new-entry-item" name="item_id" type="number" min="1" max="2147483647" placeholder="Missing"></div></div><div class="actions"><button class="btn primary" type="submit">Add expected item</button></div></form></section>
<?php else:
    $checklists = $inventory->checklists($dimension);
    $legacySets = $inventory->legacySets();
?>
<section class="panel"><h2>Completeness by country, year, series or theme</h2><p class="muted" style="margin-bottom:16px">Progress is measured against the expected items you list, so duplicates never inflate your completion rate.</p>
<form method="get" class="search" style="margin-bottom:12px"><input type="hidden" name="m" value="collection_workbench"><input type="hidden" name="tab" value="completeness"><div><label for="cw-dimension-filter">Show grouping</label><select id="cw-dimension-filter" name="dimension"><option value="">All groupings</option><?php foreach (CollectionInventory::DIMENSIONS as $key => $label): ?><option value="<?= $key ?>" <?= $dimension === $key ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?></select></div><button class="btn" type="submit">Apply filter</button></form>
<?php foreach ($checklists as $checklist):
    $expected = (int)$checklist['expected_count']; $owned = (int)$checklist['owned_count'];
?>
<div class="checklist-row"><div><h3><a href="<?= $h($baseUrl . '&tab=completeness&checklist=' . (int)$checklist['id']) ?>" style="color:var(--cw-blue);text-decoration:underline"><?= $h($checklist['name']) ?></a></h3><p class="muted"><?= $h(CollectionInventory::DIMENSIONS[$checklist['dimension']] ?? $checklist['dimension']) ?> · <?= $h($checklist['group_value']) ?></p><span class="badge missing" style="margin-top:8px"><?= $expected - $owned ?> missing</span></div><div class="progress"><?= $owned ?>/<?= $expected ?><br><small><?= $expected ? (int)round($owned / $expected * 100) . '% complete' : 'Add expected items' ?></small></div></div>
<?php endforeach; ?>
<?php if (!$checklists): ?><p class="empty">No checklists for this grouping yet. Create one below.</p><?php endif; ?>
</section>
<section class="panel"><h2>Create a completeness checklist</h2><form method="post"><?= Csrf::input() ?><div class="grid"><div class="wide"><label for="cw-checklist-name">Checklist name</label><input id="cw-checklist-name" name="name" maxlength="255" required placeholder="Example: India space commemoratives"></div><div><label for="cw-group-by">Group by</label><select id="cw-group-by" name="dimension"><?php foreach (CollectionInventory::DIMENSIONS as $key => $label): ?><option value="<?= $key ?>"><?= $h($label) ?></option><?php endforeach; ?></select></div><div><label for="cw-group-value">Country, year, series or theme name</label><input id="cw-group-value" name="group_value" required maxlength="255" placeholder="Example: India"></div></div><div class="actions"><button type="submit" class="btn primary" name="action" value="create_checklist">Create empty checklist</button></div>
<?php if ($legacySets): ?><div style="margin-top:22px;padding-top:18px;border-top:1px solid var(--cw-border)"><label for="cw-import-set">Or copy expected items from an existing set</label><select id="cw-import-set" name="set_id"><?php foreach ($legacySets as $set): ?><option value="<?= (int)$set['id'] ?>"><?= $h($set['name']) ?></option><?php endforeach; ?></select><p class="muted" style="margin-top:8px">Copies the existing set structure and owned links once. Later changes to either checklist are independent.</p><div class="actions"><button type="submit" class="btn" name="action" value="import_set">Create from selected set</button></div></div><?php endif; ?>
</form></section>
<?php endif; endif;
} catch (InvalidArgumentException $e) { ?><div class="notice error" role="alert"><?= $h($e->getMessage()) ?></div><?php }
catch (Throwable $e) { error_log('Collection workbench view: ' . $e->getMessage()); ?><div class="notice error" role="alert">Unable to load the workbench. Verify the database update and reload this page.</div><?php }
endif; ?>
</div>
<script>
(() => {
    const all = document.getElementById('cw-select-all');
    const boxes = Array.from(document.querySelectorAll('.cw-item-select'));
    if (all) {
        all.checked = boxes.length > 0 && boxes.every(box => box.checked);
        all.addEventListener('change', () => boxes.forEach(box => { box.checked = all.checked; }));
        boxes.forEach(box => box.addEventListener('change', () => {
            const selected = boxes.filter(input => input.checked).length;
            all.checked = selected === boxes.length && boxes.length > 0;
            all.indeterminate = selected > 0 && selected < boxes.length;
        }));
    }
    document.querySelectorAll('[data-cw-remove]').forEach(form => form.addEventListener('submit', event => {
        if (!window.confirm('Remove this expected item from the checklist? Its collection record is retained.')) event.preventDefault();
    }));
})();
</script>
