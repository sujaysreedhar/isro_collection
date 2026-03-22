<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die("CSRF token validation failed.");
    }

    if (isset($_POST['create_key'])) {
        $clientName = trim($_POST['client_name'] ?: 'External Client');
        $newKey = bin2hex(random_bytes(16)); // 32 chars
        $stmt = $pdo->prepare("INSERT INTO api_keys (key_value, client_name) VALUES (?, ?)");
        $stmt->execute([$newKey, $clientName]);
        header("Location: api_keys.php?msg=created");
        exit;
    }

    if (isset($_POST['toggle_active'])) {
        $id = (int)$_POST['key_id'];
        $stmt = $pdo->prepare("UPDATE api_keys SET is_active = 1 - is_active WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: api_keys.php?msg=updated");
        exit;
    }

    if (isset($_POST['delete_key'])) {
        $id = (int)$_POST['key_id'];
        $stmt = $pdo->prepare("DELETE FROM api_keys WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: api_keys.php?msg=deleted");
        exit;
    }
}

$keys = $pdo->query("SELECT * FROM api_keys ORDER BY created_at DESC")->fetchAll();

echo renderAdminHeader("API Management");
?>

<div class="mb-8 flex justify-between items-end">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">API Access Keys</h1>
        <p class="text-base text-slate-500 mt-1">Manage bearer tokens for authorized 3rd party integrations and scraping prevention.</p>
    </div>
    <button onclick="document.getElementById('new-key-modal').classList.remove('hidden')" class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-blue-700 transition-all">Generate New Key</button>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl flex items-center gap-3">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
    <span class="text-sm font-medium">
        <?php
            if($_GET['msg'] == 'created') echo "New API key generated successfully.";
            if($_GET['msg'] == 'updated') echo "API key status updated.";
            if($_GET['msg'] == 'deleted') echo "API key permanently removed.";
        ?>
    </span>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-[11px] tracking-wider border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">Client Name</th>
                    <th class="px-6 py-4">API Key / Token</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Created</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($keys as $k): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-bold text-slate-900"><?= htmlspecialchars($k['client_name']) ?></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <code class="bg-slate-100 px-2 py-1 rounded text-xs text-slate-600 font-mono"><?= htmlspecialchars($k['key_value']) ?></code>
                            <button onclick="copyToClipboard('<?= $k['key_value'] ?>')" class="text-slate-400 hover:text-blue-600 transition-colors" title="Copy to clipboard">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                            </button>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?= $k['is_active'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-100 text-slate-500 border border-slate-200' ?>">
                            <?= $k['is_active'] ? 'Active' : 'Revoked' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-slate-400 text-xs"><?= date('M j, Y', strtotime($k['created_at'])) ?></td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                            <input type="hidden" name="key_id" value="<?= $k['id'] ?>">
                            <button type="submit" name="toggle_active" class="text-xs font-bold <?= $k['is_active'] ? 'text-amber-600 hover:text-amber-800' : 'text-emerald-600 hover:text-emerald-800' ?> uppercase transition-colors">
                                <?= $k['is_active'] ? 'Revoke' : 'Activate' ?>
                            </button>
                        </form>
                        <form method="POST" class="inline" onsubmit="return confirm('Permanently delete this API key? This action cannot be undone.');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                            <input type="hidden" name="key_id" value="<?= $k['id'] ?>">
                            <button type="submit" name="delete_key" class="text-xs font-bold text-red-600 hover:text-red-800 uppercase transition-colors">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($keys)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-slate-400">No API keys found. Generate one to start using the API securely.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Usage Instructions -->
<div class="mt-10 bg-slate-900 rounded-2xl p-8 text-slate-300">
    <h3 class="text-lg font-bold text-white mb-6">How to use</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="space-y-4">
            <p class="text-sm font-semibold text-slate-400 uppercase tracking-widest">Option 1: HTTP Header (Recommended)</p>
            <div class="bg-slate-800 rounded-lg p-4 font-mono text-xs overflow-x-auto border border-slate-700">
                <span class="text-pink-400">GET</span> /api.php?action=items<br>
                <span class="text-emerald-400">X-API-KEY:</span> your_key_here
            </div>
        </div>
        <div class="space-y-4">
            <p class="text-sm font-semibold text-slate-400 uppercase tracking-widest">Option 2: Bearer Token</p>
            <div class="bg-slate-800 rounded-lg p-4 font-mono text-xs overflow-x-auto border border-slate-700">
                <span class="text-pink-400">GET</span> /api.php?action=items<br>
                <span class="text-emerald-400">Authorization:</span> Bearer your_key_here
            </div>
        </div>
    </div>
</div>

<!-- New Key Modal -->
<div id="new-key-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-md w-full p-8">
        <h3 class="text-xl font-extrabold text-slate-900 mb-2">Generate New API Key</h3>
        <p class="text-slate-500 text-sm mb-6">Describe who or what will be using this key for better tracking.</p>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Client / App Name</label>
                <input type="text" name="client_name" placeholder="e.g. Mobile App, Search Bot..." required
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
            </div>
            
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('new-key-modal').classList.add('hidden')" class="flex-1 px-4 py-3 bg-slate-100 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition-all">Cancel</button>
                <button type="submit" name="create_key" class="flex-1 px-4 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition-all shadow-md shadow-blue-500/20">Generate Key</button>
            </div>
        </form>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('API Key copied to clipboard');
    });
}
</script>

<?= renderAdminFooter(); ?>
