<?php
// modules/blog/blog_list.php
require_once __DIR__ . '/../../config/config.php';
global $pdo;

$pageTitle = 'Blog - ' . SITE_TITLE;
$currentMenu = 'blog';

// Pagination
$perPage = 9;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Count total published posts
$countStmt = $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'");
$totalResults = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalResults / $perPage);

// Fetch paginated public posts
$stmt = $pdo->prepare("
    SELECT bp.*, a.username as author_name 
    FROM blog_posts bp 
    LEFT JOIN admins a ON bp.author_id = a.id 
    WHERE bp.status = 'published' 
    ORDER BY bp.published_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch tags for these posts
$postIds = array_column($posts, 'id');
$tagsByPost = [];
if ($postIds) {
    $ph = implode(',', array_fill(0, count($postIds), '?'));
    $tStmt = $pdo->prepare("
        SELECT bpt.post_id, t.name, t.slug 
        FROM blog_post_tag bpt 
        INNER JOIN tags t ON bpt.tag_id = t.id 
        WHERE bpt.post_id IN ($ph)
    ");
    $tStmt->execute($postIds);
    foreach ($tStmt->fetchAll() as $row) {
        $tagsByPost[$row['post_id']][] = $row;
    }
}

ob_start();
?>
    <!-- Additional head for blog list -->
    <style>
        .blog-card {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        }
        .blog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
            border-color: rgba(255, 255, 255, 0.1);
        }
        .blog-image-wrapper {
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .blog-image {
            transition: transform 0.5s ease;
        }
        .blog-card:hover .blog-image {
            transform: scale(1.05);
        }
    </style>
<?php
$additionalHead = ob_start(); // Clear output buffering here so no unwanted output happens.

// Wait, the previous ob_start is correct.
ob_end_clean(); 
$additionalHead = ob_get_clean() ?? '';

// Actually, let's fix the buffer handling
ob_start();
?>
    <!-- Additional head for blog list -->
    <style>
        .blog-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            height: 100%;
        }
        .blog-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-color: #d1d5db;
        }
        .blog-image-wrapper {
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid #f3f4f6;
            background-color: #f3f4f6;
            aspect-ratio: 16 / 9;
            display: flex;
            align-items: center;
            justify-center;
        }
        .blog-image {
            transition: transform 0.4s ease;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .blog-card:hover .blog-image {
            transform: scale(1.05);
        }
    </style>
<?php
$additionalHead = ob_get_clean();

require_once ThemeManager::getHeader();
?>

<main class="min-h-screen py-10 px-4 sm:px-6 lg:px-8 bg-gray-50 flex-grow">
    <div class="max-w-7xl mx-auto">
        <header class="mb-10 text-center md:text-left border-b border-gray-200 pb-6">
            <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-2 leading-tight tracking-tight serif">Blog</h1>
            <p class="text-xl text-gray-500 max-w-2xl">News, updates, and stories from the collection.</p>
        </header>

        <?php if (empty($posts)): ?>
            <div class="text-center py-20 flex flex-col items-center justify-center bg-white rounded-xl border border-gray-200 shadow-sm">
                <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9.5L18.5 7M4 16h16M4 12h16M4 8h16"></path></svg>
                <h3 class="text-xl font-bold text-gray-900 mb-2">No posts yet</h3>
                <p class="text-gray-500 max-w-md mx-auto">Check back later for news and updates.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($posts as $post): ?>
                    <article class="blog-card group" onclick="window.location.href='<?= SITE_URL ?>/blog/<?= htmlspecialchars($post['slug']) ?>'">
                        <?php if ($post['featured_image']): ?>
                            <div class="blog-image-wrapper">
                                <img src="<?= SITE_URL ?>/uploads/<?= htmlspecialchars($post['featured_image']) ?>" 
                                     alt="<?= htmlspecialchars($post['title']) ?>" 
                                     class="blog-image">
                            </div>
                        <?php else: ?>
                            <div class="blog-image-wrapper">
                                <span class="text-gray-300 text-4xl m-auto"><i class="fas fa-newspaper"></i></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="p-6 flex-grow flex flex-col">
                            <div class="flex flex-wrap gap-2 mb-3">
                                <time datetime="<?= date('c', strtotime($post['published_at'])) ?>" class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-gray-500 uppercase">
                                    <?= date('M j, Y', strtotime($post['published_at'])) ?>
                                </time>
                            </div>
                            
                            <h2 class="text-xl font-bold text-gray-900 mb-3 leading-tight group-hover:text-blue-800 transition-colors line-clamp-2 serif">
                                <a href="<?= SITE_URL ?>/blog/<?= htmlspecialchars($post['slug']) ?>">
                                    <?= htmlspecialchars($post['title']) ?>
                                </a>
                            </h2>
                            
                            <?php $postTags = $tagsByPost[$post['id']] ?? []; ?>
                            <?php if ($postTags): ?>
                            <div class="flex flex-wrap gap-1.5 mb-4 mt-auto">
                                <?php foreach (array_slice($postTags, 0, 3) as $tag): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] bg-gray-100 text-gray-600 border border-gray-200">
                                        <span class="mr-0.5 text-gray-400">#</span><?= htmlspecialchars($tag['name']) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($postTags) > 3): ?>
                                    <span class="text-xs text-gray-400 font-medium">+<?= count($postTags) - 3 ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($post['excerpt']): ?>
                                <p class="text-gray-600 text-sm leading-relaxed mb-4 <?= $postTags ? 'mt-2' : 'mt-auto' ?> line-clamp-3">
                                    <?= htmlspecialchars($post['excerpt']) ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="pt-4 border-t border-gray-100 flex items-center justify-between <?= (!$postTags && !$post['excerpt']) ? 'mt-auto' : '' ?>">
                                <span class="text-sm font-medium text-gray-500">
                                    <?= htmlspecialchars($post['author_name'] ?: 'Admin') ?>
                                </span>
                                <span class="text-blue-600 text-sm font-medium flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity transform translate-x-1 group-hover:translate-x-0">
                                    Read <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php 
                $currentPage = $page; 
                require ThemeManager::getTemplatePath('partials/pagination.php'); 
            ?>
        <?php endif; ?>
    </div>
</main>

<?php require_once ThemeManager::getFooter(); ?>
