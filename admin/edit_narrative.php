<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$narrative = ['title' => '', 'content_body' => ''];
$linkedItemIds = [];
$error = '';
$success = '';

// Load existing narrative and its linked items
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM narratives WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        $narrative = $fetched;
        // Fetch linked item IDs
        $liStmt = $pdo->prepare("SELECT i.id, i.title FROM items i INNER JOIN item_narrative inv ON i.id = inv.item_id WHERE inv.narrative_id = :id");
        $liStmt->execute([':id' => $id]);
        $linkedItems = $liStmt->fetchAll();
        $linkedItemIds = array_column($linkedItems, 'id');
    } else {
        $error = "Story not found.";
        $id = 0;
    }
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }

    $title        = trim($_POST['title'] ?? '');
    $content_body = trim($_POST['content_body'] ?? '');
    $selectedItems = $_POST['related_items'] ?? [];

    if (empty($title) || empty($content_body)) {
        $error = "Title and content body are required.";
    } else {
        try {
            $pdo->beginTransaction();

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE narratives SET title = :title, content_body = :content_body WHERE id = :id");
                $stmt->execute([':title' => $title, ':content_body' => $content_body, ':id' => $id]);
                $success = "Story updated successfully.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO narratives (title, content_body) VALUES (:title, :content_body)");
                $stmt->execute([':title' => $title, ':content_body' => $content_body]);
                $id = $pdo->lastInsertId();
                $success = "Story created successfully.";
            }

            // Re-sync item_narrative pivot table
            $pdo->prepare("DELETE FROM item_narrative WHERE narrative_id = :id")->execute([':id' => $id]);
            if (!empty($selectedItems) && is_array($selectedItems)) {
                $linkStmt = $pdo->prepare("INSERT INTO item_narrative (item_id, narrative_id) VALUES (:item_id, :narrative_id)");
                foreach ($selectedItems as $itemId) {
                    $itemId = (int)$itemId;
                    if ($itemId > 0) {
                        $linkStmt->execute([':item_id' => $itemId, ':narrative_id' => $id]);
                    }
                }
            }

            $pdo->commit();

            // Reload linked IDs
            $liStmt = $pdo->prepare("SELECT i.id, i.title FROM items i INNER JOIN item_narrative inv ON i.id = inv.item_id WHERE inv.narrative_id = :id");
            $liStmt->execute([':id' => $id]);
            $linkedItems = $liStmt->fetchAll();
            $linkedItemIds = array_column($linkedItems, 'id');
            $narrative['title'] = $title;
            $narrative['content_body'] = $content_body;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Narrative save failed: ' . $e->getMessage());
            $error = "Unable to save story right now. Please try again.";
        }
    }
}

echo renderAdminHeader($id > 0 ? "Edit Story" : "New Story");
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--default .select2-selection--multiple {
        border: 1px solid #d1d5db;
        border-radius: 6px;
        min-height: 40px;
        padding: 4px;
    }
    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: #111827;
        box-shadow: 0 0 0 1px #111827;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background: #111827;
        border: none;
        color: #fff;
        border-radius: 4px;
        padding: 2px 8px;
        font-size: 12px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #9ca3af;
        margin-right: 4px;
    }
    .ql-container { font-size: 16px; font-family: 'Georgia', serif; }
    .ql-editor { min-height: 300px; line-height: 1.8; }
</style>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 leading-tight"><?= $id > 0 ? 'Edit Story' : 'New Story' ?></h1>
        <?php if ($id > 0): ?>
            <p class="text-sm text-gray-500 mt-1">Last ID: <strong>#<?= $id ?></strong></p>
        <?php endif; ?>
    </div>
    <a href="narratives.php" class="text-gray-600 hover:text-gray-900 text-sm font-medium">&larr; Back to Stories</a>
</div>

