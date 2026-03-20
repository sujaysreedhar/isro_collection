<?php
$pageTitle = 'Timeline - ' . SITE_TITLE;
$currentMenu = 'timeline';

// We get $timelineData which is an array keyed by year_start, values are arrays of items.
require_once ThemeManager::getHeader();
?>

<div class="bg-slate-50 min-h-screen py-12 md:py-20 relative overflow-hidden">
    <!-- Decorative background elements -->
    <div class="absolute top-0 inset-x-0 h-[500px] bg-gradient-to-b from-modern-100/50 to-transparent pointer-events-none"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="text-center mb-16">
            <h1 class="text-4xl md:text-5xl font-extrabold text-slate-900 tracking-tight mb-4">Historical Timeline</h1>
            <p class="text-xl text-slate-500 max-w-2xl mx-auto">Take a journey through time and explore items chronologically.</p>
        </div>

        <?php if (empty($timelineData)): ?>
            <div class="text-center py-20 bg-white rounded-3xl border border-slate-200 border-dashed shadow-sm">
                <svg class="mx-auto h-16 w-16 text-slate-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <h3 class="text-xl font-medium text-slate-900">No timeline data available</h3>
                <p class="mt-2 text-slate-500">There are no items with a recorded start year to display on the timeline.</p>
            </div>
        <?php else: ?>
            <div class="relative wrap overflow-hidden p-4 sm:p-10 h-full">
                <!-- Vertical Line -->
                <div class="absolute border-opacity-20 border-modern-900 h-full border-l-2" style="left: 50%; transform: translateX(-50%);"></div>

                <?php 
                $isRight = false; 
                foreach ($timelineData as $year => $items): 
                ?>
                    <!-- Year Marker -->
                    <div class="mb-12 flex justify-center items-center w-full relative z-20">
                        <div class="bg-modern-600 font-bold text-white px-6 py-2 rounded-full shadow-lg border-4 border-white tracking-widest text-lg z-20 hover:scale-105 transition-transform cursor-default">
                            <?= htmlspecialchars($year) ?>
                        </div>
                    </div>

                    <?php foreach ($items as $item): ?>
                        <div class="mb-12 flex justify-between items-center w-full <?= $isRight ? 'flex-row-reverse' : '' ?>">
                            <div class="order-1 w-5/12"></div>
                            <div class="z-20 flex items-center order-1 bg-white border-4 border-modern-200 shadow-sm w-4 h-4 rounded-full">
                                <div class="w-full h-full bg-modern-500 rounded-full animate-pulse"></div>
                            </div>
                            <!-- Card -->
                            <div class="order-1 bg-white rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 border border-slate-100 w-5/12 overflow-hidden group hover:-translate-y-1">
                                <?php if (!empty($item['preview_file_path'])): ?>
                                    <div class="h-48 w-full bg-slate-100 overflow-hidden relative">
                                        <div class="absolute inset-0 bg-modern-900/10 group-hover:bg-transparent transition-colors z-10"></div>
                                        <img src="<?= SITE_URL ?>/uploads/display/<?= rawurlencode($item['preview_file_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                    </div>
                                <?php endif; ?>
                                
                                <div class="p-6">
                                    <span class="text-xs font-bold tracking-widest text-modern-500 uppercase mb-2 block"><?= htmlspecialchars($item['reg_number']) ?></span>
                                    <h3 class="font-bold text-xl text-slate-900 mb-2 leading-tight group-hover:text-modern-600 transition-colors">
                                        <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a>
                                    </h3>
                                    <?php if ($item['year_start'] !== $item['year_end'] && $item['year_end']): ?>
                                        <p class="text-sm font-semibold text-slate-500 mb-3 bg-slate-50 inline-block px-2 py-1 rounded">Period: <?= htmlspecialchars($item['year_start']) ?> - <?= htmlspecialchars($item['year_end']) ?></p>
                                    <?php endif; ?>
                                    <p class="text-sm text-slate-600 line-clamp-3 leading-relaxed">
                                        <?= htmlspecialchars(strip_tags($item['physical_description'] ?? '')) ?>
                                    </p>
                                    
                                    <div class="mt-6 pt-4 border-t border-slate-100 flex justify-between items-center">
                                        <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="text-sm font-bold text-modern-600 hover:text-modern-800 flex items-center group/link">
                                            View Artifact 
                                            <svg class="w-4 h-4 ml-1 transform group-hover/link:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php $isRight = !$isRight; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
            
            <style>
                @media (max-width: 768px) {
                    .wrap { padding-left: 20px; padding-right: 0; }
                    .wrap::before { display: none; }
                    /* Shift vertical line to the left on mobile */
                    .absolute.border-l-2 { left: 40px !important; transform: none !important; }
                    .flex.justify-between { flex-direction: row !important; }
                    .w-5\/12 { width: 100%; }
                    /* Hide left spacer */
                    .order-1:first-child { display: none; }
                    /* Move card to the right */
                    .order-1.bg-white { margin-left: 2rem; width: calc(100% - 3rem); }
                }
            </style>
        <?php endif; ?>
    </div>
</div>

<?php require_once ThemeManager::getFooter(); ?>
