<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

// Delete Category Logic
if (isset($_POST['delete_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
    $deleteId = (int)$_POST['delete_id'];
    try {
        $delStmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
        $delStmt->execute([':id' => $deleteId]);
        header("Location: " . SITE_URL . "/admin/categories.php?msg=deleted");
        exit;
    } catch (\PDOException $e) {
        $error = "Cannot delete this category. It may be linked to existing items.";
    }
}

// Fetch all categories
$stmt = $pdo->query("
    SELECT c.id, c.name, COUNT(i.id) as item_count 
    FROM categories c
    LEFT JOIN items i ON c.id = i.category_id
    GROUP BY c.id
    ORDER BY c.name ASC
");
$categories = $stmt->fetchAll();

echo renderAdminHeader("Manage Categories");
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 leading-tight">Manage Categories</h1>
        <p class="text-sm text-gray-500 mt-1">Organize your collection by categories.</p>
    </div>
    <a href="<?= SITE_URL ?>/admin/edit_category.php" class="bg-gray-900 text-white font-medium px-4 py-2 rounded-md hover:bg-gray-800 transition inline-flex items-center">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        Add New Category
    </a>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
<div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative">
    Category deleted successfully.
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-gray-50 text-gray-500 uppercase font-semibold text-xs border-b border-gray-200">
                <tr>
                    <th class="px-6 py-4">ID</th>
                    <th class="px-6 py-4 w-1/2">Name</th>
                    <th class="px-6 py-4">Linked Items</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (count($categories) > 0): ?>
                    <?php foreach ($categories as $cat): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap text-gray-500"><?= $cat['id'] ?></td>
                        <td class="px-6 py-4 font-medium text-gray-800"><?= htmlspecialchars($cat['name']) ?></td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                <?= $cat['item_count'] ?> items
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap text-sm font-medium">
                            <a href="<?= SITE_URL ?>/admin/edit_category.php?id=<?= $cat['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                            
                            <form method="POST" action="" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                                <input type="hidden" name="delete_id" value="<?= $cat['id'] ?>">
                                <button type="submit" class="text-red-600 hover:text-red-900" <?= $cat['item_count'] > 0 ? 'disabled title="Cannot delete category with items" class="text-gray-400 cursor-not-allowed"' : '' ?>>
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">No categories available. Click "Add New Category" to create one.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderAdminFooter(); ?>
