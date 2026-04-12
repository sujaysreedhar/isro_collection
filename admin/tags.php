<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

// Delete Tag Logic
if (isset($_POST['delete_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
    $deleteId = (int)$_POST['delete_id'];
    try {
        $pdo->beginTransaction();
        
        // Remove links in item_tag first (in case FKs aren't set up)
        $delLinks = $pdo->prepare("DELETE FROM item_tag WHERE tag_id = :id");
        $delLinks->execute([':id' => $deleteId]);
        
        // Delete the tag itself
        $delTag = $pdo->prepare("DELETE FROM tags WHERE id = :id");
        $delTag->execute([':id' => $deleteId]);
        
        $pdo->commit();
        
        header("Location: " . SITE_URL . "/admin/tags.php?msg=deleted");
        if (class_exists('HookRegistry')) {
            HookRegistry::doAction('tag_deleted', $deleteId);
        }
        exit;
    } catch (\PDOException $e) {
        $pdo->rollBack();
        $error = "Cannot delete this tag. Error: " . $e->getMessage();
    }
}

// Fetch all tags with item count
$stmt = $pdo->query("
    SELECT t.id, t.name, t.slug, COUNT(it.item_id) as item_count 
    FROM tags t
    LEFT JOIN item_tag it ON t.id = it.tag_id
    GROUP BY t.id
    ORDER BY t.name ASC
");
$tags = $stmt->fetchAll();

echo renderAdminHeader("Manage Tags");
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 leading-tight">Manage Tags</h1>
        <p class="text-sm text-gray-500 mt-1">Manage global hashtags used in your collection.</p>
    </div>
    <a href="<?= SITE_URL ?>/admin/edit_tag.php" class="bg-gray-900 text-white font-medium px-4 py-2 rounded-md hover:bg-gray-800 transition inline-flex items-center shadow-sm">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        Add New Tag
    </a>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
<div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl relative flex items-center gap-3">
    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
    Tag deleted successfully.
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl relative flex items-center gap-3">
    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-slate-50 text-slate-500 uppercase font-semibold text-[11px] tracking-wider border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">ID</th>
                    <th class="px-6 py-4">Name</th>
                    <th class="px-6 py-4">Slug</th>
                    <th class="px-6 py-4">Linked Items</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (count($tags) > 0): ?>
                    <?php foreach ($tags as $tag): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-slate-400 font-mono text-xs"><?= $tag['id'] ?></td>
                        <td class="px-6 py-4 font-semibold text-slate-800">
                            <span class="inline-flex items-center gap-1">
                                <span class="text-blue-500 opacity-50">#</span>
                                <?= htmlspecialchars($tag['name']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-500 italic"><?= htmlspecialchars($tag['slug']) ?></td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                <?= $tag['item_count'] ?> items
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap text-sm font-medium">
                            <a href="<?= SITE_URL ?>/admin/edit_tag.php?id=<?= $tag['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-4 transition-colors">Edit</a>
                            
                            <form method="POST" action="" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this tag? It will be removed from all linked items.');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                                <input type="hidden" name="delete_id" value="<?= $tag['id'] ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700 transition-colors">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400 italic bg-slate-50/30">No tags found. Tags are created automatically when editing items, or you can add one manually.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (class_exists('HookRegistry')) { HookRegistry::doAction('tags_view_after_table'); } ?>

<?= renderAdminFooter(); ?>
