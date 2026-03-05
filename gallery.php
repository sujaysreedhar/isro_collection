<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/MediaProcessor.php';

// Fetch all visible images and youtube videos
$stmt = $pdo->prepare("
    SELECT m.file_path, m.media_type, m.youtube_url, i.id as item_id, i.title, i.reg_number
    FROM media m
    INNER JOIN items i ON m.item_id = i.id
    WHERE i.is_visible = 1 AND m.media_type IN ('image', 'youtube')
    ORDER BY m.upload_date DESC
");
$stmt->execute();
$mediaItems = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visual Gallery - <?= SITE_TITLE ?></title>
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

    <!-- Global Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="<?= SITE_URL ?>" class="text-2xl font-bold serif tracking-tight flex-shrink-0"><?= SITE_TITLE ?></a>
            <div class="flex-1 max-w-2xl ml-8 hidden md:block">
                <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" name="q" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md bg-gray-50 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-900 focus:border-gray-900 sm:text-sm" placeholder="Search the collections...">
                </form>
            </div>
            <nav class="hidden lg:flex space-x-8 ml-8 flex-shrink-0">
                <a href="<?= SITE_URL ?>/search.php" class="text-gray-500 hover:text-gray-900 font-medium text-sm">Explore</a>
                <a href="<?= SITE_URL ?>/gallery.php" class="text-gray-900 font-semibold text-sm border-b-2 border-gray-900">Gallery</a>
            </nav>
        </div>
    </header>

    <div class="flex-grow max-w-[1600px] mx-auto w-full px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-10 text-center md:text-left">
            <h1 class="text-4xl font-extrabold serif mb-4">Visual Gallery</h1>
            <p class="text-lg text-gray-600 max-w-3xl">A continuous stream of imagery and videos from our historical collections.</p>
        </div>

        <?php if (empty($mediaItems)): ?>
            <div class="text-center py-20 bg-white rounded-xl border border-gray-200">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No media found</h3>
                <p class="mt-1 text-sm text-gray-500">There are currently no visible images or videos in the collection.</p>
            </div>
        <?php else: ?>
            <div class="masonry-grid">
                <?php foreach ($mediaItems as $media): ?>
                    <a href="<?= SITE_URL ?>/item/<?= $media['item_id'] ?>" class="masonry-item group block relative bg-gray-100 rounded-lg overflow-hidden border border-gray-200 hover:shadow-xl transition-all duration-300">
                        <?php if ($media['media_type'] === 'image'): ?>
                            <?php 
                                $imgSrc = isset($storage) 
                                    ? $storage->url('display/' . $media['file_path']) 
                                    : SITE_URL . '/uploads/display/' . $media['file_path'];
                            ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($media['title']) ?>" class="w-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                        <?php elseif ($media['media_type'] === 'youtube'): ?>
                            <?php $ytId = MediaProcessor::extractYoutubeId($media['youtube_url']); ?>
                            <div class="relative w-full pb-[56.25%] bg-black">
                                <img src="https://img.youtube.com/vi/<?= htmlspecialchars($ytId) ?>/maxresdefault.jpg" onerror="this.src='https://img.youtube.com/vi/<?= htmlspecialchars($ytId) ?>/hqdefault.jpg'" class="absolute inset-0 w-full h-full object-cover opacity-80 group-hover:scale-105 transition-transform duration-500" loading="lazy">
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <div class="bg-red-600/90 text-white rounded-full p-3 shadow-lg group-hover:bg-red-600 transition-colors">
                                        <svg class="w-8 h-8 translate-x-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent p-6 translate-y-2 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 flex flex-col justify-end pointer-events-none">
                            <span class="text-xs font-bold tracking-wider text-white/80 uppercase mb-1"><?= htmlspecialchars($media['reg_number']) ?></span>
                            <h3 class="text-white font-medium text-sm leading-tight line-clamp-2"><?= htmlspecialchars($media['title']) ?></h3>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-auto">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 flex items-center justify-between">
            <p class="text-gray-400 text-sm">
                &copy; <?= date('Y') ?> <?= SITE_TITLE ?>. All rights reserved.
            </p>
            <div class="flex space-x-6 text-sm">
                <a href="<?= SITE_URL ?>/admin/" class="text-gray-400 hover:text-gray-900 transition">Admin Login</a>
            </div>
        </div>
    </footer>
</body>
</html>
