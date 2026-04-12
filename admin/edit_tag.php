<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tag = ['name' => '', 'slug' => ''];
$error = '';
$success = '';

// Load existing tag
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM tags WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        $tag = $fetched;
    } else {
        $error = "Tag not found.";
        $id = 0;
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');

    if (empty($slug) && !empty($name)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    }

    if (empty($name)) {
        $error = "Tag name is required.";
    } else {
        try {
            if ($id > 0) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE tags SET name = :name, slug = :slug WHERE id = :id");
                $stmt->execute([':name' => $name, ':slug' => $slug, ':id' => $id]);
                $success = "Tag updated successfully.";
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO tags (name, slug) VALUES (:name, :slug)");
                $stmt->execute([':name' => $name, ':slug' => $slug]);
                $id = (int)$pdo->lastInsertId();
                $success = "Tag created successfully.";
            }
            
            if (class_exists('HookRegistry')) {
                HookRegistry::doAction('tag_saved', $id);
            }
            
            $tag['name'] = $name;
            $tag['slug'] = $slug;
            
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                // Unique constraint violation
                $error = "A tag with that slug already exists.";
            } else {
                error_log('Tag save failed: ' . $e->getMessage());
                $error = "Unable to save tag right now.";
            }
        }
    }
}

echo renderAdminHeader($id > 0 ? "Edit Tag - " . htmlspecialchars($tag['name']) : "Create New Tag");
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 leading-tight"><?= $id > 0 ? 'Edit Tag' : 'New Tag' ?></h1>
    </div>
    <a href="tags.php" class="text-gray-600 hover:text-gray-900 text-sm font-medium transition-colors inline-flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Tags
    </a>
</div>

<?php if ($error): ?>
    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl relative flex items-center gap-3">
        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl relative flex justify-between items-center">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
        <?php if (!isset($_GET['id'])): ?>
            <a href="edit_tag.php" class="text-xs font-bold uppercase tracking-wider bg-green-100 px-3 py-1.5 rounded-lg hover:bg-green-200 transition">Add Another</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="max-w-2xl">
    <form method="POST" action="" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
        <div class="p-8 space-y-6">
            <div>
                <label for="name" class="block text-sm font-semibold text-slate-700 mb-1.5">Tag Name *</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($tag['name'] ?? '') ?>" required
                       placeholder="e.g. Victorian Steam"
                       class="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 shadow-sm transition-all sm:text-sm">
                <p class="text-xs text-slate-400 mt-2 font-medium">Use a descriptive name. This appears as the text in the collection.</p>
            </div>

            <div>
                <label for="slug" class="block text-sm font-semibold text-slate-700 mb-1.5">URL Slug</label>
                <div class="flex">
                    <span class="inline-flex items-center px-4 rounded-l-xl border border-r-0 border-slate-200 bg-slate-50 text-slate-400 text-xs font-mono">
                        /tag/
                    </span>
                    <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($tag['slug'] ?? '') ?>"
                           placeholder="e.g. victorian-steam"
                           class="flex-1 border border-slate-200 rounded-r-xl px-4 py-3 outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 shadow-sm transition-all sm:text-sm">
                </div>
                <p class="text-xs text-slate-400 mt-2 font-medium italic">Auto-generated from the name if left blank. Used for clean URLs.</p>
            </div>
        </div>

        <div class="p-6 border-t border-slate-100 flex justify-end gap-3 bg-slate-50/50">
            <a href="tags.php" class="px-6 py-2.5 border border-slate-200 shadow-sm text-sm font-semibold rounded-xl text-slate-600 bg-white hover:bg-slate-50 transition-all hover:text-slate-900">Cancel</a>
            <button type="submit" class="px-6 py-2.5 border border-transparent shadow-md text-sm font-semibold rounded-xl text-white bg-slate-900 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-900 transition-all">
                <?= $id > 0 ? 'Update Tag' : 'Save Tag' ?>
            </button>
        </div>
    </form>
</div>

<?= renderAdminFooter(); ?>
