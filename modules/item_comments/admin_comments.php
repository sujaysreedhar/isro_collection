<?php
// modules/item_comments/admin_comments.php
if (!defined('SITE_URL')) { die('Direct access denied.'); }

global $pdo;

// Handle Actions (Approve, Reject, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['comment_id'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? '') or die("CSRF validation failed.");
    
    $commentId = (int)$_POST['comment_id'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        $pdo->prepare("UPDATE item_comments SET status = 'approved' WHERE id = ?")->execute([$commentId]);
        $success = "Comment approved.";
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE item_comments SET status = 'rejected' WHERE id = ?")->execute([$commentId]);
        $success = "Comment rejected.";
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM item_comments WHERE id = ?")->execute([$commentId]);
        $success = "Comment deleted permanently.";
    }
}

// Fetch comments with item details
$filterStatus = $_GET['status'] ?? 'pending';
$allowedStatuses = ['pending', 'approved', 'rejected'];
if (!in_array($filterStatus, $allowedStatuses)) { $filterStatus = 'pending'; }

$stmt = $pdo->prepare("
    SELECT c.*, i.title as item_title, i.reg_number 
    FROM item_comments c
    JOIN items i ON c.item_id = i.id
    WHERE c.status = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$filterStatus]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts for tabs
$counts = [];
foreach ($allowedStatuses as $s) {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM item_comments WHERE status = ?");
    $stmtCount->execute([$s]);
    $counts[$s] = $stmtCount->fetchColumn();
}
$counts['total'] = array_sum($counts);
?>

<div class="mb-6 pb-5 border-b border-gray-200 sm:flex sm:items-center sm:justify-between">
    <h3 class="text-2xl leading-6 font-bold text-gray-900">Moderate Community Notes</h3>
</div>

<?php if (isset($success)): ?>
    <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-400 text-green-800 rounded-lg shadow-sm">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<!-- Tabs -->
<div class="mb-6 border-b border-gray-200">
    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
        <?php foreach ($allowedStatuses as $s): ?>
            <a href="module_page.php?m=item_comments&status=<?= $s ?>" 
               class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm
               <?= $filterStatus === $s ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                <?= ucfirst($s) ?>
                <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs <?= $filterStatus === $s ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-900' ?>">
                    <?= $counts[$s] ?>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<div class="bg-white shadow overflow-hidden sm:rounded-lg border border-gray-200">
    <?php if (empty($comments)): ?>
        <div class="p-12 text-center text-gray-500">
            No <?= htmlspecialchars($filterStatus) ?> comments found.
        </div>
    <?php else: ?>
        <ul role="list" class="divide-y divide-gray-200">
            <?php foreach ($comments as $c): ?>
                <li class="p-6 hover:bg-gray-50 transition duration-150 ease-in-out">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-bold text-blue-600 truncate">
                                    <?= htmlspecialchars($c['author_name']) ?> 
                                    <a href="mailto:<?= htmlspecialchars($c['author_email']) ?>" class="font-normal text-gray-500 hover:text-gray-900 hover:underline">&lt;<?= htmlspecialchars($c['author_email']) ?>&gt;</a>
                                </p>
                                <div class="ml-2 flex-shrink-0 flex">
                                    <p class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?= $c['status'] === 'approved' ? 'bg-green-100 text-green-800' : ($c['status'] === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                        <?= ucfirst($c['status']) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-2 text-sm text-gray-900 prose prose-sm max-w-none">
                                <?= nl2br(htmlspecialchars($c['comment'])) ?>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-gray-500">
                                <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <?= date('M j, Y, g:i a', strtotime($c['created_at'])) ?>
                                <span class="mx-2">&bull;</span>
                                <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                <a href="<?= SITE_URL ?>/item/<?= $c['item_id'] ?>" target="_blank" class="hover:text-blue-600 hover:underline">
                                    <?= htmlspecialchars($c['item_title']) ?> (<?= htmlspecialchars($c['reg_number']) ?>)
                                </a>
                            </div>
                        </div>
                        <div class="ml-6 flex-shrink-0 flex space-x-2">
                            <form method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                <?php if ($c['status'] !== 'approved'): ?>
                                    <button type="submit" name="action" value="approve" class="bg-white border border-green-300 text-green-700 hover:bg-green-50 px-3 py-1.5 rounded shadow-sm text-xs font-semibold transition">Approve</button>
                                <?php endif; ?>
                                <?php if ($c['status'] !== 'rejected'): ?>
                                    <button type="submit" name="action" value="reject" class="bg-white border border-yellow-300 text-yellow-700 hover:bg-yellow-50 px-3 py-1.5 rounded shadow-sm text-xs font-semibold transition">Reject</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="delete" class="bg-white border border-red-300 text-red-700 hover:bg-red-50 px-3 py-1.5 rounded shadow-sm text-xs font-semibold transition ml-2" onclick="return confirm('Are you sure you want to permanently delete this comment?');">Delete</button>
                            </form>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
