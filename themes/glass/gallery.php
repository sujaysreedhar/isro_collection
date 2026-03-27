<?php
// themes/glass/gallery.php
$pageTitle = 'Visual Gallery - ' . SITE_TITLE;
$currentMenu = 'gallery';

ob_start();
?>
<style>
    .masonry-grid {
        column-count: 1;
        column-gap: 1.5rem;
    }
    @media (min-width: 640px) { .masonry-grid { column-count: 2; } }
    @media (min-width: 1024px) { .masonry-grid { column-count: 3; } }
    @media (min-width: 1280px) { .masonry-grid { column-count: 4; } }
    
    .masonry-item {
        break-inside: avoid;
        margin-bottom: 1.5rem;
    }
</style>
<?php
$additionalHead = ob_get_clean();

require_once ThemeManager::getHeader();
?>

    <div class="flex-grow max-w-[1600px] mx-auto w-full px-4 sm:px-6 lg:px-8 py-10 md:py-16 relative z-10">
        <div class="mb-12 text-center max-w-3xl mx-auto">
            <h1 class="text-4xl md:text-5xl font-extrabold text-white mb-5 tracking-tight drop-shadow-md">Visual Gallery</h1>
            <p class="text-lg md:text-xl text-slate-300 font-light">A continuous stream of imagery and videos from our collections.</p>
        </div>

        <?php if (empty($mediaItems)): ?>
            <div class="text-center py-24 bg-white/5 backdrop-blur-md rounded-3xl border border-white/10 shadow-[0_4px_16px_rgba(0,0,0,0.2)] max-w-lg mx-auto">
                <svg class="mx-auto h-16 w-16 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-4 text-xl font-bold text-white">No media found</h3>
                <p class="mt-2 text-slate-400">There are currently no visible images or videos.</p>
            </div>
        <?php else: ?>
            <div class="masonry-grid">
                <?php foreach ($mediaItems as $media): ?>
                    <a href="<?= SITE_URL ?>/item/<?= $media['item_id'] ?>" class="masonry-item group block relative bg-black/20 rounded-2xl overflow-hidden border border-white/10 shadow-sm hover:shadow-[0_8px_32px_rgba(0,0,0,0.4)] hover:border-glass-400/50 transition-all duration-500 hover:-translate-y-1">
                        <?php if ($media['media_type'] === 'image'): ?>
                            <?php 
                                $imgSrc = isset($storage) 
                                    ? $storage->url('display/' . $media['file_path']) 
                                    : SITE_URL . '/uploads/display/' . $media['file_path'];
                            ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($media['title']) ?>" class="w-full object-cover group-hover:scale-105 transition-transform duration-700 ease-in-out opacity-90 group-hover:opacity-100" loading="lazy">
                        <?php elseif ($media['media_type'] === 'youtube'): ?>
                            <?php 
                                preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $media['youtube_url'] ?? '', $matches);
                                $ytId = $matches[1] ?? '';
                            ?>
                            <div class="relative w-full pb-[56.25%] bg-black">
                                <img src="https://img.youtube.com/vi/<?= htmlspecialchars($ytId) ?>/maxresdefault.jpg" onerror="this.src='https://img.youtube.com/vi/<?= htmlspecialchars($ytId) ?>/hqdefault.jpg'" class="absolute inset-0 w-full h-full object-cover opacity-80 group-hover:scale-105 transition-transform duration-700 ease-in-out" loading="lazy">
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <div class="bg-black/40 backdrop-blur-md text-white rounded-full p-4 shadow-[0_4px_16px_rgba(0,0,0,0.5)] border border-white/20 group-hover:bg-glass-600 group-hover:border-glass-400/50 transition-all duration-300">
                                        <svg class="w-8 h-8 translate-x-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-transparent p-6 translate-y-4 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-500 flex flex-col justify-end pointer-events-none z-10">
                            <span class="text-[10px] font-bold tracking-widest text-glass-300 uppercase mb-1.5"><?= htmlspecialchars($media['reg_number']) ?></span>
                            <h3 class="text-white font-bold text-base leading-snug line-clamp-2"><?= htmlspecialchars($media['title']) ?></h3>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php require_once ThemeManager::getFooter(); ?>
