<?php
// modules/user_galleries/view_gallery.php
require_once __DIR__ . '/../../config/config.php';

$token = $_GET['token'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM user_galleries WHERE share_token = ?");
$stmt->execute([$token]);
$gallery = $stmt->fetch();

if (!$gallery) {
    http_response_code(404);
    die("Gallery not found or token is invalid.");
}

// Check if the current user is the owner
$userToken = $_COOKIE['gallery_user_token'] ?? null;
$isOwner = ($userToken === $gallery['user_token']);

// Fetch items
$itemStmt = $pdo->prepare("
    SELECT i.*, 
        (SELECT file_path FROM media WHERE item_id = i.id AND media_type = 'image' ORDER BY id ASC LIMIT 1) as main_image
    FROM items i
    JOIN user_gallery_items ugi ON i.id = ugi.item_id
    WHERE ugi.gallery_id = ? AND i.is_visible = 1
    ORDER BY ugi.added_at DESC
");
$itemStmt->execute([$gallery['id']]);
$items = $itemStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($gallery['title']) ?> - <?= SITE_TITLE ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        h1, h2, h3, h4, .serif { font-family: 'Playfair Display', serif; color: #111827; }
        
        .masonry-grid {
            column-count: 1;
            column-gap: 1.5rem;
        }
        @media (min-width: 640px) { .masonry-grid { column-count: 2; } }
        @media (min-width: 1024px) { .masonry-grid { column-count: 3; } }
        @media (min-width: 1280px) { .masonry-grid { column-count: 4; } }
        
        .masonry-item {
            break-inside: avoid;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body class="text-gray-800 antialiased flex flex-col min-h-screen">

    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="<?= SITE_URL ?>" class="text-2xl font-bold serif tracking-tight flex-shrink-0"><?= SITE_TITLE ?></a>
            <?php renderFrontendNav('user_galleries'); ?>
        </div>
    </header>

    <div class="flex-grow max-w-[1600px] mx-auto w-full px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-10 text-center md:text-left flex flex-col md:flex-row justify-between items-start md:items-end gap-6">
            <div>
                <p class="text-sm text-gray-500 font-medium uppercase tracking-wider mb-2">Curated Gallery</p>
                <h1 class="text-4xl font-extrabold serif mb-4"><?= htmlspecialchars($gallery['title']) ?></h1>
                <?php if ($gallery['description']): ?>
                    <p class="text-lg text-gray-600 max-w-3xl"><?= nl2br(htmlspecialchars($gallery['description'])) ?></p>
                <?php endif; ?>
            </div>
            
            <div class="flex items-center gap-3">
                <button onclick="copyLink()" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                    Copy Link
                </button>
            </div>
        </div>

        <?php if (empty($items)): ?>
            <div class="text-center py-20 bg-white rounded-xl border border-gray-200">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Gallery is empty</h3>
                <p class="mt-1 text-sm text-gray-500">There are no items in this gallery yet.</p>
                <?php if ($isOwner): ?>
                <div class="mt-6">
                    <a href="<?= SITE_URL ?>/search.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800">Explore Collection</a>
                </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="masonry-grid">
                <?php foreach ($items as $item): ?>
                    <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="masonry-item group block relative bg-white rounded-lg overflow-hidden border border-gray-200 hover:shadow-xl transition-all duration-300">
                        <?php if ($item['main_image']): ?>
                            <?php 
                                $imgSrc = isset($storage) 
                                    ? $storage->url('display/' . $item['main_image']) 
                                    // Fallback if storage not initialized correctly here
                                    : SITE_URL . '/uploads/display/' . $item['main_image'];
                            ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                        <?php else: ?>
                            <div class="w-full aspect-[4/3] bg-gray-100 flex items-center justify-center text-gray-400">
                                <span>No Image Available</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="p-4 bg-white">
                            <span class="text-xs font-bold tracking-wider text-gray-500 uppercase mb-1 block"><?= htmlspecialchars($item['reg_number']) ?></span>
                            <h3 class="text-gray-900 font-bold text-lg leading-tight line-clamp-2 md:mb-1"><?= htmlspecialchars($item['title']) ?></h3>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function copyLink() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                alert('Gallery link copied to clipboard!');
            });
        }
    </script>
</body>
</html>
