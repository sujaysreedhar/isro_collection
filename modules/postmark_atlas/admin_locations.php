<?php
// modules/postmark_atlas/admin_locations.php
if (!defined('SITE_URL')) exit;

global $pdo;

$error = '';
$success = '';

// Handle Actions (Add, Delete, Toggle, Bulk)
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
                 $success = "Acquisition status updated.";
            }
        } elseif ($action === 'bulk_acquire') {
            $ids = $_POST['selected_ids'] ?? [];
            if (!empty($ids)) {
                $ids = array_map('intval', $ids);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("UPDATE postmark_locations SET is_acquired = 1 WHERE id IN ($placeholders)");
                if ($stmt->execute($ids)) {
                    $count = $stmt->rowCount();
                    $success = "{$count} location(s) marked as acquired.";
                } else {
                    $error = "Failed to update locations.";
                }
            } else {
                $error = "No locations selected.";
            }
        } elseif ($action === 'bulk_unacquire') {
            $ids = $_POST['selected_ids'] ?? [];
            if (!empty($ids)) {
                $ids = array_map('intval', $ids);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("UPDATE postmark_locations SET is_acquired = 0 WHERE id IN ($placeholders)");
                if ($stmt->execute($ids)) {
                    $count = $stmt->rowCount();
                    $success = "{$count} location(s) marked as not acquired.";
                } else {
                    $error = "Failed to update locations.";
                }
            } else {
                $error = "No locations selected.";
            }
        } elseif ($action === 'link_item') {
            $id     = (int)$_POST['id'];
            $itemId = (int)$_POST['item_id'];
            $stmt   = $pdo->prepare("UPDATE postmark_locations SET linked_item_id = ? WHERE id = ?");
            $stmt->execute([$itemId ?: null, $id]);
            $success = $itemId ? "Item linked." : "Item link removed.";
        } elseif ($action === 'bulk_delete') {
            $ids = $_POST['selected_ids'] ?? [];
            if (!empty($ids)) {
                $ids = array_map('intval', $ids);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM postmark_locations WHERE id IN ($placeholders)");
                if ($stmt->execute($ids)) {
                    $count = $stmt->rowCount();
                    $success = "{$count} location(s) deleted.";
                } else {
                    $error = "Failed to delete locations.";
                }
            } else {
                $error = "No locations selected.";
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

$stmt = $pdo->prepare("
    SELECT pl.*, i.title AS linked_item_title
    FROM postmark_locations pl
    LEFT JOIN items i ON i.id = pl.linked_item_id
    $whereSql ORDER BY pl.state ASC, pl.district ASC, pl.post_office ASC
");
$stmt->execute($params);
$locations = $stmt->fetchAll();

// Items for the link picker — exclude items already linked to any location
$linkedItemIds = array_values(array_filter(array_column($locations, 'linked_item_id')));
if (!empty($linkedItemIds)) {
    $ph       = implode(',', array_fill(0, count($linkedItemIds), '?'));
    $aiStmt   = $pdo->prepare("SELECT id, title FROM items WHERE id NOT IN ($ph) ORDER BY title ASC");
    $aiStmt->execute($linkedItemIds);
    $allItems = $aiStmt->fetchAll();
} else {
    $allItems = $pdo->query("SELECT id, title FROM items ORDER BY title ASC")->fetchAll();
}

// Get unique states for dropdown
$stateStmt = $pdo->query("SELECT DISTINCT state FROM postmark_locations WHERE state IS NOT NULL AND state != '' ORDER BY state ASC");
$states = $stateStmt->fetchAll(PDO::FETCH_COLUMN);

// Get counts
$totalCount = $pdo->query("SELECT COUNT(*) FROM postmark_locations")->fetchColumn();
$acquiredCount = $pdo->query("SELECT COUNT(*) FROM postmark_locations WHERE is_acquired = 1")->fetchColumn();
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-900">Locations Tracker</h2>
        <p class="text-sm text-gray-500">Manage your post office acquisition targets. <strong><?= number_format($acquiredCount) ?></strong> / <strong><?= number_format($totalCount) ?></strong> acquired.</p>
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

<!-- Bulk Actions Bar -->
<div id="bulk-bar" class="hidden mb-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3 flex flex-wrap items-center gap-3 shadow-sm">
    <span class="text-sm font-semibold text-yellow-800"><span id="selected-count">0</span> selected</span>
    <div class="flex gap-2 ml-auto">
        <button type="button" onclick="submitBulkAction('bulk_acquire')" class="inline-flex items-center px-3 py-1.5 text-xs font-bold rounded-md bg-green-600 text-white hover:bg-green-700 transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            Mark Acquired
        </button>
        <button type="button" onclick="submitBulkAction('bulk_unacquire')" class="inline-flex items-center px-3 py-1.5 text-xs font-bold rounded-md bg-gray-500 text-white hover:bg-gray-600 transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            Unmark Acquired
        </button>
        <button type="button" onclick="if(confirm('Delete all selected locations? This cannot be undone.')){submitBulkAction('bulk_delete')}" class="inline-flex items-center px-3 py-1.5 text-xs font-bold rounded-md bg-red-600 text-white hover:bg-red-700 transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            Delete Selected
        </button>
    </div>
</div>

<!-- Hidden bulk form -->
<form id="bulk-form" method="POST" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
    <input type="hidden" name="action" id="bulk-action-input" value="">
</form>

<!-- Data Table -->
<div class="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200 overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left w-8">
                    <input type="checkbox" id="select-all" class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500 cursor-pointer" onchange="toggleSelectAll(this)">
                </th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">PIN</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name of PPC</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Post Office</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">District / State</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">✓</th>
                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-100">
            <?php if (empty($locations)): ?>
            <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400 text-sm">No locations added yet.</td></tr>
            <?php else: ?>
                <?php foreach ($locations as $loc): ?>
                <tr class="hover:bg-gray-50 align-middle">
                    <td class="px-3 py-2">
                        <input type="checkbox" class="row-checkbox rounded border-gray-300 text-yellow-600 focus:ring-yellow-500 cursor-pointer" value="<?= $loc['id'] ?>" onchange="updateBulkBar()">
                    </td>

                    <!-- PIN -->
                    <td class="px-3 py-2 whitespace-nowrap font-mono text-xs text-gray-700"><?= htmlspecialchars($loc['pin_code']) ?></td>

                    <!-- Name of PPC -->
                    <td class="px-3 py-2" style="max-width:180px;">
                        <div style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                             title="<?= htmlspecialchars($loc['ppc_name'] ?? '') ?>">
                            <span class="font-medium text-indigo-700"><?= htmlspecialchars($loc['ppc_name'] ?? '—') ?></span>
                        </div>
                    </td>

                    <!-- Post Office -->
                    <td class="px-3 py-2" style="max-width:160px;">
                        <div style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                             title="<?= htmlspecialchars($loc['post_office']) ?>">
                            <span class="text-gray-700"><?= htmlspecialchars($loc['post_office']) ?></span>
                        </div>
                    </td>

                    <!-- District / State (combined) -->
                    <td class="px-3 py-2 whitespace-nowrap">
                        <div class="text-xs text-gray-700"><?= htmlspecialchars($loc['district']) ?></div>
                        <div class="text-xs text-gray-400"><?= htmlspecialchars($loc['state']) ?></div>
                    </td>

                    <!-- Linked Item (compact) -->
                    <td class="px-3 py-2" style="min-width:120px;">
                        <?php if ($loc['linked_item_id'] && $loc['linked_item_title']): ?>
                            <div class="flex items-center gap-1">
                                <a href="<?= SITE_URL ?>/item_detail.php?id=<?= $loc['linked_item_id'] ?>" target="_blank"
                                   class="text-xs text-blue-600 hover:underline font-medium"
                                   style="max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;"
                                   title="<?= htmlspecialchars($loc['linked_item_title']) ?>">
                                    <?= htmlspecialchars($loc['linked_item_title']) ?>
                                </a>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                                    <input type="hidden" name="action" value="link_item">
                                    <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                                    <input type="hidden" name="item_id" value="0">
                                    <button type="submit" class="text-red-300 hover:text-red-500 text-xs leading-none" title="Unlink">✕</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <form method="POST" class="flex items-center gap-1">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                                <input type="hidden" name="action" value="link_item">
                                <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                                <select name="item_id" class="text-xs border border-gray-200 rounded px-1 py-0.5 text-gray-600" style="max-width:95px;">
                                    <option value="">— link —</option>
                                    <?php foreach ($allItems as $it): ?>
                                        <option value="<?= $it['id'] ?>"><?= htmlspecialchars($it['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="text-xs text-blue-600 hover:text-blue-800 font-semibold whitespace-nowrap">+</button>
                            </form>
                        <?php endif; ?>
                    </td>

                    <!-- Acquired toggle -->
                    <td class="px-3 py-2 text-center">
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                            <input type="hidden" name="action" value="toggle_acquired">
                            <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                            <input type="hidden" name="is_acquired" value="<?= $loc['is_acquired'] ? '0' : '1' ?>">
                            <button type="submit" class="focus:outline-none"
                                    style="color:<?= $loc['is_acquired'] ? '#eab308' : '#d1d5db' ?>;"
                                    title="<?= $loc['is_acquired'] ? 'Unmark' : 'Mark acquired' ?>">
                                <?php if ($loc['is_acquired']): ?>
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <?php else: ?>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <?php endif; ?>
                            </button>
                        </form>
                    </td>

                    <!-- Delete -->
                    <td class="px-3 py-2 text-right">
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this location?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                            <button type="submit" class="text-gray-300 hover:text-red-500 transition-colors" title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="px-4 py-2 bg-gray-50 border-t border-gray-200 text-xs text-gray-400">
        Showing <strong class="text-gray-600"><?= number_format(count($locations)) ?></strong> location(s)
    </div>
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

<script>
function toggleSelectAll(master) {
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = master.checked;
    });
    updateBulkBar();
}

function updateBulkBar() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    const bar = document.getElementById('bulk-bar');
    const count = document.getElementById('selected-count');
    
    if (checked.length > 0) {
        bar.classList.remove('hidden');
        count.textContent = checked.length;
    } else {
        bar.classList.add('hidden');
    }
    
    // Update select-all state
    const all = document.querySelectorAll('.row-checkbox');
    const selectAll = document.getElementById('select-all');
    selectAll.checked = all.length > 0 && checked.length === all.length;
    selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
}

function submitBulkAction(action) {
    const form = document.getElementById('bulk-form');
    const actionInput = document.getElementById('bulk-action-input');
    actionInput.value = action;
    
    // Remove old hidden inputs
    form.querySelectorAll('input[name="selected_ids[]"]').forEach(el => el.remove());
    
    // Add selected IDs
    document.querySelectorAll('.row-checkbox:checked').forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_ids[]';
        input.value = cb.value;
        form.appendChild(input);
    });
    
    form.submit();
}
</script>
