<?php
// modules/trade_manager/admin_trades.php

if (!defined('SITE_URL')) {
    exit;
}

global $pdo;
require_once __DIR__ . '/../../config/config.php';

// Handle Actions
if (isset($_GET['action']) && isset($_GET['request_id'])) {
    $requestId = (int)$_GET['request_id'];
    $status = $_GET['action'] === 'accept' ? 'accepted' : ($_GET['action'] === 'reject' ? 'rejected' : 'pending');
    
    $stmt = $pdo->prepare("UPDATE trade_requests SET status = ? WHERE id = ?");
    $stmt->execute([$status, $requestId]);
    
    header("Location: admin/module_page.php?m=trade_manager&page=requests");
    exit;
}

$stmt = $pdo->query("
    SELECT tr.*, i.title as item_name 
    FROM trade_requests tr 
    JOIN items i ON tr.item_id = i.id 
    ORDER BY tr.created_at DESC
");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="p-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Trade Requests</h1>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requester</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Requested</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No trade requests found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $tr): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('M j, Y', strtotime($tr['created_at'])) ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($tr['name']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($tr['email']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 font-medium"><?= htmlspecialchars($tr['item_name']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <div class="max-w-xs truncate" title="<?= htmlspecialchars($tr['message']) ?>">
                                    <?= htmlspecialchars($tr['message']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?= $tr['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                       ($tr['status'] === 'accepted' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') ?>">
                                    <?= ucfirst($tr['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                <?php if ($tr['status'] === 'pending'): ?>
                                    <a href="module_page.php?m=trade_manager&page=requests&action=accept&request_id=<?= $tr['id'] ?>" class="text-green-600 hover:text-green-900">Accept</a>
                                    <a href="module_page.php?m=trade_manager&page=requests&action=reject&request_id=<?= $tr['id'] ?>" class="text-red-600 hover:text-red-900">Reject</a>
                                <?php else: ?>
                                    <span class="text-gray-400">Locked</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
