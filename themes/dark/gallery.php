<?php
// themes/dark/gallery.php
$pageTitle = 'Visual Gallery - ' . SITE_TITLE;
$currentMenu = 'gallery';

ob_start();
?>
<style>
    .masonry-grid { column-count: 1; column-gap: 1.5rem; }
    @media (min-width: 640px) { .masonry-grid { column-count: 2; } }
    @media (min-width: 1024px) { .masonry-grid { column-count: 3; } }
    @media (min-width: 1280px) { .masonry-grid { column-count: 4; } }
    .masonry-item { break-inside: avoid; margin-bottom: 1.5rem; }
</style>
<?php
$additionalHead = ob_get_clean();
require_once ThemeManager::getHeader();
?>

    <div class="flex-grow max-w-[1600px] mx-auto w-full px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-10 text-center md:text-left">
            <h1 class="text-4xl font-extrabold text-white mb-4">Visual Gallery</h1>
            <p class="text-lg text-gray-400 max-w-3xl">A continuous stream of imagery and videos from our historical collections.</p>
        </div>

        <?php if (empty($mediaItems)): ?>
            <div class="text-center py-20 bg-gray-800 rounded-xl border border-gray-700">
                <svg class="mx-auto h-12 w-12 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                <h3 class="mt-2 text-sm font-medium text-gray-300">No media found</h3>
                <p class="mt-1 text-sm text-gray-500">There are currently no visible images or videos in the collection.</p>
            </div>
        <?php else: ?>
            <div class="masonry-grid">
                <?php foreach ($mediaItems as $media): ?>
                    <a href="<?= SITE_URL ?>/item/<?= $media['item_id'] ?>" class="masonry-item group block relative bg-gray-800 rounded-lg overflow-hidden border border-gray-700 hover:border-gray-600 hover:shadow-xl hover:shadow-purple-900/20 transition-all duration-300">
                        <?php if ($media['media_type'] === 'image'): ?>
                            <?php $imgSrc = isset($storage) ? $storage->url('display/' . $media['file_path']) : SITE_URL . '/uploads/display/' . $media['file_path']; ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($media['title']) ?>" class="w-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                        <?php elseif ($media['media_type'] === 'youtube'): ?>
                            <?php $ytId = MediaProcessor::extractYoutubeId($media['youtube_url']); ?>
                            <div class="relative w-full pb-[56.25%] bg-black">
                                <img src="https://img.youtube.com/vi/<?= htmlspecialchars($ytId) ?>/maxresdefault.jpg" onerror="this.src='https://img.youtube.com/vi/<?= htmlspecialchars($ytId) ?>/hqdefault.jpg'" class="absolute inset-0 w-full h-full object-cover opacity-80 group-hover:scale-105 transition-transform duration-500" loading="lazy">
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <div class="bg-red-600/90 text-white rounded-full p-3 shadow-lg group-hover:bg-red-600 transition-colors"><svg class="w-8 h-8 translate-x-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/50 to-transparent p-6 translate-y-2 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 flex flex-col justify-end pointer-events-none">
                            <span class="text-xs font-bold tracking-wider text-purple-300 uppercase mb-1"><?= htmlspecialchars($media['reg_number']) ?></span>
                            <h3 class="text-white font-medium text-sm leading-tight line-clamp-2"><?= htmlspecialchars($media['title']) ?></h3>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php require_once ThemeManager::getFooter(); ?>
