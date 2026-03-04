<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../MediaProcessor.php';

$mp = new MediaProcessor($pdo);

$id  = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$item = [
    'reg_number' => '', 'title' => '', 'physical_description' => '',
    'historical_significance' => '', 'production_date' => '', 'credit_line' => '', 'category_id' => '',
];
$mediaList       = [];
$linkedNarratives = [];
$error   = '';
$success = '';

$categories   = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$allNarratives = $pdo->query("SELECT id, title FROM narratives ORDER BY title ASC")->fetchAll();

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        $item = $fetched;
        $mStmt = $pdo->prepare("SELECT * FROM media WHERE item_id = :id ORDER BY is_primary DESC");
        $mStmt->execute([':id' => $id]);
        $mediaList = $mStmt->fetchAll();
        $nStmt = $pdo->prepare("SELECT narrative_id FROM item_narrative WHERE item_id = :id");
        $nStmt->execute([':id' => $id]);
        $linkedNarratives = $nStmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $error = "Item not found.";
        $id = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reg_number             = trim($_POST['reg_number'] ?? '');
    $title                  = trim($_POST['title'] ?? '');
    $physical_description   = trim($_POST['physical_description'] ?? '');
    $historical_significance= trim($_POST['historical_significance'] ?? '');
    $production_date        = trim($_POST['production_date'] ?? '');
    $credit_line            = trim($_POST['credit_line'] ?? '');
    $category_id            = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;

    try {
        if ($id > 0) {
            $pdo->prepare("
                UPDATE items SET reg_number=:reg, title=:title, physical_description=:desc,
                historical_significance=:hist, production_date=:prod, credit_line=:cred, category_id=:cat
                WHERE id=:id
            ")->execute([
                ':reg'=>$reg_number, ':title'=>$title, ':desc'=>$physical_description,
                ':hist'=>$historical_significance, ':prod'=>$production_date, ':cred'=>$credit_line,
                ':cat'=>$category_id, ':id'=>$id,
            ]);
            $success = "Item updated.";
        } else {
            $pdo->prepare("
                INSERT INTO items (reg_number,title,physical_description,historical_significance,production_date,credit_line,category_id)
                VALUES (:reg,:title,:desc,:hist,:prod,:cred,:cat)
            ")->execute([
                ':reg'=>$reg_number, ':title'=>$title, ':desc'=>$physical_description,
                ':hist'=>$historical_significance, ':prod'=>$production_date, ':cred'=>$credit_line, ':cat'=>$category_id,
            ]);
            $id = (int) $pdo->lastInsertId();
            $success = "Item created.";
        }

        // — Image via MediaProcessor —
        if (isset($_FILES['media_upload']) && $_FILES['media_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
            $result = $mp->process(
                $_FILES['media_upload'],
                $id,
                trim($_POST['media_caption'] ?? ''),
                $_POST['media_license'] ?? 'Public Domain',
                isset($_POST['is_primary'])
            );
            if ($result['success']) {
                $success .= ' ' . $result['message'];
            } else {
                $error = $result['message'];
            }
        }

        // — Narrative pivot sync —
        $pdo->prepare("DELETE FROM item_narrative WHERE item_id = :id")->execute([':id' => $id]);
        $sel = array_map('intval', (array)($_POST['narratives'] ?? []));
        if ($sel) {
            $ls = $pdo->prepare("INSERT INTO item_narrative (item_id, narrative_id) VALUES (:i, :n)");
            foreach (array_filter($sel) as $nid) { $ls->execute([':i' => $id, ':n' => $nid]); }
        }

        // Reload
        if (empty($error)) {
            $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            $mStmt = $pdo->prepare("SELECT * FROM media WHERE item_id = :id ORDER BY is_primary DESC");
            $mStmt->execute([':id' => $id]);
            $mediaList = $mStmt->fetchAll();
            $nStmt = $pdo->prepare("SELECT narrative_id FROM item_narrative WHERE item_id = :id");
            $nStmt->execute([':id' => $id]);
            $linkedNarratives = $nStmt->fetchAll(PDO::FETCH_COLUMN);
        }

    } catch (\PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

echo renderAdminHeader($id > 0 ? "Edit — " . htmlspecialchars($item['reg_number'] ?? '') : "New Item");

// Build JS array of pre-selected narrative ids for TomSelect
$preselected = json_encode(array_map('intval', $linkedNarratives));
?>

<!-- TomSelect -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<style>
.ts-control { border: 1px solid #d1d5db !important; border-radius: 6px !important; padding: 6px 8px !important; }
.ts-control:focus-within { border-color: #111827 !important; box-shadow: 0 0 0 1px #111827 !important; }
.ts-dropdown { border: 1px solid #d1d5db !important; border-radius: 6px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1) !important; }
</style>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900"><?= $id > 0 ? 'Edit Item' : 'New Item' ?></h1>
        <?php if($id > 0): ?>
            <p class="text-sm text-gray-500 mt-1">Reg: <strong><?= htmlspecialchars($item['reg_number']) ?></strong></p>
        <?php endif; ?>
    </div>
    <a href="items.php" class="text-gray-600 hover:text-gray-900 text-sm font-medium">&larr; Back to List</a>
</div>

<?php if ($error): ?>
<div class="mb-5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="mb-5 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="flex flex-col xl:flex-row gap-8">

    <!-- MAIN FORM -->
    <div class="flex-1 min-w-0">
        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg border border-gray-200 shadow-sm">

            <!-- Metadata -->
            <div class="p-6 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                <h3 class="font-semibold text-gray-800">Metadata</h3>
            </div>
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="label">Registration Number *</label>
                        <input type="text" name="reg_number" value="<?= htmlspecialchars($item['reg_number'] ?? '') ?>" required class="input">
                    </div>
                    <div>
                        <label class="label">Category *</label>
                        <select name="category_id" required class="input">
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($item['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label">Title *</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($item['title'] ?? '') ?>" required class="input">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="label">Production Date</label>
                        <input type="text" name="production_date" value="<?= htmlspecialchars($item['production_date'] ?? '') ?>" placeholder="e.g. Circa 1860" class="input">
                    </div>
                    <div>
                        <label class="label">Credit Line</label>
                        <input type="text" name="credit_line" value="<?= htmlspecialchars($item['credit_line'] ?? '') ?>" class="input">
                    </div>
                </div>
                <div>
                    <label class="label">Physical Description</label>
                    <textarea name="physical_description" rows="4" class="input"><?= htmlspecialchars($item['physical_description'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="label">Historical Significance</label>
                    <textarea name="historical_significance" rows="3" class="input"><?= htmlspecialchars($item['historical_significance'] ?? '') ?></textarea>
                </div>

                <!-- TomSelect Narrative Linker -->
                <div>
                    <label class="label">
                        Linked Stories / Narratives
                        <span class="text-gray-400 font-normal">(search and tag multiple)</span>
                    </label>
                    <select id="narrative-select" name="narratives[]" multiple placeholder="Search for a story…" class="w-full">
                        <?php foreach ($allNarratives as $n): ?>
                            <option value="<?= $n['id'] ?>" <?= in_array($n['id'], $linkedNarratives) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($n['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Image Upload -->
            <div class="p-6 border-t border-gray-200 bg-gray-50">
                <h3 class="font-semibold text-gray-800 mb-4">
                    Attach New Image
                    <span class="text-xs font-normal text-gray-400 ml-2">JPG · PNG · WebP · max 5 MB → auto-converted to WebP in 3 sizes</span>
                </h3>
                <div class="space-y-4">
                    <input type="file" name="media_upload" accept=".jpg,.jpeg,.png,.webp,.gif"
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="label">Caption</label>
                            <input type="text" name="media_caption" class="input">
                        </div>
                        <div>
                            <label class="label">License</label>
                            <select name="media_license" class="input">
                                <option>Public Domain</option>
                                <option>CC BY 4.0</option>
                                <option>All Rights Reserved</option>
                            </select>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_primary" value="1" class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                        <span class="text-sm text-gray-700">Set as <strong>Primary Image</strong> <span class="text-gray-400">(displayed in search results)</span></span>
                    </label>
                </div>
            </div>

            <div class="p-6 border-t border-gray-200 flex justify-end gap-3 rounded-b-lg">
                <a href="items.php" class="px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-5 py-2 text-sm font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800">
                    <?= $id > 0 ? 'Update Item' : 'Save Item' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- MEDIA SIDEBAR -->
    <?php if ($id > 0): ?>
    <div class="w-full xl:w-80 space-y-4 flex-shrink-0">
        <h3 class="font-semibold text-gray-800">Attached Media <span class="text-gray-400 font-normal text-sm">(<?= count($mediaList) ?>)</span></h3>
        <?php if ($mediaList): ?>
            <?php foreach ($mediaList as $m): ?>
            <div class="bg-white border <?= $m['is_primary'] ? 'border-blue-500 ring-1 ring-blue-500' : 'border-gray-200' ?> rounded-lg overflow-hidden shadow-sm">
                <?php if ($m['is_primary']): ?>
                    <div class="bg-blue-600 text-white text-[10px] font-bold px-2 py-1 text-center tracking-widest">PRIMARY</div>
                <?php endif; ?>
                <div class="h-36 bg-gray-100">
                    <img src="<?= MediaProcessor::url($m['file_path'], 'thumbs') ?>"
                         onerror="this.src='<?= MediaProcessor::url($m['file_path'], 'display') ?>'"
                         class="object-cover w-full h-full" alt="Media thumbnail">
                </div>
                <div class="p-3 text-xs space-y-1 text-gray-500">
                    <p class="font-mono break-all"><?= htmlspecialchars($m['file_path']) ?></p>
                    <?php if (!empty($m['dimensions'])): ?><p>📐 <?= $m['dimensions'] ?></p><?php endif; ?>
                    <?php if (!empty($m['file_size'])): ?><p>💾 <?= round($m['file_size']/1024, 1) ?> KB</p><?php endif; ?>
                    <?php if (!empty($m['mime_type'])): ?><p>🎨 <?= htmlspecialchars($m['mime_type']) ?></p><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="border border-dashed border-gray-300 rounded-lg p-6 text-center text-sm text-gray-400">
                No images yet. Upload one using the form.
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Reusable input styles -->
<style>
.label { display: block; font-size: .875rem; font-weight: 500; color: #374151; margin-bottom: .25rem; }
.input { width: 100%; border: 1px solid #d1d5db; border-radius: .375rem; padding: .5rem .75rem; font-size: .875rem;
         outline: none; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
.input:focus { border-color: #111827; box-shadow: 0 0 0 1px #111827; }
</style>

<script>
new TomSelect('#narrative-select', {
    plugins: ['remove_button'],
    placeholder: 'Search for a story…',
    maxOptions: 200,
});
</script>

<?= renderAdminFooter(); ?>
