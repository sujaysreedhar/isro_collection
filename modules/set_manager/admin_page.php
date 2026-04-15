<?php
// modules/set_manager/admin_page.php
$action = $_GET['action'] ?? 'list';
$setId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $target = (int)($_POST['target_count'] ?? 0);

    if ($setId > 0) {
        $stmt = $this->pdo->prepare("UPDATE module_sets SET name = ?, description = ?, target_count = ? WHERE id = ?");
        $stmt->execute([$name, $desc, $target, $setId]);
    } else {
        $stmt = $this->pdo->prepare("INSERT INTO module_sets (name, description, target_count) VALUES (?, ?, ?)");
        $stmt->execute([$name, $desc, $target]);
    }
    header("Location: " . SITE_URL . "/admin/module_page.php?m=set_manager&msg=saved");
    exit;
}

if ($action === 'delete' && $setId > 0) {
    $this->pdo->prepare("DELETE FROM module_sets WHERE id = ?")->execute([$setId]);
    header("Location: " . SITE_URL . "/admin/module_page.php?m=set_manager&msg=deleted");
    exit;
}

$activeSet = null;
if ($setId > 0) {
    $activeSet = $this->getSet($setId);
}
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Collection Sets / Checklists</h1>
        <?php if ($action === 'list'): ?>
            <a href="module_page.php?m=set_manager&action=edit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">Create New Set</a>
        <?php else: ?>
            <a href="module_page.php?m=set_manager" class="text-gray-600 hover:text-gray-900">← Back to List</a>
        <?php endif; ?>
    </div>

    <?php if ($action === 'list'): ?>
        <?php $sets = $this->getAllSets(); ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Set Name</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Completion</th>
                        <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($sets as $s): ?>
                        <?php $prog = $this->getSetProgress($s['id']); ?>
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($s['name']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars(mb_strimwidth($s['description'], 0, 80, "...")) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-blue-600 transition-all" style="width: <?= $prog['percent'] ?>%"></div>
                                    </div>
                                    <span class="text-xs font-medium text-gray-700"><?= $prog['count'] ?> / <?= $prog['target'] ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right space-x-3">
                                <a href="module_page.php?m=set_manager&action=edit&id=<?= $s['id'] ?>" class="text-blue-600 hover:text-blue-900 transition font-medium">Edit</a>
                                <a href="module_page.php?m=set_manager&action=delete&id=<?= $s['id'] ?>" class="text-red-500 hover:text-red-700 transition font-medium" onclick="return confirm('Really delete this set?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($sets)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-gray-400">No sets created yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <!-- Edit Form -->
        <div class="max-w-2xl bg-white p-8 rounded-xl border border-gray-200 shadow-sm">
            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Set Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($activeSet['name'] ?? '') ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"><?= htmlspecialchars($activeSet['description'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Target Count (Number of items in a full set)</label>
                    <input type="number" name="target_count" value="<?= (int)($activeSet['target_count'] ?? 0) ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div class="pt-4 flex justify-end gap-3">
                    <a href="module_page.php?m=set_manager" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-sm">Save Set</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>
