<?php
/**
 * Variants — admin management page.
 * Rendered inside admin/module_page.php (wide mode). POST is handled earlier by
 * VariantsModule::handleAdminPost() on the admin_init_variants hook.
 */
global $pdo;

$csrf = function_exists('generateCsrfToken') ? generateCsrfToken() : '';
$selectedGroupId = (int) ($_GET['group'] ?? 0);

// All groups with their member counts.
$groups = $pdo->query("
    SELECT g.*, (SELECT COUNT(*) FROM module_variant_members m WHERE m.group_id = g.id) AS member_count
    FROM module_variant_groups g
    ORDER BY g.title ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Selected group + its members.
$selectedGroup = null;
$members = [];
if ($selectedGroupId > 0) {
    foreach ($groups as $g) {
        if ((int) $g['id'] === $selectedGroupId) {
            $selectedGroup = $g;
            break;
        }
    }
    if ($selectedGroup) {
        $stmt = $pdo->prepare("
            SELECT m.id AS member_id, m.variant_label, m.sort_order, i.id AS item_id, i.title, i.reg_number, i.production_date,
                   (SELECT mm.file_path FROM media mm WHERE mm.item_id = i.id AND mm.media_type = 'image' ORDER BY mm.id ASC LIMIT 1) AS preview
            FROM module_variant_members m
            JOIN items i ON i.id = m.item_id
            WHERE m.group_id = ?
            ORDER BY m.sort_order ASC, i.production_date ASC, i.id ASC
        ");
        $stmt->execute([$selectedGroupId]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Items available to add (exclude ones already in THIS group).
$allItems = [];
if ($selectedGroup) {
    $stmt = $pdo->prepare("
        SELECT i.id, i.reg_number, i.title
        FROM items i
        WHERE i.id NOT IN (SELECT item_id FROM module_variant_members WHERE group_id = ?)
        ORDER BY i.title ASC
    ");
    $stmt->execute([$selectedGroupId]);
    $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$imgUrl = function (?string $p): string {
    $p = trim((string) $p);
    if ($p === '') return '';
    global $storage;
    if (isset($storage) && is_object($storage) && method_exists($storage, 'url')) {
        return $storage->url('display/' . $p);
    }
    return SITE_URL . '/uploads/display/' . rawurlencode($p);
};
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Variants</h1>
    <p class="text-gray-500 mt-1 text-sm max-w-2xl">
        Group items that are variants of the same thing — the same cancellation across different years, or one coin
        struck at different mints. Grouped items show a switcher on their detail page so visitors can flip between them.
        You can also assign an item to a group directly from its editor.
    </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ── Left: groups list + create ─────────────────────────────── -->
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-3">Create a group</h2>
            <form method="post" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="variant_action" value="create_group">
                <input type="text" name="title" required class="input w-full" placeholder="Group name (e.g. 1 Anna Victoria head)">
                <input type="text" name="group_type" class="input w-full" placeholder="Type (optional) — Cancellation, Coin…">
                <textarea name="description" rows="2" class="input w-full" placeholder="Optional notes"></textarea>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                    Create group
                </button>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 px-5 pt-5 pb-2">All groups (<?= count($groups) ?>)</h2>
            <?php if (empty($groups)): ?>
                <p class="text-gray-400 text-sm px-5 pb-5">No groups yet. Create one to get started.</p>
            <?php else: ?>
                <ul class="divide-y divide-gray-100">
                    <?php foreach ($groups as $g): ?>
                        <li>
                            <a href="<?= SITE_URL ?>/admin/module_page.php?m=variants&group=<?= (int) $g['id'] ?>"
                               class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition <?= ((int) $g['id'] === $selectedGroupId) ? 'bg-blue-50' : '' ?>">
                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-900 truncate"><?= htmlspecialchars($g['title']) ?></div>
                                    <?php if (!empty($g['group_type'])): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($g['group_type']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="ml-3 flex-shrink-0 text-xs font-medium text-gray-500 bg-gray-100 rounded-full px-2 py-0.5"><?= (int) $g['member_count'] ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right: selected group detail ───────────────────────────── -->
    <div class="lg:col-span-2">
        <?php if (!$selectedGroup): ?>
            <div class="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center text-gray-400">
                Select a group on the left to manage its variants.
            </div>
        <?php else: ?>
            <!-- Edit group -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-lg font-bold text-gray-900">Edit group</h2>
                    <form method="post" onsubmit="return confirm('Delete this group? The items themselves are not deleted — they just stop being variants.');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="variant_action" value="delete_group">
                        <input type="hidden" name="group_id" value="<?= (int) $selectedGroup['id'] ?>">
                        <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Delete group</button>
                    </form>
                </div>
                <form method="post" class="mt-4 space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="variant_action" value="update_group">
                    <input type="hidden" name="group_id" value="<?= (int) $selectedGroup['id'] ?>">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input type="text" name="title" required class="input w-full" value="<?= htmlspecialchars($selectedGroup['title']) ?>" placeholder="Group name">
                        <input type="text" name="group_type" class="input w-full" value="<?= htmlspecialchars($selectedGroup['group_type'] ?? '') ?>" placeholder="Type (optional)">
                    </div>
                    <textarea name="description" rows="2" class="input w-full" placeholder="Optional notes"><?= htmlspecialchars($selectedGroup['description'] ?? '') ?></textarea>
                    <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white font-semibold py-2 px-4 rounded-lg transition">Save changes</button>
                </form>
            </div>

            <!-- Members -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Variants in this group (<?= count($members) ?>)</h2>
                <?php if (empty($members)): ?>
                    <p class="text-gray-400 text-sm">No items yet. Add one below.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($members as $mem):
                            $img = $imgUrl($mem['preview'] ?? '');
                            ?>
                            <form method="post" class="flex items-center gap-3 border border-gray-100 rounded-lg p-2">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="group_id" value="<?= (int) $selectedGroup['id'] ?>">
                                <input type="hidden" name="member_id" value="<?= (int) $mem['member_id'] ?>">

                                <div class="w-14 h-14 flex-shrink-0 rounded-lg bg-gray-100 overflow-hidden flex items-center justify-center">
                                    <?php if ($img): ?>
                                        <img src="<?= htmlspecialchars($img) ?>" alt="" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <?php endif; ?>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <a href="<?= SITE_URL ?>/admin/edit_item.php?id=<?= (int) $mem['item_id'] ?>" target="_blank" class="font-medium text-gray-900 hover:text-blue-600 truncate block">
                                        <?= htmlspecialchars($mem['title']) ?>
                                    </a>
                                    <span class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($mem['reg_number']) ?></span>
                                </div>

                                <input type="text" name="variant_label" value="<?= htmlspecialchars($mem['variant_label'] ?? '') ?>"
                                       class="input w-40" placeholder="Variant label">
                                <input type="number" name="sort_order" value="<?= (int) $mem['sort_order'] ?>" class="input w-16" title="Sort order">

                                <button type="submit" name="variant_action" value="update_member" class="text-sm font-medium text-blue-600 hover:text-blue-800 flex-shrink-0">Save</button>
                                <button type="submit" name="variant_action" value="remove_member" class="text-sm font-medium text-red-500 hover:text-red-700 flex-shrink-0"
                                        onclick="return confirm('Remove this item from the group?');">Remove</button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add member -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-3">Add an item to this group</h2>
                <form method="post" class="flex flex-col sm:flex-row gap-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="variant_action" value="add_member">
                    <input type="hidden" name="group_id" value="<?= (int) $selectedGroup['id'] ?>">
                    <select name="item_id" required class="input flex-1">
                        <option value="">Select an item…</option>
                        <?php foreach ($allItems as $it): ?>
                            <option value="<?= (int) $it['id'] ?>">[<?= htmlspecialchars($it['reg_number']) ?>] <?= htmlspecialchars($it['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="variant_label" class="input sm:w-56" placeholder="Variant label (optional)">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition flex-shrink-0">Add</button>
                </form>
                <p class="text-xs text-gray-400 mt-2">Note: an item can belong to only one variant group. Adding one that's already grouped elsewhere moves it here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
