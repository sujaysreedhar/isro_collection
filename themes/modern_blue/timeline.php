<?php
$pageTitle = 'Timeline - ' . SITE_TITLE;
$currentMenu = 'timeline';

// We get $timelineData which is an array keyed by year_start, values are arrays of items.
require_once ThemeManager::getHeader();
?>

<div class="bg-slate-50 min-h-screen flex flex-col font-sans">
    <!-- Header/Filter Bar -->
    <div class="bg-white border-b border-slate-200 sticky top-0 z-50 px-4 py-4 shadow-sm">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-modern-100 rounded-lg text-modern-600">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Timeline</h1>
            </div>
            
            <form method="GET" action="" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <div class="relative flex-grow md:w-64">
                    <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search timeline..." 
                           class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-modern-500 outline-none transition-all">
                    <svg class="absolute left-3 top-2.5 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                
                <select name="category_id" class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-modern-500 outline-none transition-all" onchange="this.form.submit()">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $catId == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <?php if ($catId > 0 || !empty($searchTerm)): ?>
                    <a href="?" class="text-sm text-slate-400 hover:text-red-500 font-bold px-2">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Main Timeline Container -->
    <div class="flex-grow flex flex-col items-center justify-center p-4 md:p-8 overflow-hidden">
        <?php if (empty($eraGroups)): ?>
            <div class="text-center py-20">
                <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <h3 class="text-2xl font-bold text-slate-900 mb-2">No matching events</h3>
                <p class="text-slate-500">Try adjusting your search or filters.</p>
                <a href="?" class="mt-6 inline-block text-modern-600 font-bold bg-modern-50 px-6 py-2 rounded-xl transition-colors hover:bg-modern-100">Reset View</a>
            </div>
        <?php else: ?>
            <div id="timeline-viewport" class="w-full flex overflow-x-auto snap-x snap-mandatory hide-scrollbar gap-12 py-12 px-[10vw]">
                <?php foreach ($eraGroups as $eraIndex => $era): ?>
                    <!-- Era Section -->
                    <div class="flex-shrink-0 flex items-center gap-12 snap-start" id="era-<?= $eraIndex ?>">
                        <div class="flex flex-col justify-center gap-4">
                            <span class="text-<?= $era['color'] ?>-600 font-black uppercase tracking-[0.3em] text-xs vertical-text opacity-50"><?= htmlspecialchars($era['name']) ?></span>
                            <div class="w-1 h-32 bg-gradient-to-b from-<?= $era['color'] ?>-500 to-transparent rounded-full mx-auto"></div>
                        </div>

                        <?php foreach ($era['items'] as $item): ?>
                            <div class="w-[320px] md:w-[400px] flex-shrink-0 snap-center group">
                                <div class="bg-white rounded-[2rem] shadow-2xl overflow-hidden border border-slate-100 transition-all duration-500 hover:-translate-y-4 hover:shadow-modern-200/50">
                                    <div class="h-64 relative bg-slate-900">
                                        <?php if ($item['preview_file_path']): ?>
                                            <img src="<?= SITE_URL ?>/uploads/display/<?= rawurlencode($item['preview_file_path']) ?>" 
                                                 class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110 opacity-90 group-hover:opacity-100" loading="lazy">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center bg-slate-800 text-slate-600">
                                                <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                        <?php endif; ?>
                                        <div class="absolute bottom-0 left-0 right-0 p-6 bg-gradient-to-t from-black/80 to-transparent">
                                            <div class="flex items-center gap-3">
                                                <span class="text-4xl font-black text-white italic tracking-tighter"><?= htmlspecialchars($item['year_start']) ?></span>
                                                <div class="h-px flex-grow bg-white/30"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-8">
                                        <span class="text-[10px] uppercase tracking-widest font-black text-modern-500 mb-2 block"><?= htmlspecialchars($item['reg_number'] ?? 'RECORD') ?></span>
                                        <h3 class="text-2xl font-bold text-slate-900 mb-4 leading-tight group-hover:text-modern-600 transition-colors">
                                            <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a>
                                        </h3>
                                        <p class="text-slate-500 text-sm leading-relaxed line-clamp-3 italic mb-6">
                                            "<?= htmlspecialchars(strip_tags($item['physical_description'] ?? 'No description available for this period.')) ?>"
                                        </p>
                                        <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="inline-flex items-center gap-2 text-sm font-bold text-slate-900 hover:text-modern-600 transition-colors">
                                            Explore Artifact
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
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
                <div class="bg-white/50 backdrop-blur-md rounded-2xl border border-white/50 p-6 flex items-center gap-6 shadow-xl relative overflow-hidden">
                    <div id="nav-indicator" class="absolute h-1 bg-modern-500 transition-all duration-300 bottom-0 left-0"></div>
                    <div class="flex-shrink-0 text-xs font-black uppercase text-slate-400 tracking-widest bg-slate-100 px-3 py-1 rounded-full">Journey Bar</div>
                    <div id="timeline-navigator" class="flex-grow flex justify-between items-center relative h-8">
                        <div class="absolute inset-x-0 h-0.5 bg-slate-200 top-1/2 -translate-y-1/2"></div>
                        <?php 
                        $totalItems = count($timelineItems);
                        $step = max(1, floor($totalItems / 10));
                        foreach ($timelineItems as $idx => $item): 
                            if ($idx % $step !== 0 && $idx !== $totalItems - 1) continue;
                        ?>
                            <button onclick="scrollToItem(<?= $idx ?>)" data-idx="<?= $idx ?>" 
                                    class="nav-dot w-6 h-6 flex items-center justify-center relative bg-white border-2 border-slate-200 rounded-full hover:border-modern-500 hover:scale-125 transition-all z-10 group">
                                <span class="absolute -top-10 opacity-0 group-hover:opacity-100 transition-opacity bg-slate-900 text-white text-[10px] font-bold px-2 py-1 rounded whitespace-nowrap pointer-events-none"><?= $item['year_start'] ?></span>
                                <div class="w-1.5 h-1.5 bg-slate-300 rounded-full group-hover:bg-modern-500"></div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .vertical-text {
        writing-mode: vertical-rl;
        transform: rotate(180deg);
    }
    .hide-scrollbar::-webkit-scrollbar { display: none; }
    .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    
    #timeline-viewport {
        scroll-behavior: smooth;
        cursor: grab;
    }
    #timeline-viewport:active {
        cursor: grabbing;
    }
