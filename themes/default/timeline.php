<?php
$pageTitle = 'Timeline - ' . SITE_TITLE;
$currentMenu = 'timeline';

// We get $timelineData which is an array keyed by year_start, values are arrays of items.
require_once ThemeManager::getHeader();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-10 pb-5 border-b border-gray-200">
        <h1 class="text-4xl font-bold serif text-gray-900 mb-3">Timeline</h1>
        <p class="text-lg text-gray-600">Explore the collection chronologically.</p>
    </div>

    <?php if (empty($timelineData)): ?>
        <div class="text-center py-20 bg-gray-50 rounded-lg border border-gray-200">
            <h3 class="text-xl font-medium text-gray-900">No items available</h3>
            <p class="mt-2 text-gray-500">There are no items with a recorded start year.</p>
        </div>
    <?php else: ?>
        <div class="relative wrap overflow-hidden p-4 sm:p-10 h-full">
            <div class="absolute border-opacity-20 border-gray-700 h-full border-l-2" style="left: 50%; transform: translateX(-50%);"></div>

            <?php 
            $isRight = false; 
            foreach ($timelineData as $year => $items): 
            ?>
                <!-- Year Marker -->
                <div class="mb-10 flex justify-center items-center w-full relative z-20">
                    <div class="bg-gray-800 text-white font-bold px-4 py-1.5 rounded-full shadow-md text-sm cursor-default">
                        <?= htmlspecialchars($year) ?>
                    </div>
                </div>

                <?php foreach ($items as $item): ?>
                    <div class="mb-8 flex justify-between items-center w-full <?= $isRight ? 'flex-row-reverse' : '' ?>">
                        <div class="order-1 w-5/12"></div>
                        <div class="z-20 flex items-center order-1 bg-gray-800 shadow-xl w-3 h-3 rounded-full"></div>
                        <!-- Card -->
                        <div class="order-1 bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow w-5/12 overflow-hidden flex flex-col md:flex-row">
                            <?php if (!empty($item['preview_file_path'])): ?>
                                <div class="md:w-1/3 bg-gray-100 flex-shrink-0 border-b md:border-b-0 md:border-r border-gray-200">
                                    <img src="<?= SITE_URL ?>/uploads/display/<?= rawurlencode($item['preview_file_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-cover">
                                </div>
                            <?php endif; ?>
                            
                            <div class="p-5 flex-1 flex flex-col justify-center">
                                <span class="text-xs text-gray-400 font-bold mb-1"><?= htmlspecialchars($item['reg_number']) ?></span>
                                <h3 class="font-bold text-lg text-gray-900 mb-1 leading-tight"><a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="hover:text-blue-700 transition-colors"><?= htmlspecialchars($item['title']) ?></a></h3>
                                <p class="text-sm text-gray-600 line-clamp-2 mt-1"><?= htmlspecialchars(strip_tags($item['physical_description'] ?? '')) ?></p>
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
                .order-1.bg-white { margin-left: 1.5rem; width: calc(100% - 2rem); flex-direction: column !important; }
                .order-1.bg-white .md\:w-1\/3 { width: 100%; height: 150px; border-right: none; border-bottom: 1px solid #e5e7eb; }
            }
        </style>
    <?php endif; ?>
</div>

<?php require_once ThemeManager::getFooter(); ?>
