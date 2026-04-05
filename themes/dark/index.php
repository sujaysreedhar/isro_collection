<?php
// themes/dark/index.php
$pageTitle = SITE_TITLE;
$currentMenu = 'home';
$hideHeaderSearch = true;

ob_start();
?>
<style>
    .hero-dark {
        background-color: #0f172a;
        background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%231e293b' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
</style>
<?php
$additionalHead = ob_get_clean();
require_once ThemeManager::getHeader();
?>

    <!-- Hero Section -->
    <div class="relative overflow-hidden hero-dark border-b border-gray-800">
        <div class="max-w-7xl mx-auto">
            <div class="relative z-10 pb-8 sm:pb-16 md:pb-20 lg:max-w-2xl lg:w-full lg:pb-28 xl:pb-32 pt-10 sm:pt-16 lg:pt-20">
                <main class="mt-10 mx-auto max-w-7xl px-4 sm:mt-12 sm:px-6 md:mt-16 lg:mt-20 lg:px-8 xl:mt-28">
                    <div class="sm:text-center lg:text-left">
                        <h1 class="text-4xl tracking-tight font-extrabold sm:text-5xl md:text-6xl">
                            <span class="block text-white xl:inline">Discover history in the</span>
                            <span class="block text-gray-400 xl:inline"><?= SITE_TITLE ?></span>
                        </h1>
                        <p class="mt-3 text-base text-gray-400 sm:mt-5 sm:text-lg sm:max-w-xl sm:mx-auto md:mt-5 md:text-xl lg:mx-0">
                            Explore thousands of items, narratives, and media from engineering marvels to significant historical artifacts.
                        </p>
                        <div class="mt-8 sm:flex sm:justify-center lg:justify-start">
                            <form action="<?= SITE_URL ?>/search.php" method="GET" class="w-full sm:max-w-lg relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                </div>
                                <input type="text" name="q" class="block w-full pl-10 pr-12 sm:text-lg border-gray-700 rounded-md py-4 bg-gray-800 text-white placeholder-gray-500 focus:ring-purple-500 focus:border-purple-500 shadow-lg" placeholder="Search for items, dates, or stories...">
                                <button type="submit" class="absolute inset-y-0 right-0 px-4 text-white bg-purple-600 hover:bg-purple-700 rounded-r-md font-medium transition-colors">Search</button>
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
            <h2 class="text-3xl font-bold text-white">Recently Added Items</h2>
            <a href="<?= SITE_URL ?>/search.php" class="text-sm font-semibold text-gray-400 hover:text-white flex items-center">
                View All 
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
        </div>

        <?php if (count($featuredItems) > 0): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($featuredItems as $item): ?>
                    <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="group bg-gray-800 rounded-lg border border-gray-700 overflow-hidden hover:border-gray-600 hover:shadow-xl hover:shadow-purple-900/20 transition-all flex flex-col h-full">
                        <div class="relative h-64 bg-gray-900 flex items-center justify-center overflow-hidden">
                            <?php if (!empty($item['file_path'])): ?>
                                <?php $featuredImgUrl = isset($storage) ? $storage->url('display/' . $item['file_path']) : SITE_URL . '/uploads/display/' . $item['file_path']; ?>
                                <img src="<?= htmlspecialchars($featuredImgUrl) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-500">
                            <?php else: ?>
                                <svg class="w-12 h-12 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <?php endif; ?>
                            <div class="absolute bottom-0 left-0 bg-gray-900/90 backdrop-blur px-3 py-1 text-xs font-bold text-gray-300 tracking-wider">
                                <?= htmlspecialchars($item['reg_number']) ?>
                            </div>
                            <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('item_card_badge', $item); } ?>
                        </div>
                        <div class="p-6 flex flex-col flex-grow">
                            <h3 class="text-xl font-bold text-white mb-2 group-hover:text-purple-400 transition-colors line-clamp-2"><?= htmlspecialchars($item['title']) ?></h3>
                            <p class="text-sm text-gray-400 line-clamp-3 mb-3 flex-grow">
                                <?= htmlspecialchars(strip_tags($item['physical_description'] ?? 'No description available.')) ?>
                            </p>
                            <?php $cardTags = $featuredTags[$item['id']] ?? []; ?>
                            <?php if ($cardTags): ?>
                            <div class="flex flex-wrap gap-1 mt-auto">
                                <?php foreach (array_slice($cardTags, 0, 4) as $ct): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-700 text-gray-300">
                                        <span class="mr-0.5 text-gray-500">#</span><?= htmlspecialchars($ct['name']) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($cardTags) > 4): ?>
                                    <span class="text-xs text-gray-500">+<?= count($cardTags) - 4 ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-20 bg-gray-800 border border-gray-700 rounded-lg">
                <svg class="mx-auto h-12 w-12 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                <h3 class="mt-2 text-sm font-medium text-gray-300">No items available</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by importing data into your MySQL database.</p>
            </div>
        <?php endif; ?>

        <?php /* ═══════════ BROWSE BY CATEGORY ═══════════ */ ?>
        <?php if (!empty($homeCategories)): ?>
        <div class="mt-20">
            <div class="flex items-center justify-between mb-8 border-b border-gray-800 pb-4">
                <h2 class="text-3xl font-bold text-white">Browse by Category</h2>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach ($homeCategories as $cat): ?>
                <a href="<?= SITE_URL ?>/search.php?category_ids[]=<?= (int)$cat['id'] ?>" class="group block">
                    <div class="relative aspect-video rounded-xl overflow-hidden border border-gray-700 bg-gray-800 hover:border-purple-500 hover:shadow-xl transition-all duration-300">
                        <img src="<?= SITE_URL ?>/uploads/categories/<?= htmlspecialchars($cat['image_path']) ?>" 
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" alt="<?= htmlspecialchars($cat['name']) ?>">
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-900/90 via-gray-900/20 to-transparent"></div>
                        <div class="absolute inset-0 p-4 flex flex-col justify-end text-center sm:text-left">
                            <h4 class="text-white font-bold text-sm sm:text-base"><?= htmlspecialchars($cat['name']) ?></h4>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Modular Sections Injected via Hook -->
        <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('home_page_sections'); } ?>
    </main>

<?php require_once ThemeManager::getFooter(); ?>
