<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$category = ['name' => ''];
$error = '';
$success = '';

// Load existing category
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        $category = $fetched;
    } else {
        $error = "Category not found.";
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

    if (empty($name)) {
        $error = "Category name is required.";
    } else {
        try {
            if ($id > 0) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE categories SET name = :name WHERE id = :id");
                $stmt->execute([':name' => $name, ':id' => $id]);
                $success = "Category updated successfully.";
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
                $stmt->execute([':name' => $name]);
                $id = $pdo->lastInsertId();
                $success = "Category created successfully.";
            }
            $category['name'] = $name;
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                // Unique constraint violation
                $error = "A category with that name already exists.";
            } else {
                error_log('Category save failed: ' . $e->getMessage());
                $error = "Unable to save category right now.";
            }
        }
    }
}

echo renderAdminHeader($id > 0 ? "Edit Category - " . htmlspecialchars($category['name']) : "Create New Category");
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 leading-tight"><?= $id > 0 ? 'Edit Category' : 'New Category' ?></h1>
    </div>
    <a href="categories.php" class="text-gray-600 hover:text-gray-900 text-sm font-medium">&larr; Back to Categories</a>
</div>

<?php if ($error): ?>
    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<div class="max-w-2xl">
    <form method="POST" action="" class="bg-white rounded-lg border border-gray-200 shadow-sm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
        <div class="p-6">
            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Category Name *</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($category['name'] ?? '') ?>" required
                       class="w-full border border-gray-300 rounded-md px-3 py-2 outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900 shadow-sm sm:text-sm">
            </div>
        </div>

        <div class="p-6 border-t border-gray-200 rounded-b-lg flex justify-end gap-3 bg-gray-50">
            <a href="categories.php" class="px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
                <?= $id > 0 ? 'Update Category' : 'Save Category' ?>
            </button>
        </div>
    </form>
</div>

<?= renderAdminFooter(); ?>
