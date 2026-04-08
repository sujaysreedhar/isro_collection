<?php require_once ThemeManager::getHeader(); ?>
<div class="bg-white min-h-screen flex flex-col font-serif text-slate-900">
    <!-- Header/Filter Bar -->
    <div class="bg-white border-b border-slate-100 sticky top-0 z-50 px-4 py-6">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="text-center md:text-left">
                <h1 class="text-3xl font-bold tracking-tight mb-1">Historical Archive Timeline</h1>
                <p class="text-sm text-slate-500 font-sans italic">A chronological journey through the collection.</p>
            </div>
            
            <form method="GET" action="" class="flex flex-wrap items-center gap-3 w-full md:w-auto font-sans">
                <div class="relative flex-grow md:w-72">
                    <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Filter records..." 
                           class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:ring-1 focus:ring-slate-900 outline-none transition-all">
                    <svg class="absolute left-3 top-3 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                
                <select name="category_id" class="bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-1 focus:ring-slate-900 outline-none transition-all" onchange="this.form.submit()">
                    <option value="0">All Classifications</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $catId == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <?php if ($catId > 0 || !empty($searchTerm)): ?>
                    <a href="?" class="text-xs text-slate-400 hover:text-slate-900 font-bold uppercase tracking-widest px-2 group flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Main Timeline Container -->
    <div class="flex-grow flex flex-col items-center justify-center p-4 md:p-8 overflow-hidden bg-slate-50/50">
        <?php if (empty($eraGroups)): ?>
            <div class="text-center py-20 bg-white p-12 rounded-3xl border border-slate-100 shadow-sm max-w-md">
                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h3 class="text-xl font-bold mb-2">No items found</h3>
                <p class="text-slate-500 font-sans text-sm">We couldn't find any chronological records matching your current filters.</p>
                <a href="?" class="mt-8 inline-block font-sans text-xs font-bold uppercase tracking-[0.2em] bg-slate-900 text-white px-8 py-3 rounded hover:bg-slate-800 transition-colors">Clear Filters</a>
            </div>
        <?php else: ?>
            <div id="timeline-viewport" class="w-full flex overflow-x-auto snap-x snap-mandatory hide-scrollbar gap-16 py-16 px-[10vw]">
                <?php foreach ($eraGroups as $eraIndex => $era): ?>
                    <!-- Era Section -->
                    <div class="flex-shrink-0 flex items-center gap-16 snap-start" id="era-<?= $eraIndex ?>">
                        <div class="flex flex-col justify-center items-center gap-4">
                            <span class="text-slate-400 font-bold uppercase tracking-[0.4em] text-[10px] vertical-text font-sans"><?= htmlspecialchars($era['name']) ?></span>
                            <div class="w-px h-40 bg-slate-200"></div>
                        </div>

                        <?php foreach ($era['items'] as $item): ?>
                            <div class="w-[300px] md:w-[380px] flex-shrink-0 snap-center group">
                                <div class="bg-white rounded-lg shadow-sm overflow-hidden border border-slate-200 transition-all duration-300 hover:shadow-xl hover:border-slate-300">
                                    <div class="h-60 relative bg-slate-50">
                                        <?php if ($item['preview_file_path']): ?>
                                            <img src="<?= SITE_URL ?>/uploads/display/<?= rawurlencode($item['preview_file_path']) ?>" 
                                                 class="w-full h-full object-cover grayscale-[0.2] group-hover:grayscale-0 transition-all duration-700 group-hover:scale-105" loading="lazy">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-slate-200">
                                                <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                        <?php endif; ?>
                                        <div class="absolute top-4 left-4">
                                            <div class="bg-white/90 backdrop-blur px-4 py-1.5 rounded-sm border border-slate-100 shadow-sm">
                                                <span class="text-2xl font-black italic tracking-tighter"><?= htmlspecialchars($item['year_start']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-8 font-sans">
                                        <span class="text-[9px] uppercase tracking-widest font-bold text-slate-400 mb-2 block"><?= htmlspecialchars($item['reg_number'] ?? 'ITEM') ?></span>
                                        <h3 class="text-xl font-bold text-slate-900 mb-4 leading-snug group-hover:text-slate-700 transition-colors serif">
                                            <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a>
                                        </h3>
                                        <p class="text-slate-500 text-xs leading-relaxed line-clamp-2 mb-6 italic">
                                            <?= htmlspecialchars(strip_tags($item['physical_description'] ?? 'No additional description for this record.')) ?>
                                        </p>
                                        <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-900 border-b border-slate-900 pb-0.5 hover:text-slate-500 hover:border-slate-300 transition-all inline-block">
                                            View Archive
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Navigator Bar -->
            <div class="w-full max-w-4xl mx-auto px-4 mt-8">
                <div class="bg-white rounded-full border border-slate-200 p-4 flex items-center gap-6 shadow-sm relative overflow-hidden group/nav">
                    <div id="nav-indicator" class="absolute h-0.5 bg-slate-900 transition-all duration-300 bottom-0 left-0"></div>
                    <div class="flex-shrink-0 text-[10px] font-bold uppercase text-slate-400 tracking-widest pl-4 font-sans">Timeline Navigator</div>
                    <div id="timeline-navigator" class="flex-grow flex justify-between items-center relative h-6 mr-6">
                        <div class="absolute inset-x-0 h-px bg-slate-100 top-1/2 -translate-y-1/2"></div>
                        <?php 
                        $totalItems = count($timelineItems);
                        $step = max(1, floor($totalItems / 12));
                        foreach ($timelineItems as $idx => $item): 
                            if ($idx % $step !== 0 && $idx !== $totalItems - 1) continue;
                        ?>
                            <button onclick="scrollToItem(<?= $idx ?>)" 
                                    class="w-2 h-2 rounded-full bg-slate-200 hover:bg-slate-900 hover:scale-150 transition-all z-10 relative group/dot">
                                <span class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover/dot:opacity-100 transition-all text-[9px] font-bold bg-slate-900 text-white px-2 py-1 rounded whitespace-nowrap pointer-events-none"><?= $item['year_start'] ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>


<?php 
echo AssetManager::renderStyles(['themes/default/timeline.css']); 
echo AssetManager::renderScripts(['themes/default/timeline.js']);
?>

<?php require_once ThemeManager::getFooter(); ?>
