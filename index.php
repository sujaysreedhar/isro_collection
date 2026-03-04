<?php
// index.php
require_once __DIR__ . '/config/config.php';

// Fetch a few featured/recent items to display on the home page
$stmt = $pdo->prepare("
    SELECT i.*, m.file_path, m.caption 
    FROM items i
    LEFT JOIN media m ON i.id = m.item_id
    GROUP BY i.id
    ORDER BY i.id DESC
    LIMIT 6
");
$stmt->execute();
$featuredItems = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= HOME_PAGE_TITLE ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        h1, h2, h3, h4, .serif { font-family: 'Playfair Display', serif; color: #111827; }
        
        .hero-pattern {
            background-color: #ffffff;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23f3f4f6' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
    </style>
</head>
<body class="text-gray-800 antialiased flex flex-col min-h-screen">

    <!-- Global Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center">
                <a href="<?= SITE_URL ?>" class="text-2xl font-bold serif tracking-tight"><?= SITE_TITLE ?></a>
            </div>
            <nav class="hidden md:flex space-x-8">
                <a href="<?= SITE_URL ?>/search.php" class="text-gray-500 hover:text-gray-900 font-medium">Explore Collections</a>
                <a href="#" class="text-gray-500 hover:text-gray-900 font-medium">About</a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <div class="relative bg-white overflow-hidden hero-pattern border-b border-gray-200">
        <div class="max-w-7xl mx-auto">
            <div class="relative z-10 pb-8 bg-white/90 backdrop-blur-sm sm:pb-16 md:pb-20 lg:max-w-2xl lg:w-full lg:pb-28 xl:pb-32 pt-10 sm:pt-16 lg:pt-20">
                <main class="mt-10 mx-auto max-w-7xl px-4 sm:mt-12 sm:px-6 md:mt-16 lg:mt-20 lg:px-8 xl:mt-28">
                    <div class="sm:text-center lg:text-left">
                        <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 sm:text-5xl md:text-6xl serif">
                            <span class="block xl:inline">Discover history in the</span>
                            <span class="block text-gray-600 xl:inline">Museum Collection</span>
                        </h1>
                        <p class="mt-3 text-base text-gray-500 sm:mt-5 sm:text-lg sm:max-w-xl sm:mx-auto md:mt-5 md:text-xl lg:mx-0">
                            Explore thousands of items, narratives, and media from engineering marvels to significant historical artifacts.
                        </p>
                        
                        <!-- Prominent Search -->
                        <div class="mt-8 sm:flex sm:justify-center lg:justify-start">
                            <form action="<?= SITE_URL ?>/search.php" method="GET" class="w-full sm:max-w-lg relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                </div>
                                <input type="text" name="q" class="focus:ring-gray-900 focus:border-gray-900 block w-full pl-10 pr-12 sm:text-lg border-gray-300 rounded-md py-4 shadow-lg" placeholder="Search for items, dates, or stories...">
                                <button type="submit" class="absolute inset-y-0 right-0 px-4 text-white bg-gray-900 hover:bg-gray-800 rounded-r-md font-medium transition-colors">
                                    Search
                                </button>
                            </form>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <!-- Featured Grid -->
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-16">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-3xl font-bold serif">Recently Added Items</h2>
            <a href="<?= SITE_URL ?>/search.php" class="text-sm font-semibold text-gray-600 hover:text-gray-900 flex items-center">
                View All 
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
        </div>

        <?php if (count($featuredItems) > 0): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($featuredItems as $item): ?>
                    <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="group bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow flex flex-col h-full">
                        <div class="relative h-64 bg-gray-100 flex items-center justify-center overflow-hidden">
                            <?php if (!empty($item['file_path'])): ?>
                                <img src="<?= htmlspecialchars($item['file_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-500">
                            <?php else: ?>
                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <?php endif; ?>
                            <div class="absolute bottom-0 left-0 bg-white/90 backdrop-blur px-3 py-1 text-xs font-bold text-gray-700 tracking-wider">
                                <?= htmlspecialchars($item['reg_number']) ?>
                            </div>
                        </div>
                        <div class="p-6 flex flex-col flex-grow">
                            <h3 class="text-xl font-bold serif text-gray-900 mb-2 group-hover:text-blue-800 transition-colors line-clamp-2"><?= htmlspecialchars($item['title']) ?></h3>
                            <p class="text-sm text-gray-600 line-clamp-3 mb-4 flex-grow">
                                <?= htmlspecialchars(strip_tags($item['physical_description'] ?? 'No description available.')) ?>
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-20 bg-white border border-gray-200 rounded-lg">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No items available</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by importing data into your MySQL database.</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white mt-auto py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center text-sm text-gray-400">
            <p>&copy; <?= date('Y') ?> <?= SITE_TITLE ?>. All rights reserved.</p>
            <div class="flex space-x-6">
                <a href="#" class="hover:text-white transition-colors">Privacy</a>
                <a href="#" class="hover:text-white transition-colors">Terms</a>
            </div>
        </div>
    </footer>
</body>
</html>
