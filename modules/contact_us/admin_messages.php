<?php
// modules/contact_us/admin_messages.php
// Admin view — List and manage contact messages

global $pdo;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['msg_action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        die('Invalid CSRF token.');
    }
    $msgId = (int)($_POST['msg_id'] ?? 0);

    if ($_POST['msg_action'] === 'mark_read' && $msgId > 0) {
        $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([$msgId]);
    } elseif ($_POST['msg_action'] === 'mark_unread' && $msgId > 0) {
        $pdo->prepare("UPDATE contact_messages SET is_read = 0 WHERE id = ?")->execute([$msgId]);
    } elseif ($_POST['msg_action'] === 'delete' && $msgId > 0) {
        $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$msgId]);
    }
}

// Fetch messages
$filter = $_GET['filter'] ?? 'all';
$query = "SELECT * FROM contact_messages";
$params = [];

if ($filter === 'unread') {
    $query .= " WHERE is_read = 0";
} elseif ($filter === 'read') {
    $query .= " WHERE is_read = 1";
}
$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll();

$unreadCount = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
$totalCount  = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-xl font-bold text-gray-900">Contact Messages</h2>
        <p class="text-sm text-gray-500 mt-1"><?= $totalCount ?> total messages, <?= $unreadCount ?> unread.</p>
    </div>
    <div class="flex gap-2 text-sm">
        <a href="?m=contact_us&filter=all" class="px-3 py-1.5 rounded-lg font-medium transition-colors <?= $filter === 'all' ? 'bg-gray-900 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">All</a>
        <a href="?m=contact_us&filter=unread" class="px-3 py-1.5 rounded-lg font-medium transition-colors <?= $filter === 'unread' ? 'bg-gray-900 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">Unread<?= $unreadCount ? " ($unreadCount)" : '' ?></a>
        <a href="?m=contact_us&filter=read" class="px-3 py-1.5 rounded-lg font-medium transition-colors <?= $filter === 'read' ? 'bg-gray-900 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">Read</a>
    </div>
</div>

<?php if (empty($messages)): ?>
<div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
    <svg class="mx-auto w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
    <p class="text-gray-500 font-medium">No messages found.</p>
</div>
<?php else: ?>
<div class="space-y-3">
    <?php foreach ($messages as $msg): ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow <?= !$msg['is_read'] ? 'border-l-4 border-l-blue-500' : '' ?>" id="msg-<?= $msg['id'] ?>">
        <div class="p-5">
            <div class="flex flex-col sm:flex-row justify-between gap-3 mb-3">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-400 to-indigo-500 flex items-center justify-center text-white text-sm font-bold flex-shrink-0 shadow-sm">
                        <?= strtoupper(substr($msg['name'], 0, 1)) ?>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($msg['name']) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($msg['email']) ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <span class="text-gray-400"><?= date('M j, Y g:i A', strtotime($msg['created_at'])) ?></span>
                    <?php if (!$msg['is_read']): ?>
                        <span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full font-semibold">New</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($msg['subject'])): ?>
                <p class="font-semibold text-gray-800 text-sm mb-2"><?= htmlspecialchars($msg['subject']) ?></p>
            <?php endif; ?>
            
            <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($msg['message']) ?></p>
            
            <div class="flex gap-2 mt-4 pt-3 border-t border-gray-100">
                <?php if (!$msg['is_read']): ?>
                <form method="POST" class="inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>"><input type="hidden" name="msg_id" value="<?= $msg['id'] ?>"><input type="hidden" name="msg_action" value="mark_read">
                    <button type="submit" class="text-xs font-medium text-gray-500 hover:text-blue-600 transition-colors flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Mark Read
                    </button>
                </form>
                <?php else: ?>
                <form method="POST" class="inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>"><input type="hidden" name="msg_id" value="<?= $msg['id'] ?>"><input type="hidden" name="msg_action" value="mark_unread">
                    <button type="submit" class="text-xs font-medium text-gray-500 hover:text-blue-600 transition-colors flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8"></path></svg>Mark Unread
                    </button>
                </form>
                <?php endif; ?>
                <a href="mailto:<?= htmlspecialchars($msg['email']) ?>?subject=Re: <?= rawurlencode($msg['subject'] ?: 'Your Message') ?>" class="text-xs font-medium text-gray-500 hover:text-green-600 transition-colors flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>Reply
                </a>
                <form method="POST" class="inline ml-auto"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>"><input type="hidden" name="msg_id" value="<?= $msg['id'] ?>"><input type="hidden" name="msg_action" value="delete">
                    <button type="submit" onclick="return confirm('Delete this message permanently?')" class="text-xs font-medium text-gray-400 hover:text-red-600 transition-colors flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
