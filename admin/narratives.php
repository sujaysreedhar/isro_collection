<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

// Pagination variables
$limit = 20; // Number of items per page (Server-side constraint)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Delete Narrative Logic
if (isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    $delStmt = $pdo->prepare("DELETE FROM narratives WHERE id = :id");
    $delStmt->execute([':id' => $deleteId]);
    header("Location: " . SITE_URL . "/admin/narratives.php?msg=deleted");
    exit;
}

// Fetch Total Count for Pagination
$totalResult = $pdo->query("SELECT COUNT(*) FROM narratives")->fetchColumn();
$totalPages = ceil($totalResult / $limit);

// Fetch all narratives safely
$stmt = $pdo->prepare("
    SELECT n.id, n.title, COUNT(inv.item_id) as linked_items
    FROM narratives n
    LEFT JOIN item_narrative inv ON n.id = inv.narrative_id
    GROUP BY n.id
    ORDER BY n.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$narratives = $stmt->fetchAll();

echo renderAdminHeader("Manage Stories & Narratives");
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 leading-tight">Manage Stories & Narratives</h1>
        <p class="text-sm text-gray-500 mt-1">Showing <?= count($narratives) ?> narratives of <?= number_format((int)$totalResult) ?> total.</p>
    </div>
    <a href="<?= SITE_URL ?>/admin/edit_narrative.php" class="bg-gray-900 text-white font-medium px-4 py-2 rounded-md hover:bg-gray-800 transition inline-flex items-center">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        Add New Narrative
    </a>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
<div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative">
    Narrative deleted successfully.
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-gray-50 text-gray-500 uppercase font-semibold text-xs border-b border-gray-200">
                <tr>
                    <th class="px-6 py-4">ID</th>
                    <th class="px-6 py-4 w-1/2">Title</th>
                    <th class="px-6 py-4">Linked Items</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (count($narratives) > 0): ?>
                    <?php foreach ($narratives as $nar): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap text-gray-500"><?= $nar['id'] ?></td>
                        <td class="px-6 py-4 font-medium text-gray-800"><?= htmlspecialchars($nar['title']) ?></td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                <?= $nar['linked_items'] ?> items
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap text-sm font-medium">
                            <a href="<?= SITE_URL ?>/admin/edit_narrative.php?id=<?= $nar['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                            
                            <form method="POST" action="" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this narrative?');">
                                <input type="hidden" name="delete_id" value="<?= $nar['id'] ?>">
                                <button type="submit" class="text-red-600 hover:text-red-900">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">No narratives available. Click "Add New Narrative" to create one.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Server-Side Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50">
        <div class="text-sm text-gray-500">
            Page <?= $page ?> of <?= $totalPages ?>
        </div>
        <div class="flex space-x-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 border border-gray-300 rounded text-sm text-gray-600 bg-white hover:bg-gray-50 transition">Previous</a>
            <?php endif; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 border border-gray-300 rounded text-sm text-gray-600 bg-white hover:bg-gray-50 transition">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?= renderAdminFooter(); ?>
