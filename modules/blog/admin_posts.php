<?php
// modules/blog/admin_posts.php

if (!defined('SITE_URL')) {
    exit('Direct access not permitted.');
}

// Ensure user is admin
// The module system handles authentication, but double-check if needed

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        $id = $_POST['id'] ?? null;
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        // default slug if empty
        if (empty($slug) && !empty($title)) {
           $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        }
        $excerpt = trim($_POST['excerpt'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        $author_id = $_SESSION['admin_id'] ?? null; // Assuming $_SESSION['admin_id'] exists
        
        // Handle Featured Image Upload
        $featured_image = $_POST['existing_image'] ?? '';
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/blog/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = time() . '_' . basename($_FILES['featured_image']['name']);
            $targetPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $targetPath)) {
                $featured_image = 'blog/' . $filename;
            } else {
                $error = "Failed to upload image.";
            }
        }

        if (empty($title) || empty($slug)) {
            $error = "Title and Slug are required.";
        } else {
            if ($status === 'published') {
                $published_at = date('Y-m-d H:i:s');
            } else {
                $published_at = null;
            }

            try {
                if ($id) {
                    // Update
                    global $pdo;
                    $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, slug = ?, excerpt = ?, content = ?, status = ?, featured_image = ?, published_at = COALESCE(published_at, ?) WHERE id = ?");
                    $stmt->execute([$title, $slug, $excerpt, $content, $status, $featured_image, $published_at, $id]);
                    $success = "Post updated successfully.";
                } else {
                    // Insert
                    global $pdo;
                    $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, excerpt, content, status, author_id, featured_image, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $slug, $excerpt, $content, $status, $author_id, $featured_image, $published_at]);
                    $id = $pdo->lastInsertId();
                    $success = "Post created successfully.";
                    $action = 'edit'; // Switch to edit view
                    $_GET['id'] = $id; // Set ID for edit view
                }
                if (isset($_POST['tags']) && is_array($_POST['tags'])) {
                    $stmt = $pdo->prepare("DELETE FROM blog_post_tag WHERE post_id = ?");
                    $stmt->execute([$id]);
                    if (!empty($_POST['tags'])) {
                        $ph = implode(',', array_fill(0, count($_POST['tags']), '(?, ?)'));
                        $values = [];
                        foreach ($_POST['tags'] as $tagId) {
                            $values[] = $id;
                            $values[] = (int)$tagId;
                        }
                        $stmt = $pdo->prepare("INSERT INTO blog_post_tag (post_id, tag_id) VALUES $ph");
                        $stmt->execute($values);
                    }
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                     $error = "A post with this slug already exists.";
                } else {
                     $error = "Database error: " . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
         $id = $_POST['id'] ?? null;
         if ($id) {
             global $pdo;
             $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
             $stmt->execute([$id]);
             $success = "Post deleted successfully.";
             $action = 'list';
         }
    }
}

