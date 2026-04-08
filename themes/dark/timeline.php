<?php require_once ThemeManager::getHeader(); ?>
<div class="bg-slate-950 min-h-screen flex flex-col font-sans text-slate-100">
    <!-- Header/Filter Bar -->
    <div class="bg-slate-900/80 backdrop-blur-xl border-b border-white/5 sticky top-0 z-50 px-4 py-4 shadow-2xl">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-500/20 rounded-lg text-purple-400 border border-purple-500/30">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h1 class="text-xl font-black text-white tracking-widest uppercase">Chronos <span class="text-purple-500">Timeline</span></h1>
            </div>
            
            <form method="GET" action="" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <div class="relative flex-grow md:w-64">
                    <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search history..." 
                           class="w-full pl-10 pr-4 py-2 bg-slate-800/50 border border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none transition-all placeholder-slate-500">
                    <svg class="absolute left-3 top-2.5 w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                
                <select name="category_id" class="bg-slate-800/50 border border-white/10 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-purple-500 outline-none transition-all text-slate-300" onchange="this.form.submit()">
                    <option value="0">All Epochs</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $catId == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <?php if ($catId > 0 || !empty($searchTerm)): ?>
                    <a href="?" class="text-xs text-slate-500 hover:text-purple-400 font-black uppercase tracking-widest px-2">Reset</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Main Timeline Container -->
    <div class="flex-grow flex flex-col items-center justify-center p-4 md:p-8 overflow-hidden relative">
        <!-- Background Aura -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[400px] bg-purple-600/10 blur-[120px] pointer-events-none rounded-full"></div>

        <?php if (empty($eraGroups)): ?>
            <div class="text-center py-20 relative z-10">
                <div class="w-24 h-24 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-8 border border-white/10 shadow-inner">
                    <svg class="w-10 h-10 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <h3 class="text-3xl font-black text-white mb-3">Void Detected</h3>
                <p class="text-slate-500 max-w-sm mx-auto">No artifacts were found matching your temporal filters.</p>
                <a href="?" class="mt-8 inline-block text-purple-400 font-bold border border-purple-500/30 px-8 py-3 rounded-full transition-all hover:bg-purple-500 hover:text-white shadow-lg shadow-purple-900/20">Return to Origin</a>
            </div>
        <?php else: ?>
            <div id="timeline-viewport" class="w-full flex overflow-x-auto snap-x snap-mandatory hide-scrollbar gap-24 py-12 px-[15vw] relative z-10">
                <?php foreach ($eraGroups as $eraIndex => $era): ?>
                    <!-- Era Section -->
                    <div class="flex-shrink-0 flex items-center gap-24 snap-start" id="era-<?= $eraIndex ?>">
                        <div class="flex flex-col justify-center items-center gap-6">
                            <h2 class="text-<?= $era['color'] ?>-500 font-black uppercase tracking-[0.5em] text-sm vertical-text opacity-40 blur-[0.5px]"><?= htmlspecialchars($era['name']) ?></h2>
                            <div class="w-px h-64 bg-gradient-to-b from-<?= $era['color'] ?>-500/50 via-<?= $era['color'] ?>-500/10 to-transparent"></div>
                        </div>

                        <?php foreach ($era['items'] as $item): ?>
                            <div class="w-[350px] md:w-[450px] flex-shrink-0 snap-center group">
                                <div class="bg-slate-900/40 backdrop-blur-2xl rounded-[2.5rem] shadow-2xl overflow-hidden border border-white/5 transition-all duration-700 hover:-translate-y-6 hover:shadow-purple-500/20 hover:border-purple-500/30">
                                    <div class="h-80 relative bg-black">
                                        <?php if ($item['preview_file_path']): ?>
                                            <img src="<?= SITE_URL ?>/uploads/display/<?= rawurlencode($item['preview_file_path']) ?>" 
                                                 class="w-full h-full object-cover transition-transform duration-[1.5s] group-hover:scale-110 opacity-70 group-hover:opacity-100 grayscale-[0.5] group-hover:grayscale-0" loading="lazy">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center bg-slate-900 text-slate-800">
                                                <svg class="w-20 h-20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Year Badge -->
                                        <div class="absolute top-8 left-8">
                                            <div class="bg-black/40 backdrop-blur-md border border-white/10 px-6 py-2 rounded-full shadow-2xl">
                                                <span class="text-3xl font-black text-white italic tracking-tighter"><?= htmlspecialchars($item['year_start']) ?></span>
                                            </div>
                                        </div>

                                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/20 to-transparent"></div>
                                    </div>
                                    
                                    <div class="p-10 -mt-10 relative z-10 bg-gradient-to-b from-transparent to-slate-900/80">
                                        <span class="text-[10px] uppercase tracking-[0.3em] font-black text-purple-400 mb-3 block opacity-60"><?= htmlspecialchars($item['reg_number'] ?? 'ARCHIVE_ENTRY') ?></span>
                                        <h3 class="text-3xl font-bold text-white mb-6 leading-tight group-hover:text-purple-400 transition-colors">
                                            <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a>
                                        </h3>
                                        <p class="text-slate-400 text-sm leading-relaxed line-clamp-3 mb-8 font-light italic opacity-80 group-hover:opacity-100 transition-opacity">
                                            "<?= htmlspecialchars(strip_tags($item['physical_description'] ?? 'Transcribing historical record...')) ?>"
                                        </p>
                                        <div class="flex items-center justify-between">
                                            <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="group/btn inline-flex items-center gap-3 text-xs font-black uppercase tracking-widest text-white hover:text-purple-400 transition-all">
                                                Access Record
                                                <div class="w-8 h-8 rounded-full border border-white/10 flex items-center justify-center group-hover/btn:border-purple-500/50 transition-all">
                                                    <svg class="w-4 h-4 transform group-hover/btn:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Navigator Bar -->
            <div class="w-full max-w-5xl mx-auto px-4 mt-12 relative z-20">
                <div class="bg-slate-900/60 backdrop-blur-2xl rounded-3xl border border-white/5 p-8 flex items-center gap-10 shadow-[0_32px_64px_-16px_rgba(0,0,0,0.5)] relative overflow-hidden">
                    <div id="nav-indicator" class="absolute h-0.5 bg-gradient-to-r from-purple-600 to-cyan-400 transition-all duration-500 bottom-0 left-0 shadow-[0_0_15px_rgba(168,85,247,0.5)]"></div>
                    
                    <div class="hidden md:block flex-shrink-0">
                        <span class="text-[10px] font-black uppercase text-slate-500 tracking-[0.4em] block mb-1">Temporal</span>
                        <span class="text-[10px] font-black uppercase text-purple-400 tracking-[0.4em]">Navigator</span>
                    </div>

                    <div id="timeline-navigator" class="flex-grow flex justify-between items-center relative h-10">
                        <div class="absolute inset-x-0 h-px bg-white/5 top-1/2 -translate-y-1/2 mr-2"></div>
                        <?php 
                        $totalItems = count($timelineItems);
                        $step = max(1, floor($totalItems / 10));
                        foreach ($timelineItems as $idx => $item): 
                            if ($idx % $step !== 0 && $idx !== $totalItems - 1) continue;
                        ?>
                            <button onclick="scrollToItem(<?= $idx ?>)" data-idx="<?= $idx ?>" 
                                    class="nav-dot w-3 h-3 flex items-center justify-center relative bg-slate-800 border border-white/10 rounded-full hover:border-purple-500/50 hover:bg-purple-500 hover:scale-150 transition-all z-10 group">
                                <span class="absolute -top-12 opacity-0 group-hover:opacity-100 transition-all -translate-y-2 group-hover:translate-y-0 bg-slate-800 text-purple-400 text-[10px] font-black border border-purple-500/30 px-3 py-1.5 rounded-lg whitespace-nowrap pointer-events-none shadow-2xl"><?= $item['year_start'] ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
echo AssetManager::renderStyles(['themes/dark/timeline.css']); 
echo AssetManager::renderScripts(['themes/dark/timeline.js']);
?>

<?php require_once ThemeManager::getFooter(); ?>
