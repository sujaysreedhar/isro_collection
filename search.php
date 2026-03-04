<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/SearchEngine.php';

// Initialize the search engine with PDO
$searchEngine = new SearchEngine($pdo);

// Extract parameters
$params = [
    'q'           => $_GET['q'] ?? '',
    'category_id' => !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null,
    'has_images'  => isset($_GET['has_images']) && $_GET['has_images'] === '1'
];

// Execute Search
$searchData = $searchEngine->search($params);
$results = $searchData['results'];
$facets = $searchData['facets'];

// Helper to build URLs preserving current query state but toggling a specific filter
function buildFilterUrl($currentParams, $key, $value) {
    $newParams = $currentParams;
    
    if ($key === 'category_id') {
        if (isset($newParams[$key]) && $newParams[$key] == $value) {
            unset($newParams[$key]); // Toggle off if already selected
        } else {
            $newParams[$key] = $value; // Toggle on
        }
    }
    
    if ($key === 'has_images') {
        if (isset($newParams[$key]) && $newParams[$key] === '1') {
            unset($newParams[$key]); 
        } else {
            $newParams[$key] = '1';
        }
    }
    
    return 'search.php?' . http_build_query($newParams);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - <?= SITE_TITLE ?></title>
    <!-- Tailwind CSS included via CDN for template purposes -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        h1, h2, h3, h4, .serif { font-family: 'Playfair Display', serif; color: #111827; }
    </style>
</head>
<body class="text-gray-800 antialiased flex flex-col min-h-screen">

    <!-- Global Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center">
                <a href="<?= SITE_URL ?>" class="text-2xl font-bold serif tracking-tight"><?= SITE_TITLE ?></a>
            </div>
            <div class="flex-1 max-w-2xl ml-8">
                <form action="search.php" method="GET" class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <?php if (!empty($params['category_id'])): ?>
                        <input type="hidden" name="category_id" value="<?= htmlspecialchars($params['category_id']) ?>">
                    <?php endif; ?>
                    <?php if ($params['has_images']): ?>
                        <input type="hidden" name="has_images" value="1">
                    <?php endif; ?>
                    <input type="text" name="q" value="<?= htmlspecialchars($params['q']) ?>" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-gray-50 placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-gray-900 focus:border-gray-900 sm:text-sm transition duration-150 ease-in-out" placeholder="Search the collections...">
                </form>
            </div>
        </div>
    </header>

    <div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-10 flex flex-col md:flex-row gap-8">
        
        <!-- The Sidebar: Refine Search Facets -->
        <aside class="w-full md:w-64 flex-shrink-0">
            <div class="sticky top-24">
                <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 mb-4">Refine Search</h3>
                
                <!-- Active Filters Summary -->
                <?php if (!empty($params['q']) || !empty($params['category_id']) || $params['has_images']): ?>
                <div class="mb-6 bg-gray-100 p-3 rounded text-sm">
                    <h4 class="font-semibold text-gray-700 mb-2">Active Filters:</h4>
                    <div class="flex flex-wrap gap-2">
                        <?php if (!empty($params['q'])): ?>
                            <a href="search.php?<?= http_build_query(array_diff_key($params, ['q' => ''])) ?>" class="inline-flex items-center px-2 py-1 rounded-sm bg-gray-200 text-gray-800 hover:bg-gray-300 transition">
                                "<?= htmlspecialchars($params['q']) ?>" <span class="ml-1 font-bold">&times;</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($params['has_images']): ?>
                            <a href="<?= buildFilterUrl($params, 'has_images', '1') ?>" class="inline-flex items-center px-2 py-1 rounded-sm bg-gray-200 text-gray-800 hover:bg-gray-300 transition">
                                Has Images <span class="ml-1 font-bold">&times;</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Categories Facet -->
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-800 mb-2 border-b border-gray-200 pb-2">Category</h4>
                    <div class="space-y-2 mt-3">
                        <?php foreach ($facets['categories'] as $cat): ?>
                            <?php $isChecked = ($params['category_id'] == $cat['id']); ?>
                            <a href="<?= buildFilterUrl($params, 'category_id', $cat['id']) ?>" class="flex items-center group cursor-pointer text-gray-600 hover:text-gray-900">
                                <span class="w-4 h-4 inline-flex justify-center items-center border <?= $isChecked ? 'bg-gray-900 border-gray-900 text-white' : 'border-gray-300 bg-white' ?> rounded mr-2 group-hover:border-gray-500 transition-colors">
                                    <?php if ($isChecked): ?>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    <?php endif; ?>
                                </span>
                                <span class="text-sm"><?= htmlspecialchars($cat['name']) ?> <span class="text-gray-400 ml-1">(<?= $cat['facet_count'] ?>)</span></span>
                            </a>
                        <?php endforeach; ?>
                        
                        <?php if (empty($facets['categories'])): ?>
                            <p class="text-sm text-gray-400 italic">No categories match.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Media Facet -->
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-800 mb-2 border-b border-gray-200 pb-2">Media</h4>
                    <div class="space-y-2 mt-3">
                        <a href="<?= buildFilterUrl($params, 'has_images', '1') ?>" class="flex items-center group cursor-pointer text-gray-600 hover:text-gray-900">
                            <span class="w-4 h-4 inline-flex justify-center items-center border <?= $params['has_images'] ? 'bg-gray-900 border-gray-900 text-white' : 'border-gray-300 bg-white' ?> rounded mr-2 group-hover:border-gray-500 transition-colors">
                                <?php if ($params['has_images']): ?>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                <?php endif; ?>
                            </span>
                            <span class="text-sm">Has Images <span class="text-gray-400 ml-1">(<?= $facets['has_images'] ?>)</span></span>
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 min-w-0">
            
            <div class="mb-6 flex justify-between items-end border-b border-gray-200 pb-4">
                <h1 class="text-3xl font-bold serif">
                    <?php if (!empty($params['q'])): ?>
                        Search results for "<?= htmlspecialchars($params['q']) ?>"
                    <?php else: ?>
                        All Items
                    <?php endif; ?>
                </h1>
                <span class="text-gray-500 text-sm"><?= count($results) ?> result(s) found</span>
            </div>

            <!-- Item View -->
            <?php if (count($results) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($results as $item): ?>
                        <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="group block border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition bg-white flex flex-col">
                            <div class="h-48 bg-gray-100 flex items-center justify-center p-4">
                                <!-- Ideally, we fetch primary media here, but keeping it simple for the result grid -->
                                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                            <div class="p-4 flex flex-col flex-grow">
                                <div class="text-xs font-bold text-gray-500 mb-1"><?= htmlspecialchars($item['reg_number']) ?></div>
                                <h3 class="font-bold serif text-lg text-gray-900 group-hover:text-blue-800 transition line-clamp-2"><?= htmlspecialchars($item['title']) ?></h3>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-20 bg-white border border-gray-200 rounded-lg">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No matching items found</h3>
                    <p class="mt-1 text-sm text-gray-500">Try adjusting your search or clearing your filters.</p>
                    <div class="mt-6">
                        <a href="search.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
                            Clear all filters
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
        </main>
    </div>

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
