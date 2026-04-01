<?php
// modules/blog/post_detail.php
require_once __DIR__ . '/../../config/config.php';
global $pdo;

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    http_response_code(404);
    require_once ThemeManager::getTemplatePath('404.php'); // Or a generic error
    exit;
}

// Fetch post
$stmt = $pdo->prepare("
    SELECT bp.*, a.username as author_name 
    FROM blog_posts bp 
    LEFT JOIN admins a ON bp.author_id = a.id 
    WHERE bp.slug = ? AND bp.status = 'published'
");
$stmt->execute([$slug]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    require_once ThemeManager::getTemplatePath('404.php');
    exit;
}

// Fetch tags
$tStmt = $pdo->prepare("
    SELECT t.name, t.slug 
    FROM blog_post_tag bpt 
    INNER JOIN tags t ON bpt.tag_id = t.id 
    WHERE bpt.post_id = ?
");
$tStmt->execute([$post['id']]);
$postTags = $tStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = htmlspecialchars($post['title']) . ' - Blog - ' . SITE_TITLE;
$currentMenu = 'blog';

ob_start();
?>
    <style>
        .blog-content h2 { font-size: 1.875rem; font-weight: 700; margin-top: 2.5rem; margin-bottom: 1.25rem; color: #111827; }
        .blog-content h3 { font-size: 1.5rem; font-weight: 600; margin-top: 2rem; margin-bottom: 1rem; color: #374151; }
        .blog-content p { margin-bottom: 1.5rem; color: #4b5563; line-height: 1.8; }
        .blog-content a { color: #2563eb; text-decoration: none; border-bottom: 1px solid transparent; transition: border-color 0.2s; }
        .blog-content a:hover { border-color: #2563eb; }
        .blog-content ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1.5rem; color: #4b5563; }
        .blog-content ol { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 1.5rem; color: #4b5563; }
        .blog-content li { margin-bottom: 0.5rem; }
        .blog-content blockquote { border-left: 4px solid #3b82f6; padding-left: 1rem; margin-left: 0; margin-bottom: 1.5rem; font-style: italic; color: #6b7280; }
        .blog-content img { max-width: 100%; height: auto; border-radius: 0.5rem; margin-bottom: 1.5rem; }
    </style>
<?php
$additionalHead = ob_get_clean();

require_once ThemeManager::getHeader();
?>

    <div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-10 flex flex-col md:flex-row gap-8">
        
        <!-- Main Content Area -->
        <main class="flex-1 min-w-0">
            <!-- Breadcrumbs -->
            <nav class="flex text-sm text-gray-500 mb-8" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center"><a href="<?= SITE_URL ?>/blog" class="hover:text-gray-900">Blog</a></li>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                            <span class="text-gray-800 line-clamp-1"><?= htmlspecialchars($post['title']) ?></span>
                        </div>
                    </li>
                </ol>
            </nav>

            <!-- Blog Post Layout -->
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-12">
                
                <!-- Left Column: Media & Content -->
                <div class="xl:col-span-7 border border-gray-200 bg-white">
                    <div class="bg-white rounded-t shadow-sm overflow-hidden">
                        <div class="relative bg-gray-100 flex items-center justify-center">
                            <!-- Featured Image -->
                            <?php if ($post['featured_image']): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-auto">
                            <?php else: ?>
                                <div class="flex items-center justify-center h-[400px] w-full text-gray-400 flex-col">
                                    <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <span>No Image Available</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="p-8">
                        <div class="blog-content prose prose-gray prose-lg max-w-none text-gray-700">
                            <?= $post['content'] ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Info & Specs -->
                <div class="xl:col-span-5 border-t xl:border-t-0 p-6 xl:p-0">
                    <div class="mb-2 text-sm text-gray-500 font-medium">
                        Posted by <?= htmlspecialchars($post['author_name'] ?: 'Admin') ?>
                    </div>
                    
                    <h1 class="text-4xl font-bold mb-4 leading-tight serif"><?= htmlspecialchars($post['title']) ?></h1>

                    <?php if ($postTags): ?>
                    <div class="flex flex-wrap gap-2 mb-6">
                        <?php foreach ($postTags as $tag): ?>
                            <a href="<?= SITE_URL ?>/search.php?tag=<?= htmlspecialchars($tag['slug']) ?>"
                               class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-800 hover:text-white transition-colors duration-200">
                                <span class="mr-1 text-gray-400">#</span><?= htmlspecialchars($tag['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="prose prose-gray mb-8 serif text-lg leading-relaxed text-gray-700">
                        <p>
                            <?= nl2br(htmlspecialchars($post['excerpt'] ?? '')) ?>
                        </p>
                    </div>

                    <!-- Structured Specifications Table -->
                    <div class="border-t border-gray-200 pt-6 mb-10">
                        <h3 class="text-lg font-bold mb-4">Post Details</h3>
                        <dl class="divide-y divide-gray-200 text-sm">
                            <div class="py-3 flex justify-between">
                                <dt class="text-gray-500 font-medium w-1/3">Published</dt>
                                <dd class="text-gray-900 w-2/3 text-right">
                                    <?= date('M j, Y', strtotime($post['published_at'])) ?>
                                </dd>
                            </div>
                            
                            <div class="py-3 flex justify-between">
                                <dt class="text-gray-500 font-medium w-1/3">Author</dt>
                                <dd class="text-gray-900 w-2/3 text-right">
                                    <?= htmlspecialchars($post['author_name'] ?: 'Admin') ?>
                                </dd>
                            </div>
                            
                            <div class="py-3 flex flex-col sm:flex-row justify-between">
                                <dt class="text-gray-500 font-medium w-full sm:w-1/3 mb-1 sm:mb-0">Format</dt>
                                <dd class="text-gray-900 w-full sm:w-2/3 sm:text-right">Blog Article</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- How to Cite -->
                    <?php
                        // Generate Citation
                        $citationUrl = SITE_URL . "/blog/" . htmlspecialchars($post['slug']);
                        $citation = SITE_TITLE . ". (n.d.). \"" . htmlspecialchars($post['title']) . ".\" Retrieved " . date('F j, Y') . ", from " . $citationUrl;
                    ?>
                    <div class="border border-gray-200 rounded p-4 bg-white mt-10">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-2">How to Cite</h3>
                        <div class="bg-gray-50 p-3 border border-gray-200 font-mono text-xs text-gray-800 break-words cursor-text select-all">
                            <?= $citation ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </main>
    </div>

<?php require_once ThemeManager::getFooter(); ?>