<?php if ($error): ?>
<div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
    
    <!-- Blog Editor Column (wide) -->
    <div class="xl:col-span-2">
        <form method="POST" action="" id="narrative-form" class="bg-white rounded-lg border border-gray-200 shadow-sm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
            <div class="p-6 space-y-6">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Story Title *</label>
                    <input type="text" id="title" name="title"
                           value="<?= htmlspecialchars($narrative['title'] ?? '') ?>" required
                           class="w-full border border-gray-300 rounded-md px-3 py-2 outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 shadow-sm text-lg font-semibold"
                           placeholder="e.g., The Industrial Revolution in Victoria">
                </div>

                <!-- Quill Rich Text Editor -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Content Body *</label>
                    <div id="quill-editor" class="border border-gray-300 rounded-md"><?= $id > 0 ? htmlspecialchars_decode(htmlspecialchars($narrative['content_body'] ?? '')) : '' ?></div>
                    <!-- Hidden textarea synced with Quill -->
                    <textarea id="content_body" name="content_body" class="hidden"><?= htmlspecialchars($narrative['content_body'] ?? '') ?></textarea>
                </div>

                <!-- Related Items Select2 -->
                <div>
                    <label for="related-items-select" class="block text-sm font-medium text-gray-700 mb-1">
                        Tag Related Items
                        <span class="text-gray-400 font-normal ml-1">(Search and select artifacts to link to this story)</span>
                    </label>
                    <select id="related-items-select" name="related_items[]" multiple class="w-full">
                        <?php if (!empty($linkedItems)): ?>
                            <?php foreach ($linkedItems as $li): ?>
                                <option value="<?= $li['id'] ?>" selected><?= htmlspecialchars($li['title']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">These items will show "Related Story" links on their public detail page.</p>
                </div>
            </div>

            <div class="p-6 border-t border-gray-200 bg-gray-50 rounded-b-lg flex justify-end gap-3">
                <a href="narratives.php" class="px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-5 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900">
                    <?= $id > 0 ? 'Update Story' : 'Publish Story' ?>
                </button>
            </div>
        </form>
    </div>
    
    <!-- Status / Preview Sidebar Column -->
    <div class="xl:col-span-1 space-y-6">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-3">Story Status</h3>
            <dl class="divide-y divide-gray-100 text-sm">
                <div class="py-2 flex justify-between">
                    <dt class="text-gray-500">Story ID</dt>
                    <dd class="text-gray-900 font-medium"><?= $id > 0 ? '#' . $id : 'New' ?></dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-gray-500">Linked Items</dt>
                    <dd class="text-gray-900 font-medium"><?= count($linkedItemIds) ?></dd>
                </div>
            </dl>
        </div>
        
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-5 text-sm text-amber-800">
            <h4 class="font-semibold mb-2">How Linking Works</h4>
            <p>When you tag an item to this story, visitors on the item's public detail page will see a "Related Stories" section linking to this narrative. This populates the <code class="bg-amber-100 px-1 rounded font-mono text-xs">item_narrative</code> pivot table.</p>
        </div>
    </div>
</div>

<!-- Select2 + jQuery + Quill -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
const AJAX_URL = '<?= SITE_URL ?>/admin/ajax.php';

// Quill WYSIWYG editor
const quill = new Quill('#quill-editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            [{ 'header': [2, 3, false] }],
            ['bold', 'italic', 'underline', 'blockquote'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            ['link'],
            ['clean']
        ]
    },
    placeholder: 'Write the full story here...'
});

// Sync Quill to hidden textarea before submit
document.getElementById('narrative-form').addEventListener('submit', function() {
    document.getElementById('content_body').value = quill.root.innerHTML;
});

// Select2 with AJAX search for items
$('#related-items-select').select2({
    placeholder: 'Search for items by title...',
    minimumInputLength: 2,
    ajax: {
        url: AJAX_URL,
        dataType: 'json',
        delay: 250,
        data: params => ({ action: 'search_items', q: params.term }),
        processResults: data => ({ results: data.results })
    }
});
</script>

<?= renderAdminFooter(); ?>
