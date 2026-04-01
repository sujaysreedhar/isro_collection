<?php
// themes/default/gallery.php
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

    <div class="flex-grow max-w-[1600px] mx-auto w-full px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-10 text-center md:text-left">
            <h1 class="text-4xl font-extrabold serif mb-4">Visual Gallery</h1>
            <p class="text-lg text-gray-600 max-w-3xl">A continuous stream of imagery and videos from our historical collections.</p>
        </div>

        <?php if (empty($mediaItems)): ?>
            <div class="text-center py-20 bg-white rounded-xl border border-gray-200">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No media found</h3>
                <p class="mt-1 text-sm text-gray-500">There are currently no visible images or videos in the collection.</p>
            </div>
        <?php else: ?>
            <div id="gallery-grid" class="masonry-grid">
                <?php require ThemeManager::getTemplatePath('partials/gallery_items.php'); ?>
            </div>

            <!-- Scroll Sentinel & Loader -->
            <div id="scroll-sentinel" class="py-20 flex justify-center">
                <div id="loader" class="flex flex-col items-center gap-2 text-gray-400">
                    <div class="w-8 h-8 border-4 border-gray-200 border-t-blue-600 rounded-full animate-spin"></div>
                    <span class="text-sm font-medium">Loading more artifacts...</span>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    let page = 1;
                    let isLoading = false;
                    let hasMore = true;
                    const grid = document.getElementById('gallery-grid');
                    const sentinel = document.getElementById('scroll-sentinel');

                    const observer = new IntersectionObserver((entries) => {
                        if (entries[0].isIntersecting && !isLoading && hasMore) {
                            loadMore();
                        }
                    }, { rootMargin: '400px' });

                    observer.observe(sentinel);

                    async function loadMore() {
                        isLoading = true;
                        page++;
                        
                        try {
                            const response = await fetch(`<?= SITE_URL ?>/gallery.php?page=${page}&ajax=1`);
                            const html = await response.text();
                            
                            if (!html.trim()) {
                                hasMore = false;
                                sentinel.style.display = 'none';
                                return;
                            }

                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const items = Array.from(doc.body.children);
                            
                            if (items.length === 0) {
                                hasMore = false;
                                sentinel.style.display = 'none';
                                return;
                            }

                            items.forEach(item => grid.appendChild(item));
                            
                        } catch (error) {
                            console.error('Failed to load more items:', error);
                            hasMore = false;
                            sentinel.innerHTML = '<p class="text-gray-500">End of records.</p>';
                        } finally {
                            isLoading = false;
                        }
                    }
                });
            </script>
        <?php endif; ?>
    </div>

<?php require_once ThemeManager::getFooter(); ?>
