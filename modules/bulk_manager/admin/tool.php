<?php
// modules/bulk_manager/admin/tool.php

$items = $this->pdo->query("SELECT id, reg_number, title, category_id FROM items ORDER BY reg_number ASC LIMIT 100")->fetchAll();
$categories = $this->pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $itemIds = array_map('intval', (array)($_POST['item_ids'] ?? []));
    $newCategoryId = (int)($_POST['new_category_id'] ?? 0);
    
    if (empty($itemIds)) {
        $error = "No items selected.";
    } elseif ($newCategoryId <= 0) {
        $error = "Select a valid category.";
    } else {
        try {
            $stmt = $this->pdo->prepare("UPDATE items SET category_id = ? WHERE id = ?");
            foreach ($itemIds as $id) {
                $stmt->execute([$newCategoryId, $id]);
            }
            $success = count($itemIds) . " items updated successfully.";
            // Refresh list
            $items = $this->pdo->query("SELECT id, reg_number, title, category_id FROM items ORDER BY reg_number ASC LIMIT 100")->fetchAll();
        } catch (Exception $e) {
            $error = "Failed to update: " . $e->getMessage();
        }
    }
}
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Bulk Inventory Manager</h1>
    <p class="text-gray-500 mt-1">Mass-update categories, visibility, or deletions for your collection.</p>
</div>

<?php if ($error): ?><div class="mb-4 bg-red-50 text-red-700 p-4 rounded-xl border border-red-100"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="mb-4 bg-emerald-50 text-emerald-700 p-4 rounded-xl border border-emerald-100"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<form method="POST">
    <input type="hidden" name="bulk_action" value="1">
    
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-8">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-bold text-gray-700">Select Items to Update</h3>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400">Showing first 100 items</span>
                <button type="button" onclick="selectAll(true)" class="text-[10px] font-bold text-blue-600 uppercase tracking-widest hover:underline">Select All</button>
                <span class="text-gray-300">|</span>
                <button type="button" onclick="selectAll(false)" class="text-[10px] font-bold text-gray-500 uppercase tracking-widest hover:underline">Clear</button>
            </div>
        </div>
        
        <div class="max-h-[500px] overflow-y-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] border-b border-gray-100">
                        <th class="px-6 py-3 w-12"></th>
                        <th class="px-6 py-3">Registration #</th>
                        <th class="px-6 py-3">Title</th>
                        <th class="px-6 py-3">Current Category</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php foreach ($items as $item): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-3">
                                <input type="checkbox" name="item_ids[]" value="<?= $item['id'] ?>" class="item-checkbox w-4 h-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                            </td>
                            <td class="px-6 py-3 font-mono text-xs text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($item['reg_number'] ?? '') ?></td>
                            <td class="px-6 py-3 font-bold text-gray-900"><?= htmlspecialchars($item['title']) ?></td>
                            <td class="px-6 py-3">
                                <?php 
                                    foreach($categories as $c) {
                                        if ($c['id'] == $item['category_id']) {
                                            echo '<span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px] font-bold uppercase tracking-widest">' . htmlspecialchars($c['name']) . '</span>';
                                        }
                                    }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="bg-slate-900 rounded-3xl p-8 border border-slate-800 shadow-2xl shadow-slate-300">
        <h3 class="text-white font-bold mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            Selected Action
        </h3>
        <div class="flex flex-col md:flex-row items-end gap-6">
            <div class="flex-grow">
                <label class="block text-slate-400 text-xs font-bold uppercase tracking-widest mb-2">Move to Category</label>
                <select name="new_category_id" class="w-full bg-slate-800 border-slate-700 text-white rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    <option value="">-- Select New Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="px-10 py-3 bg-white text-slate-900 font-black rounded-xl hover:bg-blue-50 transition-all shadow-xl shadow-slate-950/20 active:scale-95">Apply Bulk Changes</button>
        </div>
        <p class="text-[10px] text-slate-500 mt-4 leading-relaxed font-medium tracking-wide italic">WARNING: This action will overwrite the primary category for all selected items. This cannot be undone.</p>
    </div>
</form>

<script>
function selectAll(val) {
    document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = val);
}
</script>