// Views
if ($action === 'list') {
    // List Posts
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM blog_posts ORDER BY created_at DESC");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-white">Blog Posts</h2>
        <a href="?m=blog&action=create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">Create New Post</a>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-500/10 border border-green-500/20 text-green-400 p-4 rounded-lg mb-6"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="bg-gray-800/50 backdrop-blur-md rounded-xl border border-gray-700/50 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-900/50 border-b border-gray-700/50">
                    <th class="p-4 text-sm font-semibold text-gray-400">Title</th>
                    <th class="p-4 text-sm font-semibold text-gray-400">Status</th>
                    <th class="p-4 text-sm font-semibold text-gray-400">Date</th>
                    <th class="p-4 text-sm font-semibold text-gray-400 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700/50 text-sm">
                <?php foreach ($posts as $post): ?>
                <tr class="hover:bg-gray-700/20 transition-colors group">
                    <td class="p-4 text-gray-200 font-medium">
                        <?= htmlspecialchars($post['title']) ?>
                        <div class="text-xs text-gray-500 font-normal mt-1">/blog/<?= htmlspecialchars($post['slug']) ?></div>
                    </td>
                    <td class="p-4">
                        <?php if ($post['status'] === 'published'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-500/10 text-green-400 border border-green-500/20">Published</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-500/10 text-yellow-400 border border-yellow-500/20">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 text-gray-400">
                        <?= date('M j, Y', strtotime($post['created_at'])) ?>
                    </td>
                    <td class="p-4 text-right">
                        <div class="flex items-center justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="?m=blog&action=edit&id=<?= $post['id'] ?>" class="text-blue-400 hover:text-blue-300">Edit</a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $post['id'] ?>">
                                <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                            </form>
                            <?php if ($post['status'] === 'published'): ?>
                            <a href="<?= SITE_URL ?>/blog/<?= $post['slug'] ?>" target="_blank" class="text-gray-400 hover:text-white">View</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($posts)): ?>
                <tr>
                    <td colspan="4" class="p-8 text-center text-gray-500">No blog posts found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
} elseif ($action === 'create' || $action === 'edit') {
    // Form View
    $id = $_GET['id'] ?? null;
    $post = [
        'title' => '',
        'slug' => '',
        'excerpt' => '',
        'content' => '',
        'status' => 'draft',
        'featured_image' => ''
    ];

    if ($id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$post) {
            echo "<p class='text-red-500'>Post not found.</p>";
            return;
        }
        
        $stmt = $pdo->prepare("SELECT tag_id FROM blog_post_tag WHERE post_id = ?");
        $stmt->execute([$id]);
        $selectedTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $selectedTags = [];
    }

    global $pdo;
    $stmt = $pdo->query("SELECT * FROM tags ORDER BY name ASC");
    $allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      tinymce.init({
        selector: '#content-editor',
        plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount code',
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat | code',
        skin: 'oxide-dark',
        content_css: 'dark',
        height: 500,
        menubar: false,
        branding: false
      });
    </script>
    <div class="flex items-center gap-4 mb-6">
        <a href="?m=blog" class="text-gray-400 hover:text-white transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        </a>
        <h2 class="text-2xl font-bold text-white"><?= $id ? 'Edit Post' : 'Create New Post' ?></h2>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-lg mb-6"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-500/10 border border-green-500/20 text-green-400 p-4 rounded-lg mb-6"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= htmlspecialchars($id ?? '') ?>">
        
        <div class="xl:col-span-2 space-y-6">
            <!-- Content Card -->
            <div class="bg-gray-800/40 backdrop-blur-md rounded-2xl border border-gray-700/50 p-6 shadow-xl">
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-300 mb-2">Post Title <span class="text-red-400">*</span></label>
                        <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" required 
                               class="w-full bg-gray-900/60 border border-gray-700/50 rounded-xl px-4 py-3 text-white text-lg placeholder-gray-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                               placeholder="Enter your engaging title here...">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-300 mb-2">Post Content <span class="text-red-400">*</span></label>
                        <div class="rounded-xl overflow-hidden border border-gray-700/50">
                            <textarea id="content-editor" name="content"><?= htmlspecialchars($post['content']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Excerpt Card -->
            <div class="bg-gray-800/40 backdrop-blur-md rounded-2xl border border-gray-700/50 p-6 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <label class="block text-sm font-semibold text-gray-300">Excerpt / Summary</label>
                    <span class="text-xs text-gray-500 font-medium">Optional</span>
                </div>
                <textarea name="excerpt" rows="3" 
                          class="w-full bg-gray-900/60 border border-gray-700/50 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                          placeholder="A short summary of this post representing what readers will see on the blog list..."><?= htmlspecialchars($post['excerpt']) ?></textarea>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="xl:col-span-1 space-y-6">
            
            <!-- Publishing Control Card -->
            <div class="bg-gray-800/40 backdrop-blur-md rounded-2xl border border-gray-700/50 p-6 shadow-xl">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-200 uppercase tracking-wider mb-5">
                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                    Publishing
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Visibility Status</label>
                        <select name="status" class="w-full bg-gray-900/60 border border-gray-700/50 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-blue-500 transition-colors">
                            <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>>Draft - Hidden from public</option>
                            <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>Published - Visible to public</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Custom URL Slug</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 text-sm">/blog/</span>
                            <input type="text" name="slug" value="<?= htmlspecialchars($post['slug']) ?>" 
                                   class="w-full bg-gray-900/60 border border-gray-700/50 rounded-lg pl-12 pr-3 py-2.5 text-white text-sm focus:outline-none focus:border-blue-500 transition-colors"
                                   placeholder="my-post-title">
                        </div>
                        <p class="text-[10px] text-gray-500 mt-1.5">Leave blank to auto-generate from your title.</p>
                    </div>
                <div class="mt-6 pt-5 border-t border-gray-700/50">
                    <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white px-4 py-3 rounded-xl font-bold transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5 flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                        <?= $id ? 'Save Changes' : 'Publish Post' ?>
                    </button>
                </div>
            </div>

            <!-- Tags Card -->
            <div class="bg-gray-800/40 backdrop-blur-md rounded-2xl border border-gray-700/50 p-6 shadow-xl">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-200 uppercase tracking-wider mb-4">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                    Tags & Taxonomy
                </h3>
                
                <div>
                    <select name="tags[]" multiple class="w-full bg-gray-900/60 border border-gray-700/50 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-emerald-500 h-40 scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-transparent">
                        <?php foreach ($allTags as $tag): ?>
                            <option value="<?= $tag['id'] ?>" <?= in_array($tag['id'], $selectedTags) ? 'selected' : '' ?> class="py-1 px-2 mb-1 rounded hover:bg-gray-700 <?= in_array($tag['id'], $selectedTags) ? 'bg-emerald-500/20 text-emerald-300' : '' ?>">
                                # <?= htmlspecialchars($tag['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[10px] text-gray-500 mt-2 text-center bg-gray-900/40 rounded p-1.5 border border-gray-700/50">Hold <kbd class="px-1 py-0.5 rounded bg-gray-800 border border-gray-600 mx-0.5">Ctrl</kbd> or <kbd class="px-1 py-0.5 rounded bg-gray-800 border border-gray-600 mx-0.5">Cmd</kbd> to select multiple tags.</p>
                </div>
            </div>
                    </div>
                    
            <!-- Featured Image Card -->
            <div class="bg-gray-800/40 backdrop-blur-md rounded-2xl border border-gray-700/50 p-6 shadow-xl">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-200 uppercase tracking-wider mb-4">
                    <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Featured Image
                </h3>
                
                <?php if ($post['featured_image']): ?>
                    <div class="mb-4 relative rounded-xl overflow-hidden border border-gray-700/50 shadow-lg group">
                        <img src="<?= SITE_URL ?>/uploads/<?= htmlspecialchars($post['featured_image']) ?>" class="w-full h-auto aspect-video object-cover transition-transform duration-500 group-hover:scale-105" alt="Featured Image">
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <input type="hidden" name="existing_image" value="<?= htmlspecialchars($post['featured_image']) ?>">
                    </div>
                <?php endif; ?>
                
                <div class="relative group cursor-pointer">
                    <div class="absolute inset-0 bg-purple-500/10 rounded-xl blur-md group-hover:bg-purple-500/20 transition-all"></div>
                    <div class="relative bg-gray-900/60 border-2 border-dashed border-gray-700 group-hover:border-purple-500/50 rounded-xl p-4 transition-colors">
                        <input type="file" name="featured_image" accept="image/*" class="w-full text-sm text-gray-400 file:cursor-pointer file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-purple-500/10 file:text-purple-300 hover:file:bg-purple-500/20 cursor-pointer">
                    </div>
                </div>
            </div>
            
        </div>
    </form>
    <?php
}
?>
