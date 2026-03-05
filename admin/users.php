<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../config/config.php';

$error   = '';
$success = '';

// Handle POST actions (Create, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Both username and password are required.';
        } else {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'An administrator with this username already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
                if ($insert->execute([$username, $hash])) {
                    $success = 'New administrator created successfully.';
                } else {
                    $error = 'Failed to create administrator.';
                }
            }
        }
    } elseif ($action === 'delete') {
        $deleteId = (int)($_POST['user_id'] ?? 0);
        
        // Prevent deleting the currently logged-in user
        if ($deleteId === ($_SESSION['admin_id'] ?? 0)) {
             $error = 'You cannot delete your own account while logged in.';
        } else {
            // Check if this is the last admin
            $countStmt = $pdo->query("SELECT COUNT(*) FROM admins");
            $adminCount = $countStmt->fetchColumn();
            
            if ($adminCount <= 1) {
                $error = 'Cannot delete the last administrator account.';
            } else {
                $delStmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
                if ($delStmt->execute([$deleteId])) {
                     $success = 'Administrator deleted successfully.';
                } else {
                     $error = 'Failed to delete administrator.';
                }
            }
        }
    }
}

// Fetch all admins
$stmt = $pdo->query("SELECT id, username, created_at FROM admins ORDER BY created_at ASC");
$admins = $stmt->fetchAll();

echo renderAdminHeader('Manage Administrators');
?>

<div class="mb-6 flex justify-between items-end">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Manage Administrators</h1>
        <p class="text-sm text-gray-500 mt-1">Add or remove users with access to this panel.</p>
    </div>
</div>

<?php if ($error): ?>
<div class="mb-5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded flex items-start gap-2">
    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <span><?= htmlspecialchars($error) ?></span>
</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="mb-5 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded flex items-start gap-2">
    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
    <span><?= $success ?></span>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- List of Users -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 font-bold border border-gray-200">
                                    <?= strtoupper(substr($admin['username'], 0, 1)) ?>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($admin['username']) ?>
                                        <?php if ($admin['id'] == ($_SESSION['admin_id'] ?? 0)): ?>
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">You</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= date('M j, Y, g:i a', strtotime($admin['created_at'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <?php if ($admin['id'] != ($_SESSION['admin_id'] ?? 0)): ?>
                            <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this administrator?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                                <button type="submit" class="text-red-600 hover:text-red-900 transition-colors">Delete</button>
                            </form>
                            <?php else: ?>
                                <span class="text-gray-300 cursor-not-allowed">Delete</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add New User Form -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-200 bg-gray-50">
                <h3 class="font-semibold text-gray-800">Add Administrator</h3>
            </div>
            <div class="p-5">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" name="username" id="username" required
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900"
                               placeholder="e.g. jsmith">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" name="password" id="password" required
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900"
                               placeholder="••••••••">
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition-colors">
                            Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= renderAdminFooter(); ?>
