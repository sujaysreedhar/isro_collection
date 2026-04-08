<?php
// modules/item_specs/views/admin_settings.php
/** @var array $this The Module class instance */

$categories = $this->pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$mapping = $this->getSpecsMapping();
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mapping'])) {
    $newMapping = $_POST['mapping'] ?? [];
    $this->saveSpecsMapping($newMapping);
    $mapping = $this->getSpecsMapping(); // Refresh
    $success = "Mapping saved successfully!";
}
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Item Specs Mapping</h1>
    <p class="text-gray-500 mt-1">Assign categories to specific technical field groups. This determines which fields appear when editing an item.</p>
</div>

<?php if ($success): ?>
    <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl shadow-sm">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<form method="POST" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <input type="hidden" name="save_mapping" value="1">
    <table class="w-full text-left border-collapse">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest">Category Name</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest">Technical Profile</th>
                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest">Fields Included</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($categories as $cat): ?>
                <?php $currentProfile = $mapping[$cat['id']] ?? ''; ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-5">
                        <span class="font-bold text-gray-900"><?= htmlspecialchars($cat['name']) ?></span>
                        <span class="block text-[10px] text-gray-400 font-mono">ID: <?= $cat['id'] ?></span>
                    </td>
                    <td class="px-6 py-5">
                        <select name="mapping[<?= $cat['id'] ?>]" class="w-full max-w-xs px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="">-- No Technical Specs --</option>
                            <option value="philately" <?= $currentProfile === 'philately' ? 'selected' : '' ?>>📬 Philately (Stamps/FDC)</option>
                            <option value="numismatics" <?= $currentProfile === 'numismatics' ? 'selected' : '' ?>>🪙 Numismatics (Coins)</option>
                            <option value="banknotes" <?= $currentProfile === 'banknotes' ? 'selected' : '' ?>>💵 Bank Notes</option>
                            <option value="postcard" <?= $currentProfile === 'postcard' ? 'selected' : '' ?>>🖼 Postcards / History</option>
                        </select>
                    </td>
                    <td class="px-6 py-5">
                        <div class="flex flex-wrap gap-1">
                            <?php if ($currentProfile === 'philately'): ?>
                                <span class="bg-blue-50 text-blue-600 text-[10px] px-2 py-0.5 rounded uppercase font-bold">Perforation</span>
                                <span class="bg-blue-50 text-blue-600 text-[10px] px-2 py-0.5 rounded uppercase font-bold">Watermark</span>
                            <?php elseif ($currentProfile === 'numismatics'): ?>
                                <span class="bg-amber-50 text-amber-600 text-[10px] px-2 py-0.5 rounded uppercase font-bold">Weight</span>
                                <span class="bg-amber-50 text-amber-600 text-[10px] px-2 py-0.5 rounded uppercase font-bold">Diameter</span>
                                <span class="bg-amber-50 text-amber-600 text-[10px] px-2 py-0.5 rounded uppercase font-bold">Mint Mark</span>
                            <?php elseif ($currentProfile === 'banknotes'): ?>
                                <span class="bg-emerald-50 text-emerald-600 text-[10px] px-2 py-0.5 rounded uppercase font-bold">Serial #</span>
                                <span class="bg-emerald-50 text-emerald-600 text-[10px] px-2 py-0.5 rounded uppercase font-bold">Watermark</span>
                            <?php elseif ($currentProfile === 'postcard'): ?>
                                <span class="bg-slate-50 text-slate-600 text-[10px] px-2 py-0.5 rounded uppercase font-bold">Postmark</span>
                                <span class="bg-slate-50 text-slate-600 text-[10px] px-2 py-0.5 rounded uppercase font-bold">Publisher</span>
                            <?php else: ?>
                                <span class="text-gray-300 italic text-xs">Standard Metadata Only</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
        <button type="submit" class="px-6 py-2 bg-gray-900 text-white font-bold rounded-xl hover:bg-gray-800 transition-all shadow-lg active:scale-95">Save Mapping</button>
    </div>
</form>
