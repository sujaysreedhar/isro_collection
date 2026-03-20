<?php
// themes/dark/timeline.php
$pageTitle = 'Timeline - ' . SITE_TITLE;
$currentMenu = 'timeline';

require_once ThemeManager::getHeader();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-10 pb-5 border-b border-gray-700">
        <h1 class="text-4xl font-bold text-white mb-3">Timeline</h1>
        <p class="text-lg text-gray-400">Explore the collection chronologically.</p>
    </div>

    <?php if (empty($timelineData)): ?>
        <div class="text-center py-20 bg-gray-800 rounded-lg border border-gray-700">
            <h3 class="text-xl font-medium text-gray-300">No items available</h3>
            <p class="mt-2 text-gray-500">There are no items with a recorded start year.</p>
        </div>
    <?php else: ?>
        <div class="relative wrap overflow-hidden p-4 sm:p-10 h-full">
            <div class="absolute border-opacity-30 border-purple-400 h-full border-l-2" style="left: 50%; transform: translateX(-50%);"></div>

            <?php
            $isRight = false;
            foreach ($timelineData as $year => $items):
            ?>
                <div class="mb-10 flex justify-center items-center w-full relative z-20">
                    <div class="bg-purple-600 text-white font-bold px-4 py-1.5 rounded-full shadow-lg text-sm cursor-default">
                        <?= htmlspecialchars($year) ?>
                    </div>
                </div>

                <?php foreach ($items as $item): ?>
                    <div class="mb-8 flex justify-between items-center w-full <?= $isRight ? 'flex-row-reverse' : '' ?>">
                        <div class="order-1 w-5/12"></div>
                        <div class="z-20 flex items-center order-1 bg-purple-500 shadow-xl w-3 h-3 rounded-full ring-2 ring-purple-300/30"></div>
                        <div class="order-1 bg-gray-800 border border-gray-700 rounded-lg shadow-sm hover:shadow-lg hover:border-gray-600 transition-all w-5/12 overflow-hidden flex flex-col md:flex-row">
                            <?php if (!empty($item['preview_file_path'])): ?>
                                <div class="md:w-1/3 bg-gray-900 flex-shrink-0 border-b md:border-b-0 md:border-r border-gray-700">
                                    <img src="<?= SITE_URL ?>/uploads/display/<?= rawurlencode($item['preview_file_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-cover">
                                </div>
                            <?php endif; ?>
                            <div class="p-5 flex-1 flex flex-col justify-center">
                                <span class="text-xs text-gray-500 font-bold mb-1"><?= htmlspecialchars($item['reg_number']) ?></span>
                                <h3 class="font-bold text-lg text-white mb-1 leading-tight"><a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="hover:text-purple-400 transition-colors"><?= htmlspecialchars($item['title']) ?></a></h3>
                                <p class="text-sm text-gray-400 line-clamp-2 mt-1"><?= htmlspecialchars(strip_tags($item['physical_description'] ?? '')) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php $isRight = !$isRight; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>

        <style>
            @media (max-width: 768px) {
                .relative.wrap { padding-left: 20px; padding-right: 0; }
                .absolute.border-l-2 { left: 40px !important; transform: none !important; }
                .flex.justify-between { flex-direction: row !important; }
                .w-5\/12 { width: 100%; }
                .order-1:first-child { display: none; }
                .order-1.bg-gray-800 { margin-left: 1.5rem; width: calc(100% - 2rem); flex-direction: column !important; }
                .order-1.bg-gray-800 .md\:w-1\/3 { width: 100%; height: 150px; border-right: none; border-bottom: 1px solid #374151; }
            }
        </style>
    <?php endif; ?>
</div>

<?php require_once ThemeManager::getFooter(); ?>
