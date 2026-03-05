<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../MediaProcessor.php';

$mp = new MediaProcessor($pdo, $storage ?? null);
$hasIsPrimary = false;
try {
    $columnStmt = $pdo->query("SHOW COLUMNS FROM media LIKE 'is_primary'");
    $hasIsPrimary = (bool) $columnStmt->fetch();
} catch (\PDOException) {
    $hasIsPrimary = false;
}

$id  = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$item = [
    'reg_number' => '', 'title' => '', 'physical_description' => '',
    'historical_significance' => '', 'production_date' => '', 'credit_line' => '', 'category_id' => '',
];
$mediaList       = [];
$linkedNarratives = [];
$linkedTags      = [];
$error   = '';
$success = '';

$categories   = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$allNarratives = $pdo->query("SELECT id, title FROM narratives ORDER BY title ASC")->fetchAll();
$allTags       = $pdo->query("SELECT id, name FROM tags ORDER BY name ASC")->fetchAll();

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        $item = $fetched;
        $mStmt = $pdo->prepare("SELECT * FROM media WHERE item_id = :id ORDER BY " . ($hasIsPrimary ? "is_primary DESC, " : "") . "upload_date DESC");
        $mStmt->execute([':id' => $id]);
        $mediaList = $mStmt->fetchAll();
        $nStmt = $pdo->prepare("SELECT narrative_id FROM item_narrative WHERE item_id = :id");
        $nStmt->execute([':id' => $id]);
        $linkedNarratives = $nStmt->fetchAll(PDO::FETCH_COLUMN);
        $tStmt = $pdo->prepare("SELECT tag_id FROM item_tag WHERE item_id = :id");
        $tStmt->execute([':id' => $id]);
        $linkedTags = $tStmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $error = "Item not found.";
        $id = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }

    if (($_POST['action'] ?? '') === 'make_primary' && $id > 0) {
        $mediaId = (int) ($_POST['primary_media_id'] ?? 0);

        try {
            if (!$hasIsPrimary) {
                throw new RuntimeException('Primary media is not supported in this database schema.');
            }

            $stmt = $pdo->prepare("SELECT id FROM media WHERE id = :media_id AND item_id = :item_id AND media_type = 'image' LIMIT 1");
            $stmt->execute([':media_id' => $mediaId, ':item_id' => $id]);
            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('Only image media can be set as primary.');
            }

            $pdo->beginTransaction();
            $pdo->prepare('UPDATE media SET is_primary = 0 WHERE item_id = :item_id')->execute([':item_id' => $id]);
            $pdo->prepare('UPDATE media SET is_primary = 1 WHERE id = :media_id AND item_id = :item_id')
                ->execute([':media_id' => $mediaId, ':item_id' => $id]);
            $pdo->commit();
            $success = 'Primary image updated.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unable to set primary image right now. Please try again.';
        }

        $stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch();
        $mStmt = $pdo->prepare("SELECT * FROM media WHERE item_id = :id ORDER BY " . ($hasIsPrimary ? "is_primary DESC, " : "") . "upload_date DESC");
        $mStmt->execute([':id' => $id]);
        $mediaList = $mStmt->fetchAll();
        $nStmt = $pdo->prepare("SELECT narrative_id FROM item_narrative WHERE item_id = :id");
        $nStmt->execute([':id' => $id]);
        $linkedNarratives = $nStmt->fetchAll(PDO::FETCH_COLUMN);
        $tStmt = $pdo->prepare("SELECT tag_id FROM item_tag WHERE item_id = :id");
        $tStmt->execute([':id' => $id]);
        $linkedTags = $tStmt->fetchAll(PDO::FETCH_COLUMN);
    } else {

        $reg_number             = trim($_POST['reg_number'] ?? '');
        $title                  = trim($_POST['title'] ?? '');
        $physical_description   = trim($_POST['physical_description'] ?? '');
        $historical_significance= trim($_POST['historical_significance'] ?? '');
        $production_date        = trim($_POST['production_date'] ?? '');
        $credit_line            = trim($_POST['credit_line'] ?? '');
        $category_id            = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;

        try {
            $pdo->beginTransaction();

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

        // — Delete selected media (image/pdf/youtube) —
        $deleteMediaIds = array_map('intval', (array) ($_POST['delete_media'] ?? []));
        foreach (array_filter($deleteMediaIds) as $mediaId) {
            $result = $mp->deleteMedia($mediaId, $id);
            if (!$result['success']) {
                throw new RuntimeException($result['message']);
            }
        }
        if (!empty($deleteMediaIds)) {
            $success .= ' Selected media deleted.';
        }

        // — Image via MediaProcessor —
        if (isset($_FILES['media_upload']) && $_FILES['media_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
            $result = $mp->process(
                $_FILES['media_upload'], $id,
                trim($_POST['media_caption'] ?? ''),
                $_POST['media_license'] ?? 'Public Domain',
                isset($_POST['is_primary'])
            );
            if ($result['success']) {
                $success .= ' ' . $result['message'];
            } else {
                throw new RuntimeException($result['message']);
            }
        }

        // — PDF via MediaProcessor —
        if (isset($_FILES['pdf_upload']) && $_FILES['pdf_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
            $result = $mp->processPdf(
                $_FILES['pdf_upload'], $id,
                trim($_POST['pdf_caption'] ?? '')
            );
            if ($result['success']) {
                $success .= ' ' . $result['message'];
            } else {
                throw new RuntimeException($result['message']);
            }
        }

        // — YouTube via MediaProcessor —
        $ytUrl = trim($_POST['youtube_url'] ?? '');
        if ($ytUrl !== '') {
            $result = $mp->processYoutube($ytUrl, $id, trim($_POST['youtube_caption'] ?? ''));
            if ($result['success']) {
                $success .= ' ' . $result['message'];
            } else {
                throw new RuntimeException($result['message']);
            }
        }

        // — Narrative pivot sync —
        $pdo->prepare("DELETE FROM item_narrative WHERE item_id = :id")->execute([':id' => $id]);
        $sel = array_map('intval', (array)($_POST['narratives'] ?? []));
        if ($sel) {
            $ls = $pdo->prepare("INSERT INTO item_narrative (item_id, narrative_id) VALUES (:i, :n)");
            foreach (array_filter($sel) as $nid) { $ls->execute([':i' => $id, ':n' => $nid]); }
        }

        // — Tag pivot sync (auto-create new tags) —
        $pdo->prepare("DELETE FROM item_tag WHERE item_id = :id")->execute([':id' => $id]);
        $rawTags = (array)($_POST['tags'] ?? []);
        foreach ($rawTags as $tagValue) {
            $tagValue = trim($tagValue);
            if ($tagValue === '') continue;
            if (ctype_digit($tagValue)) {
                // Existing tag by ID
                $tagId = (int)$tagValue;
            } else {
                // New tag — create it
                $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($tagValue));
                $slug = trim($slug, '-');
                // Check if slug already exists
                $existCheck = $pdo->prepare("SELECT id FROM tags WHERE slug = :s");
                $existCheck->execute([':s' => $slug]);
                $tagId = $existCheck->fetchColumn();
                if (!$tagId) {
                    $ins = $pdo->prepare("INSERT INTO tags (name, slug) VALUES (:n, :s)");
                    $ins->execute([':n' => $tagValue, ':s' => $slug]);
                    $tagId = (int)$pdo->lastInsertId();
                }
            }
            $pdo->prepare("INSERT IGNORE INTO item_tag (item_id, tag_id) VALUES (:i, :t)")
                ->execute([':i' => $id, ':t' => $tagId]);
        }

            $pdo->commit();

        // Reload
            $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            $mStmt = $pdo->prepare("SELECT * FROM media WHERE item_id = :id ORDER BY " . ($hasIsPrimary ? "is_primary DESC, " : "") . "upload_date DESC");
            $mStmt->execute([':id' => $id]);
            $mediaList = $mStmt->fetchAll();
            $nStmt = $pdo->prepare("SELECT narrative_id FROM item_narrative WHERE item_id = :id");
            $nStmt->execute([':id' => $id]);
            $linkedNarratives = $nStmt->fetchAll(PDO::FETCH_COLUMN);
            $tStmt = $pdo->prepare("SELECT tag_id FROM item_tag WHERE item_id = :id");
            $tStmt->execute([':id' => $id]);
            $linkedTags = $tStmt->fetchAll(PDO::FETCH_COLUMN);
            $allTags = $pdo->query("SELECT id, name FROM tags ORDER BY name ASC")->fetchAll();

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Item save failed: ' . $e->getMessage());
            $success = '';
            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : "Unable to save item right now. Please try again.";
        }
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
        <form id="item-form" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg border border-gray-200 shadow-sm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">

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

                <!-- TomSelect Tag Input -->
                <div>
                    <label class="label">
                        Tags / Hashtags
                        <span class="text-gray-400 font-normal">(type to create new or select existing)</span>
                    </label>
                    <select id="tag-select" name="tags[]" multiple placeholder="Add tags… e.g. steam, victorian" class="w-full">
                        <?php foreach ($allTags as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= in_array($t['id'], $linkedTags) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- ── Attach Media (tabbed) ────────────────────────── -->
            <div class="border-t border-gray-200">
                <!-- Tab buttons -->
                <div class="flex border-b border-gray-200 bg-gray-50" role="tablist">
                    <button type="button" onclick="showTab('tab-image')" id="btn-image"
                            class="tab-btn px-5 py-3 text-sm font-medium border-b-2 border-gray-900 text-gray-900">🖼 Image</button>
                    <button type="button" onclick="showTab('tab-pdf')" id="btn-pdf"
                            class="tab-btn px-5 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">📄 PDF</button>
                    <button type="button" onclick="showTab('tab-youtube')" id="btn-youtube"
                            class="tab-btn px-5 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">▶ YouTube</button>
                </div>

                <!-- Image tab -->
                <div id="tab-image" class="media-tab p-6 space-y-4">
                    <p class="text-xs text-gray-400">JPG · PNG · WebP · max 5 MB → auto-converted to WebP in 3 sizes</p>
                    <input type="file" name="media_upload" accept=".jpg,.jpeg,.png,.webp,.gif"
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="label">Caption</label><input type="text" name="media_caption" class="input"></div>
                        <div>
                            <label class="label">License</label>
                            <select name="media_license" class="input">
                                <option>Public Domain</option><option>CC BY 4.0</option><option>All Rights Reserved</option>
                            </select>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_primary" value="1" class="h-4 w-4 rounded border-gray-300">
                        <span class="text-sm text-gray-700">Set as <strong>Primary Image</strong> <span class="text-gray-400">(shown in search results)</span></span>
                    </label>
                </div>

                <!-- PDF tab -->
                <div id="tab-pdf" class="media-tab p-6 space-y-4 hidden">
                    <p class="text-xs text-gray-400">Upload a PDF document — max 20 MB. Visitors will be able to view or download it on the item page.</p>
                    <input type="file" name="pdf_upload" accept=".pdf,application/pdf"
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-red-100 file:text-red-700 hover:file:bg-red-200">
                    <div><label class="label">Caption / Document Title</label><input type="text" name="pdf_caption" class="input" placeholder="e.g. Original auction catalogue, 1887"></div>
                </div>

                <!-- YouTube tab -->
                <div id="tab-youtube" class="media-tab p-6 space-y-4 hidden">
                    <p class="text-xs text-gray-400">Paste any YouTube URL — standard, short (youtu.be), Shorts, or embed format.</p>
                    <div><label class="label">YouTube URL</label>
                        <input type="url" name="youtube_url" class="input" placeholder="https://www.youtube.com/watch?v=..."></div>
                    <div><label class="label">Caption</label>
                        <input type="text" name="youtube_caption" class="input" placeholder="e.g. Museum documentary, 2022"></div>
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
            <?php $mType = $m['media_type'] ?? 'image'; ?>
            <div class="bg-white border <?= !empty($m['is_primary']) ? 'border-blue-500 ring-1 ring-blue-500' : 'border-gray-200' ?> rounded-lg overflow-hidden shadow-sm">
                <?php if (!empty($m['is_primary'])): ?>
                    <div class="bg-blue-600 text-white text-[10px] font-bold px-2 py-1 text-center tracking-widest">PRIMARY</div>
                <?php endif; ?>

                <?php if ($mType === 'youtube'): ?>
                    <!-- YouTube preview -->
                    <div class="relative h-36 bg-black">
                        <img src="https://img.youtube.com/vi/<?= htmlspecialchars($m['file_path']) ?>/mqdefault.jpg"
                             class="w-full h-full object-cover opacity-80">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="bg-red-600 text-white text-xs font-bold px-2 py-1 rounded">▶ YouTube</span>
                        </div>
                    </div>
                <?php elseif ($mType === 'pdf'): ?>
                    <!-- PDF preview -->
                    <div class="h-36 bg-red-50 flex flex-col items-center justify-center gap-1">
                        <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        <span class="text-xs text-red-500 font-medium">PDF Document</span>
                        <a href="<?= MediaProcessor::url($m['file_path'], 'display', 'pdf', $storage ?? null) ?>" target="_blank"
                           class="text-xs text-blue-600 hover:underline">Open PDF</a>
                    </div>
                <?php else: ?>
                    <!-- Image preview -->
                    <div class="h-36 bg-gray-100">
                        <img src="<?= MediaProcessor::url($m['file_path'], 'thumbs', 'image', $storage ?? null) ?>"
                             onerror="this.src='<?= MediaProcessor::url($m['file_path'], 'display', 'image', $storage ?? null) ?>'"
                             class="object-cover w-full h-full" alt="thumbnail">
                    </div>
                <?php endif; ?>

                <div class="p-3 text-xs space-y-0.5 text-gray-500">
                    <p class="font-mono break-all"><?= htmlspecialchars($m['file_path']) ?></p>
                    <?php if (!empty($m['caption'])): ?><p class="text-gray-700"><?= htmlspecialchars($m['caption']) ?></p><?php endif; ?>
                    <?php if (!empty($m['dimensions'])): ?><p>📐 <?= $m['dimensions'] ?></p><?php endif; ?>
                    <?php if (!empty($m['file_size'])): ?><p>💾 <?= round($m['file_size']/1024, 1) ?> KB</p><?php endif; ?>
                    <?php if ($mType === 'youtube' && !empty($m['youtube_url'])): ?>
                        <a href="<?= htmlspecialchars($m['youtube_url']) ?>" target="_blank" class="text-blue-500 hover:underline">View on YouTube ↗</a>
                    <?php endif; ?>

                    <?php if ($hasIsPrimary && $mType === 'image' && empty($m['is_primary'])): ?>
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
                            <input type="hidden" name="action" value="make_primary">
                            <input type="hidden" name="primary_media_id" value="<?= (int) $m['id'] ?>">
                            <button type="submit" class="w-full px-3 py-1.5 rounded border border-blue-200 bg-blue-50 text-blue-700 font-medium hover:bg-blue-100 transition">
                                Make Primary
                            </button>
                        </form>
                    <?php endif; ?>

                    <label class="mt-2 inline-flex items-center gap-2 text-red-600 cursor-pointer">
                        <input type="checkbox" name="delete_media[]" value="<?= (int) $m['id'] ?>" form="item-form" class="h-4 w-4 rounded border-red-300 text-red-600 focus:ring-red-500">
                        <span class="font-medium">Delete this media on save</span>
                    </label>
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

new TomSelect('#tag-select', {
    plugins: ['remove_button'],
    create: true,
    placeholder: 'Add tags… e.g. steam, victorian',
    maxOptions: 200,
    persist: false,
    createFilter: function(input) { return input.trim().length >= 2; },
});

// Media upload tab switcher
function showTab(id) {
    document.querySelectorAll('.media-tab').forEach(el => el.classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');
    const labels = { 'tab-image': 'btn-image', 'tab-pdf': 'btn-pdf', 'tab-youtube': 'btn-youtube' };
    Object.values(labels).forEach(btn => {
        document.getElementById(btn).classList.remove('border-gray-900', 'text-gray-900');
        document.getElementById(btn).classList.add('border-transparent', 'text-gray-500');
    });
    const activeBtn = document.getElementById(labels[id]);
    activeBtn.classList.remove('border-transparent', 'text-gray-500');
    activeBtn.classList.add('border-gray-900', 'text-gray-900');
}
</script>

<?= renderAdminFooter(); ?>