</style>

<script>
    const viewport = document.getElementById('timeline-viewport');
    
    function scrollToItem(idx) {
        const cards = viewport.querySelectorAll('.group');
        if (cards[idx]) {
            cards[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }

    // Scroll drag functionality
    let isDown = false;
    let startX;
    let scrollLeft;

    viewport.addEventListener('mousedown', (e) => {
        isDown = true;
        viewport.classList.add('active');
        startX = e.pageX - viewport.offsetLeft;
        scrollLeft = viewport.scrollLeft;
    });
    viewport.addEventListener('mouseleave', () => {
        isDown = false;
    });
    viewport.addEventListener('mouseup', () => {
        isDown = false;
    });
    viewport.addEventListener('mousemove', (e) => {
        if(!isDown) return;
        e.preventDefault();
        const x = e.pageX - viewport.offsetLeft;
        const walk = (x - startX) * 2;
        viewport.scrollLeft = scrollLeft - walk;
    });

    // Update navigator indicator
    viewport.addEventListener('scroll', () => {
        const totalScroll = viewport.scrollWidth - viewport.clientWidth;
        const currentScroll = viewport.scrollLeft;
        const percent = (currentScroll / totalScroll) * 100;
        document.getElementById('nav-indicator').style.width = percent + '%';
        
        // Highlight closest dot
        // (Optional expansion: find closest card and highlight corresponding dot)
    });
</script>

<?php require_once ThemeManager::getFooter(); ?>
