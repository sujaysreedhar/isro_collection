<?php
// modules/postmark_atlas/admin_locations.php
if (!defined('SITE_URL')) exit;

global $pdo;

$error = '';
$success = '';

// Handle Actions (Add, Delete, Toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
         $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $pin = trim($_POST['pin_code'] ?? '');
            $po = trim($_POST['post_office'] ?? '');
            $dist = trim($_POST['district'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $lat = (float)($_POST['latitude'] ?? 0);
            $lng = (float)($_POST['longitude'] ?? 0);
            
            if ($pin && $po && $lat && $lng) {
                $stmt = $pdo->prepare("INSERT INTO postmark_locations (pin_code, post_office, district, state, latitude, longitude, is_acquired) VALUES (?, ?, ?, ?, ?, ?, 0)");
                if ($stmt->execute([$pin, $po, $dist, $state, $lat, $lng])) {
                    $success = "Location added successfully.";
                } else {
                    $error = "Failed to add location.";
                }
            } else {
                 $error = "PIN Code, Post Office Name, and Coordinates are required.";
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM postmark_locations WHERE id = ?");
            if ($stmt->execute([$id])) {
                $success = "Location removed.";
            } else {
                $error = "Failed to remove location.";
            }
        } elseif ($action === 'toggle_acquired') {
            $id = (int)$_POST['id'];
            $val = (int)$_POST['is_acquired'] === 1 ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE postmark_locations SET is_acquired = ? WHERE id = ?");
            if ($stmt->execute([$val, $id])) {
                 // Set a light success message or just pass, since it might be AJAX/redirect loops
                 $success = "Acquisition status updated.";
            }
        }
    }
}

// Filter Logic
$filterState = $_GET['state'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$whereClauses = [];
$params = [];

if ($filterState !== '') {
    $whereClauses[] = "state = ?";
    $params[] = $filterState;
}

if ($filterStatus !== '') {
    $whereClauses[] = "is_acquired = ?";
    $params[] = (int)$filterStatus;
}

$whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $pdo->prepare("SELECT * FROM postmark_locations $whereSql ORDER BY state ASC, district ASC, post_office ASC");
$stmt->execute($params);
$locations = $stmt->fetchAll();

// Get unique states for dropdown
$stateStmt = $pdo->query("SELECT DISTINCT state FROM postmark_locations WHERE state IS NOT NULL AND state != '' ORDER BY state ASC");
$states = $stateStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-900">Locations Tracker</h2>
        <p class="text-sm text-gray-500">Manage your post office acquisition targets.</p>
    </div>
    <div>
        <button onclick="document.getElementById('add-location-modal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700">
            + Add Location
        </button>
    </div>
</div>

<?php if ($error): ?>
<div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4"><p class="text-sm text-red-700"><?= htmlspecialchars($error) ?></p></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4"><p class="text-sm text-green-700"><?= htmlspecialchars($success) ?></p></div>
<?php endif; ?>

<!-- Filters -->
<div class="mb-4 bg-white p-4 rounded-md shadow-sm border border-gray-200">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="m" value="postmark_atlas">
        <input type="hidden" name="page" value="locations">
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
            <select name="state" class="block w-full border border-gray-300 rounded-md py-2 px-3 text-sm">
                <option value="">All States</option>
                <?php foreach ($states as $st): ?>
                    <option value="<?= htmlspecialchars($st) ?>" <?= $filterState === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="block w-full border border-gray-300 rounded-md py-2 px-3 text-sm">
                <option value="">All Statuses</option>
                <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Acquired</option>
                <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Not Acquired</option>
            </select>
        </div>
        <div>
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Filter</button>
            <a href="?m=postmark_atlas&page=locations" class="inline-flex items-center px-4 py-2 text-sm font-medium text-blue-600 hover:text-blue-500">Clear</a>
        </div>
    </form>
</div>

<!-- Data Table -->
<div class="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PIN Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Post Office</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">District / City</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">State</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acquired</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php if (empty($locations)): ?>
            <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No locations added yet.</td></tr>
            <?php else: ?>
                <?php foreach ($locations as $loc): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($loc['pin_code']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($loc['post_office']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($loc['district']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($loc['state']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                            <input type="hidden" name="action" value="toggle_acquired">
                            <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                            <input type="hidden" name="is_acquired" value="<?= $loc['is_acquired'] ? '0' : '1' ?>">
                            <button type="submit" class="text-<?= $loc['is_acquired'] ? 'yellow' : 'gray' ?>-500 hover:text-<?= $loc['is_acquired'] ? 'yellow' : 'gray' ?>-700 focus:outline-none">
                                <?php if ($loc['is_acquired']): ?>
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                <?php else: ?>
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <?php endif; ?>
                            </button>
                        </form>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this location?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                            <button type="submit" class="text-red-600 hover:text-red-900 ml-3">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add Modal -->
<div id="add-location-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Add New Location</h3>
            <button onclick="document.getElementById('add-location-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
            <input type="hidden" name="action" value="add">
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">PIN Code</label>
                    <input type="text" name="pin_code" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Post Office</label>
                    <input type="text" name="post_office" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">District / City</label>
                    <input type="text" name="district" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">State</label>
                    <input type="text" name="state" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Latitude</label>
                    <input type="number" step="any" name="latitude" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm" placeholder="e.g. 12.9716">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Longitude</label>
                    <input type="number" step="any" name="longitude" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm" placeholder="e.g. 77.5946">
                </div>
            </div>
            
            <div class="mt-5 sm:mt-6">
                <button type="submit" class="inline-flex justify-center w-full rounded-md border border-transparent shadow-sm px-4 py-2 bg-yellow-600 text-base font-medium text-white hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 sm:text-sm">
                    Save Location
                </button>
            </div>
        </form>
    </div>
</div>
