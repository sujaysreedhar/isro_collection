<?php
/** @var array $mediaItems */
/** @var StorageInterface $storage */
foreach ($mediaItems as $media): ?>
    <a href="<?= SITE_URL ?>/item/<?= $media['item_id'] ?>" class="masonry-item group block relative bg-gray-100 rounded-lg overflow-hidden border border-gray-200 hover:shadow-xl transition-all duration-300">
        <?php if ($media['media_type'] === 'image'): ?>
            <?php 
                $imgSrc = isset($storage) 
                    ? $storage->url('display/' . $media['file_path']) 
                    : SITE_URL . '/uploads/display/' . $media['file_path'];
            ?>
            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($media['title']) ?>" class="w-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
        <?php elseif ($media['media_type'] === 'youtube'): ?>
            <?php $ytId = MediaProcessor::extractYoutubeId($media['youtube_url']); ?>
            <div class="relative w-full pb-[56.25%] bg-black">
                <img src="https://img.youtube.com/vi/<?= htmlspecialchars($ytId) ?>/maxresdefault.jpg" onerror="this.src='https://img.youtube.com/vi/<?= htmlspecialchars($ytId) ?>/hqdefault.jpg'" class="absolute inset-0 w-full h-full object-cover opacity-80 group-hover:scale-105 transition-transform duration-500" loading="lazy">
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="bg-red-600/90 text-white rounded-full p-3 shadow-lg group-hover:bg-red-600 transition-colors">
                        <svg class="w-8 h-8 translate-x-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent p-6 translate-y-2 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 flex flex-col justify-end pointer-events-none">
            <span class="text-xs font-bold tracking-wider text-white/80 uppercase mb-1"><?= htmlspecialchars($media['reg_number']) ?></span>
            <h3 class="text-white font-medium text-sm leading-tight line-clamp-2"><?= htmlspecialchars($media['title']) ?></h3>
        </div>
    </a>
<?php endforeach; ?>
