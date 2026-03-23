<?php
// themes/modern_blue/item_detail.php

$pageTitle = $item['title'] . ' - ' . SITE_TITLE;

ob_start();
?>
<!-- Preconnect and JSON-LD -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<script type="application/ld+json">
<?= $jsonLdJson ?>
</script>
<style>
/* For smooth image transitions */
#main-media { transition: opacity 0.3s ease-in-out; }
.fade-in { opacity: 1 !important; }
</style>
<?php
$additionalHead = ob_get_clean();

require_once ThemeManager::getHeader();
?>

    <div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 md:py-12">
        
        <!-- Breadcrumb & Nav -->
        <nav class="flex items-center text-sm font-medium text-slate-500 mb-8 overflow-x-auto whitespace-nowrap pb-2 hide-scrollbar">
            <a href="<?= SITE_URL ?>/" class="hover:text-modern-600 transition-colors flex items-center">
                <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                Home
            </a>
            <svg class="mx-2 h-4 w-4 text-slate-300 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
            <a href="<?= SITE_URL ?>/search.php" class="hover:text-modern-600 transition-colors">Collection</a>
            <?php if (!empty($item['category_name'])): ?>
                <svg class="mx-2 h-4 w-4 text-slate-300 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                <a href="<?= SITE_URL ?>/search.php?category_ids[]=<?= $item['category_id'] ?>" class="hover:text-modern-600 transition-colors"><?= htmlspecialchars($item['category_name']) ?></a>
            <?php endif; ?>
            <svg class="mx-2 h-4 w-4 text-slate-300 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
            <span class="text-slate-900 truncate max-w-xs" aria-current="page"><?= htmlspecialchars($item['reg_number']) ?></span>
        </nav>

        <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200 overflow-hidden flex flex-col lg:flex-row">
            
            <!-- Media Section (Left side on desktop) -->
            <div class="w-full lg:w-3/5 bg-slate-50 border-r border-slate-200 flex flex-col">
                <div class="relative w-full flex items-center justify-center p-6 bg-slate-100 group">
                    <?php if (empty($allMedia)): ?>
                        <div class="text-slate-400 flex flex-col items-center py-24">
                            <svg class="h-20 w-20 mb-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <span class="text-lg font-medium">No Media Available</span>
                        </div>
                    <?php else: ?>
                        <!-- Main display area -->
                        <div id="media-container" class="w-full h-full relative rounded-2xl overflow-hidden shadow-sm">
                            <?php 
                                $first = $allMedia[0];
                                $mediaSrc = '';
                                if ($first['media_type'] === 'image' || $first['media_type'] === 'document') {
                                    $mediaSrc = isset($storage) ? $storage->url('display/' . $first['file_path']) : SITE_URL . '/uploads/display/' . $first['file_path'];
                                    echo '<img id="main-media" src="'.htmlspecialchars($mediaSrc).'" alt="Item Media" class="w-full h-auto cursor-zoom-in" onclick="openFullscreen()">';
                                } elseif ($first['media_type'] === 'youtube') {
                                    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $first['youtube_url'] ?? '', $matches);
                                    $ytId = $matches[1] ?? '';
                                    echo '<div class="w-full relative" style="padding-top:56.25%"><iframe id="main-media" src="https://www.youtube.com/embed/'.htmlspecialchars($ytId).'" class="absolute inset-0 w-full h-full border-0" allowfullscreen></iframe></div>';
                                } elseif ($first['media_type'] === 'video') {
                                    $mediaSrc = isset($storage) ? $storage->url('originals/' . $first['file_path']) : SITE_URL . '/uploads/originals/' . $first['file_path'];
                                    echo '<video id="main-media" src="'.htmlspecialchars($mediaSrc).'" controls class="w-full h-auto bg-black"></video>';
                                }
                            ?>
                        </div>
                        
                        <!-- Fullscreen overlay -->
                        <div id="fullscreen-overlay" class="fixed inset-0 bg-slate-900/95 z-[100] hidden items-center justify-center backdrop-blur-sm" onclick="closeFullscreen()">
                            <button class="absolute top-6 right-6 text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition-colors" onclick="closeFullscreen()">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                            <img id="fullscreen-img" class="max-w-[95vw] max-h-[95vh] object-contain shadow-2xl rounded-lg" src="" alt="Fullscreen view">
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Thumbnails Gallery -->
                <?php if (count($allMedia) > 1): ?>
                <div class="bg-white border-t border-slate-200 p-4 overflow-x-auto hide-scrollbar">
                    <div class="flex gap-3">
                        <?php foreach ($allMedia as $index => $m): ?>
                            <?php 
                                $thumbSrc = '';
                                $isVid = false;
                                if ($m['media_type'] === 'image' || $m['media_type'] === 'document') {
                                    $thumbSrc = isset($storage) ? $storage->url('thumbnails/' . $m['file_path']) : SITE_URL . '/uploads/thumbnails/' . $m['file_path'];
                                } elseif ($m['media_type'] === 'youtube') {
                                    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $m['youtube_url'] ?? '', $matches);
                                    $ytId = $matches[1] ?? '';
                                    $thumbSrc = "https://img.youtube.com/vi/{$ytId}/default.jpg";
                                    $isVid = true;
                                } elseif ($m['media_type'] === 'video') {
                                    // Placeholder for video
                                    $thumbSrc = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" fill="gray"><rect width="100%" height="100%" fill="black" /><polygon points="40,30 70,50 40,70" fill="white" /></svg>';
                                    $isVid = true;
                                }
                                
                                $fullSrc = '';
                                if ($m['media_type'] === 'image' || $m['media_type'] === 'document') {
                                    $fullSrc = isset($storage) ? $storage->url('display/' . $m['file_path']) : SITE_URL . '/uploads/display/' . $m['file_path'];
                                } elseif ($m['media_type'] === 'youtube') {
                                    $fullSrc = "https://www.youtube.com/embed/" . $ytId;
                                } elseif ($m['media_type'] === 'video') {
                                    $fullSrc = isset($storage) ? $storage->url('originals/' . $m['file_path']) : SITE_URL . '/uploads/originals/' . $m['file_path'];
                                }
                            ?>
                            <button type="button" onclick="switchMedia('<?= htmlspecialchars($fullSrc) ?>', '<?= $m['media_type'] ?>', this)" 
                                    class="thumb-btn flex-shrink-0 relative w-20 h-20 rounded-xl overflow-hidden border-2 <?= $index === 0 ? 'border-modern-500 ring-2 ring-modern-200 ring-offset-1' : 'border-transparent hover:border-modern-300' ?> transition-all">
                                <img src="<?= htmlspecialchars($thumbSrc) ?>" class="w-full h-full object-cover" alt="Thumbnail">
                                <?php if($isVid): ?>
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    </div>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons (Mobile: under thumb, Desktop: bottom of left col) -->
                <div class="px-6 py-4 flex flex-wrap gap-3 bg-white mt-auto justify-center md:justify-start">
                    <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-slate-100 text-slate-700 text-sm font-semibold rounded-lg hover:bg-slate-200 transition-colors shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print Details
                    </button>
                    <!-- Hook for social share or collection add -->
                    <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_item_actions', $item); } ?>
                </div>
            </div>

            <!-- Content Section (Right side) -->
            <div class="w-full lg:w-2/5 p-8 lg:p-10 flex flex-col">
                
                <!-- Headers -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <span class="inline-block px-3 py-1 bg-modern-50 text-modern-700 font-bold text-xs uppercase tracking-widest rounded-md border border-modern-100">
                            <?= htmlspecialchars($item['reg_number']) ?>
                        </span>
                        <?php if(!empty($item['collection_name'])): ?>
                            <span class="text-sm font-semibold text-slate-500 flex items-center gap-1.5 focus-within:text-slate-800 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                <?= htmlspecialchars($item['collection_name']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tight leading-tight mb-4">
                        <?= htmlspecialchars($item['title']) ?>
                    </h1>
                    
                    <div class="flex flex-wrap items-center gap-4 text-sm font-medium text-slate-500">
                        <div class="flex items-center gap-1.5 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                            <svg class="w-4 h-4 text-modern-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <?= htmlspecialchars($item['production_date'] ?? 'Unknown Date') ?>
                        </div>
                        <?php if (!empty($item['creator'])): ?>
                        <div class="flex items-center gap-1.5 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                            <svg class="w-4 h-4 text-modern-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            <?= htmlspecialchars($item['creator']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Description -->
                <?php if (!empty($item['physical_description'])): ?>
                <div class="prose prose-slate prose-modern max-w-none text-slate-600 mb-10 leading-relaxed max-h-96 overflow-y-auto pr-2 custom-scrollbar">
                    <?= $item['physical_description'] ?>
                </div>
                <?php endif; ?>

                <!-- Specs Grid -->
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 mb-8 mt-auto">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-widest mb-4 flex items-center">
                        <svg class="w-4 h-4 mr-2 text-modern-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                        Specifications
                    </h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <?php if(!empty($item['dimensions'])): ?>
                            <div class="col-span-1 border-b border-slate-200 pb-2">
                                <dt class="text-slate-500 font-medium mb-1">Dimensions</dt>
                                <dd class="text-slate-900 font-semibold"><?= htmlspecialchars($item['dimensions']) ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if(!empty($item['materials'])): ?>
                            <div class="col-span-1 border-b border-slate-200 pb-2">
                                <dt class="text-slate-500 font-medium mb-1">Materials/Medium</dt>
                                <dd class="text-slate-900 font-semibold"><?= htmlspecialchars($item['materials']) ?></dd>
                            </div>
                        <?php endif; ?>
                        <div class="col-span-1 border-b border-slate-200 pb-2">
                            <dt class="text-slate-500 font-medium mb-1">Aquisition / Status</dt>
                            <dd class="text-slate-900 font-semibold flex items-center gap-2">
                                <?php if ($item['is_acquired']): ?>
                                    <span class="w-2 h-2 rounded-full bg-green-500"></span> In Collection
                                <?php else: ?>
                                    <span class="w-2 h-2 rounded-full bg-slate-300"></span> Not Acquired
                                <?php endif; ?>
                            </dd>
                        </div>
                        <?php if(!empty($item['credit_line'])): ?>
                            <div class="col-span-1 sm:col-span-2 border-slate-200 pt-1">
                                <dt class="text-slate-500 font-medium mb-1">Credit Line</dt>
                                <dd class="text-slate-900 italic"><?= htmlspecialchars($item['credit_line']) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>

                <!-- Tags -->
                <?php if (!empty($tags)): ?>
                <div class="mb-2">
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($tags as $tag): ?>
                            <a href="<?= SITE_URL ?>/search.php?tag_ids[]=<?= $tag['id'] ?>" class="inline-flex items-center px-3 py-1 rounded-md bg-white border border-slate-200 text-xs font-semibold text-slate-600 hover:text-modern-600 hover:border-modern-300 hover:bg-modern-50 transition-all shadow-sm">
                                <span class="text-slate-400 mr-1">#</span><?= htmlspecialchars($tag['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Related Stories -->
        <?php if (!empty($relatedStories)): ?>
        <div class="mt-16 sm:mt-24">
            <div class="flex items-center mb-8">
                <h2 class="text-2xl font-bold text-slate-900">Featured in Narratives</h2>
                <div class="ml-4 flex-grow h-px bg-slate-200"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($relatedStories as $story): ?>
                <a href="<?= SITE_URL ?>/narrative/<?= $story['id'] ?>" class="group bg-white border border-slate-200 rounded-2xl p-6 hover:border-modern-300 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-modern-500 transform origin-bottom scale-y-0 group-hover:scale-y-100 transition-transform duration-300"></div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-modern-50 text-modern-600 rounded-lg group-hover:bg-modern-600 group-hover:text-white transition-colors">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        </div>
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-400 group-hover:text-modern-500 transition-colors">Narrative Story</span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 group-hover:text-modern-700 transition-colors mb-2"><?= htmlspecialchars($story['title']) ?></h3>
                    <p class="text-sm text-slate-500 line-clamp-3 leading-relaxed"><?= htmlspecialchars(strip_tags($story['content'])) ?></p>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
    </div>

    <!-- Script for media switching -->
    <script>
    function openFullscreen() {
        const mainMedia = document.getElementById('main-media');
        if(mainMedia.tagName !== 'IMG') return; // Only fullscreen images 
        
        document.getElementById('fullscreen-img').src = mainMedia.src;
        document.getElementById('fullscreen-overlay').style.display = 'flex';
    }

    function closeFullscreen() {
        document.getElementById('fullscreen-overlay').style.display = 'none';
        document.getElementById('fullscreen-img').src = '';
    }

    function switchMedia(src, type, btnElement) {
        const container = document.getElementById('media-container');
        
        // Handle active thumbnail styling
        document.querySelectorAll('.thumb-btn').forEach(btn => {
            btn.classList.remove('border-modern-500', 'ring-2', 'ring-modern-200', 'ring-offset-1');
            btn.classList.add('border-transparent');
        });
        btnElement.classList.add('border-modern-500', 'ring-2', 'ring-modern-200', 'ring-offset-1');
        btnElement.classList.remove('border-transparent');

        // Fade out transition
        container.style.opacity = '0';
        
        setTimeout(() => {
            if (type === 'image' || type === 'document') {
                container.innerHTML = `<img id="main-media" src="${src}" class="w-full h-auto cursor-zoom-in" onclick="openFullscreen()">`;
            } else if (type === 'youtube') {
                container.innerHTML = `<div class="w-full relative" style="padding-top:56.25%"><iframe id="main-media" src="${src}" class="absolute inset-0 w-full h-full border-0" allowfullscreen></iframe></div>`;
            } else if (type === 'video') {
                container.innerHTML = `<video id="main-media" src="${src}" controls class="w-full h-auto bg-black"></video>`;
            }
            // Fade in
            container.style.opacity = '1';
            container.style.transition = 'opacity 0.3s ease-in-out';
        }, 300);
    }
    
    // Custom scrollbar styles dynamically attached
    const style = document.createElement('style');
    style.innerHTML = `
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    `;
    document.head.appendChild(style);
    </script>

<?php require_once ThemeManager::getFooter(); ?>
