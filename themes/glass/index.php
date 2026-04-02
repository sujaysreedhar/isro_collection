<?php
// themes/glass/index.php

$pageTitle = SITE_TITLE . ' - Digital Archive';
$currentMenu = 'home';

require_once ThemeManager::getHeader();
?>

    <!-- Hero Section -->
    <div class="relative bg-white/5 backdrop-blur-sm border-b border-white/10 overflow-hidden shadow-2xl z-10">
        <!-- Abstract background pattern -->
        <div class="absolute inset-0 opacity-10 pointer-events-none">
            <svg class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="grid-pattern" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M0 40V0H40" fill="none" stroke="white" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid-pattern)"/>
            </svg>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 to-transparent"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 sm:py-32 flex flex-col items-center text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-glass-500/20 border border-glass-500/30 text-glass-100 text-sm font-medium mb-8 backdrop-blur-md shadow-[0_0_15px_rgba(139,92,246,0.3)]">
                <span class="w-2 h-2 rounded-full bg-glass-400 animate-pulse shadow-[0_0_8px_rgba(167,139,250,0.8)]"></span>
                <span>Discover history through our digital lens</span>
            </div>
            
            <h1 class="text-5xl sm:text-6xl lg:text-7xl font-extrabold text-white tracking-tight mb-6 drop-shadow-md">
                Explore the <span class="text-transparent bg-clip-text bg-gradient-to-r from-fuchsia-400 to-cyan-400 drop-shadow-[0_0_10px_rgba(192,132,252,0.3)]">Archives</span>
            </h1>
            <p class="max-w-2xl text-xl text-slate-300 mb-10 leading-relaxed font-light">
                A curated digital collection of historical artifacts, documents, and narratives preserved for future generations.
            </p>

            <!-- Big Search Box -->
            <div class="w-full max-w-3xl bg-white/10 backdrop-blur-xl p-2 flex rounded-2xl border border-white/20 shadow-[0_8px_32px_rgba(0,0,0,0.3)]">
                <form action="<?= SITE_URL ?>/search.php" method="GET" class="w-full relative flex items-center">
                    <svg class="absolute left-4 h-6 w-6 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text" name="q" class="w-full bg-transparent border-none text-white placeholder-slate-300 pl-14 pr-4 py-4 focus:ring-0 text-lg rounded-xl" placeholder="Search the collection...">
                    <button type="submit" class="bg-glass-600 hover:bg-glass-500 text-white font-semibold py-3 px-8 rounded-xl transition-all shadow-[0_4px_14px_rgba(124,58,237,0.4)] hover:shadow-[0_6px_20px_rgba(124,58,237,0.6)] hover:-translate-y-0.5 ml-2">
                        Search
                    </button>
                </form>
            </div>
            
            <div class="mt-8 flex gap-4 text-sm text-slate-400 font-medium tracking-wide">
                <span>Popular:</span>
                <a href="<?= SITE_URL ?>/search.php?q=photographs" class="hover:text-glass-300 hover:drop-shadow-[0_0_4px_rgba(196,181,253,0.8)] transition-all">Photographs</a>
                <a href="<?= SITE_URL ?>/search.php?q=letters" class="hover:text-glass-300 hover:drop-shadow-[0_0_4px_rgba(196,181,253,0.8)] transition-all">Letters</a>
                <a href="<?= SITE_URL ?>/search.php?q=artifacts" class="hover:text-glass-300 hover:drop-shadow-[0_0_4px_rgba(196,181,253,0.8)] transition-all">Artifacts</a>
            </div>
        </div>
    </div>

    <!-- Featured Collections -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <div class="flex items-end justify-between mb-12">
            <div>
                <h2 class="text-3xl font-extrabold tracking-tight text-white drop-shadow-sm">Featured Additions</h2>
                <p class="mt-3 text-slate-300 max-w-2xl text-lg">Browse some of the most recently acquired and cataloged items in our collection.</p>
            </div>
            <a href="<?= SITE_URL ?>/search.php" class="hidden sm:inline-flex items-center text-glass-400 font-semibold hover:text-glass-300 transition-colors group">
                View all items 
                <svg class="ml-2 w-5 h-5 group-hover:translate-x-1 transition-transform drop-shadow-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
            </a>
        </div>

        <?php if (empty($featuredItems)): ?>
            <div class="text-center py-20 bg-white/5 backdrop-blur-md rounded-3xl border border-white/20 border-dashed shadow-[0_8px_32px_rgba(0,0,0,0.2)]">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-white">No items available</h3>
                <p class="mt-1 text-slate-400">The collection database is currently empty.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 xl:gap-10">
                <?php foreach ($featuredItems as $i => $item): ?>
                    <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="group block bg-white/5 backdrop-blur-xl rounded-3xl border border-white/10 overflow-hidden hover:shadow-[0_8px_32px_rgba(0,0,0,0.4)] hover:border-glass-400/50 hover:bg-white/10 transition-all duration-300 flex flex-col h-full transform hover:-translate-y-2">
                        
                        <!-- Image Container with 16:9 Aspect Ratio -->
                        <div class="relative w-full pt-[60%] bg-slate-900 border-b border-white/5 overflow-hidden">
                            <?php if (!empty($item['primary_media_path'])): ?>
                                <?php
                                    if (isset($storage)) {
                                        $mediaSrc = $storage->url('display/' . $item['primary_media_path']);
                                    } else {
                                        $mediaSrc = file_exists(realpath(__DIR__ . '/../../uploads/display/' . $item['primary_media_path'])) 
                                            ? SITE_URL . '/uploads/display/' . rawurlencode($item['primary_media_path'])
                                            : SITE_URL . '/uploads/originals/' . rawurlencode($item['primary_media_path']);
                                    }
                                    
                                    // Determine if it's a YouTube video to show an icon
                                    $isYoutube = ($item['primary_media_type'] === 'youtube');
                                    if ($isYoutube) {
                                        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $item['primary_media_path'], $matches);
                                        $ytId = $matches[1] ?? '';
                                        $mediaSrc = "https://img.youtube.com/vi/{$ytId}/maxresdefault.jpg";
                                    }
                                ?>
                                <img src="<?= $mediaSrc ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-in-out opacity-90 group-hover:opacity-100" loading="lazy">
                                <?php if ($isYoutube): ?>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <div class="bg-black/40 backdrop-blur-md text-white rounded-full p-3 shadow-[0_4px_12px_rgba(0,0,0,0.5)] border border-white/20 group-hover:bg-glass-600 transition-colors">
                                            <svg class="w-8 h-8 translate-x-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-500 bg-black/20">
                                    <svg class="w-12 h-12 mb-3 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <span class="text-sm font-medium">No Image</span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Category Badge -->
                            <?php if (!empty($item['category_name'])): ?>
                                <div class="absolute top-4 left-4">
                                    <span class="inline-flex items-center px-3 py-1 bg-white/20 backdrop-blur-md shadow-[0_2px_10px_rgba(0,0,0,0.2)] border border-white/20 text-xs font-bold text-white rounded-full tracking-wide">
                                        <?= htmlspecialchars($item['category_name']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Card Content -->
                        <div class="p-6 flex flex-col flex-1">
                            <span class="text-xs font-bold tracking-widest text-glass-400 uppercase mb-2 block drop-shadow-sm"><?= htmlspecialchars($item['reg_number']) ?></span>
                            <h3 class="text-xl font-bold text-white leading-tight mb-3 group-hover:text-glass-300 transition-colors line-clamp-2 drop-shadow-sm">
                                <?= htmlspecialchars($item['title']) ?>
                            </h3>
                            <p class="text-slate-300 text-sm line-clamp-3 mb-6 flex-1 drop-shadow-sm">
                                <?= htmlspecialchars(strip_tags($item['physical_description'] ?? '')) ?>
                            </p>
                            
                            <!-- Bottom Meta -->
                            <div class="flex items-center justify-between mt-auto pt-4 border-t border-white/10">
                                <span class="text-xs font-medium text-slate-400 flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <?= htmlspecialchars($item['production_date'] ?? 'n.d.') ?>
                                </span>
                                <span class="text-glass-400 group-hover:text-glass-300 group-hover:translate-x-1 transition-transform drop-shadow-sm">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-12 text-center sm:hidden">
                <a href="<?= SITE_URL ?>/search.php" class="inline-flex items-center justify-center px-6 py-3 border border-white/20 backdrop-blur-md text-base font-medium rounded-xl text-white bg-white/10 hover:bg-white/20 transition-all shadow-[0_4px_16px_rgba(0,0,0,0.3)]">
                    View all items
                </a>
            </div>
        <?php endif; ?>
        <!-- Hooks for modules (home_page_sections) -->
        <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('home_page_sections'); } ?>
    </div>

<?php require_once ThemeManager::getFooter(); ?>
