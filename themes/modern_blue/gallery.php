<?php
// themes/modern_blue/gallery.php
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

    <div class="flex-grow max-w-[1600px] mx-auto w-full px-4 sm:px-6 lg:px-8 py-10 md:py-16">
        <div class="mb-12 text-center max-w-3xl mx-auto">
            <h1 class="text-4xl md:text-5xl font-extrabold text-slate-900 mb-5 tracking-tight">Visual Gallery</h1>
            <p class="text-lg md:text-xl text-slate-500 font-light">A continuous stream of imagery and videos from our collections.</p>
        </div>

        <?php if (empty($mediaItems)): ?>
            <div class="text-center py-24 bg-white rounded-3xl border border-slate-200 shadow-sm max-w-lg mx-auto">
                <svg class="mx-auto h-16 w-16 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-4 text-xl font-bold text-slate-900">No media found</h3>
                <p class="mt-2 text-slate-500">There are currently no visible images or videos.</p>
            </div>
        <?php else: ?>
            <div id="gallery-grid" class="masonry-grid">
                <?php require ThemeManager::getTemplatePath('partials/gallery_items.php'); ?>
            </div>

            <!-- Scroll Sentinel & Loader -->
            <div id="scroll-sentinel" class="py-20 flex justify-center">
                <div id="loader" class="flex flex-col items-center gap-4 text-slate-400 animate-pulse">
                    <div class="w-10 h-10 border-4 border-modern-600/30 border-t-modern-600 rounded-full animate-spin"></div>
                    <span class="text-sm font-medium tracking-widest uppercase">Loading more records...</span>
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
                        if (isLoading || !hasMore) return;
                        isLoading = true;
                        page++;
                        
                        try {
                            const response = await fetch(`<?= SITE_URL ?>/gallery.php?page=${page}&ajax=1`);
                            if (!response.ok) throw new Error('Network response was not ok');
                            
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
                            sentinel.innerHTML = '<p class="text-slate-500">End of records.</p>';
                        } finally {
                            isLoading = false;
                        }
                    }
                });
            </script>
        <?php endif; ?>
    </div>

<?php require_once ThemeManager::getFooter(); ?>
