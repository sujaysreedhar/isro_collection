<?php
// themes/custom/item_detail.php — styled entirely with tc-* CSS vars from the custom theme.

$pageTitle = $item['title'] . ' — ' . SITE_TITLE;
$currentMenu = '';

require_once ThemeManager::getHeader();
?>

<div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-12">

    <!-- Breadcrumbs -->
    <nav class="flex text-xs font-semibold uppercase tracking-wider tc-text-muted mb-8" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= SITE_URL ?>" class="tc-accent-text hover:underline transition-colors">Archive</a></li>
            <?php if (!empty($item['category_name'])): ?>
                <li class="flex items-center">
                    <span class="mx-2">/</span>
                    <a href="<?= SITE_URL ?>/search.php?category_ids[]=<?= (int)$item['category_id'] ?>" class="tc-accent-text hover:underline transition-colors"><?= htmlspecialchars($item['category_name']) ?></a>
                </li>
            <?php endif; ?>
            <li class="flex items-center tc-text">
                <span class="mx-2">/</span>
                <span><?= htmlspecialchars($item['reg_number']) ?></span>
            </li>
        </ol>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-16">

        <!-- ── Left: Gallery & Media ─────────────────────────────────────── -->
        <div class="lg:col-span-7">

            <!-- Main Viewer -->
            <div class="main-image-container tc-surface tc-border mb-4">
                <?php if ($primaryMedia):
                    $displaySrc = MediaProcessor::url($primaryMedia['file_path'], 'display', 'image', $storage ?? null);
                ?>
                    <img id="main-viewer" src="<?= $displaySrc ?>"
                         alt="<?= htmlspecialchars($item['title']) ?>"
                         data-caption="<?= htmlspecialchars($primaryMedia['caption'] ?? '') ?>"
                         class="max-h-[680px] w-auto">
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center h-80 tc-text-muted">
                        <svg class="w-14 h-14 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-sm">Digitization Pending</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Secondary Thumbnails -->
            <?php if (count($imageMedia) > 1): ?>
            <div class="flex flex-wrap gap-3 mb-5">
                <?php foreach ($imageMedia as $i => $img):
                    $thumbSrc = MediaProcessor::url($img['file_path'], 'thumbs', 'image', $storage ?? null);
                    $fullSrc  = MediaProcessor::url($img['file_path'], 'display', 'image', $storage ?? null);
                    $active   = ($img['id'] === ($primaryMedia['id'] ?? null)) ? 'active' : '';
                ?>
                <img src="<?= $thumbSrc ?>"
                     class="gallery-thumbnail <?= $active ?>"
                     data-full="<?= $fullSrc ?>"
                     data-caption="<?= htmlspecialchars($img['caption'] ?? '') ?>"
                     onclick="switchImage(this)"
                     alt="View <?= $i + 1 ?>">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Caption Block -->
            <div id="caption-box" class="tc-surface tc-border tc-radius p-5">
                <p id="image-caption" class="text-sm tc-text-muted italic leading-relaxed">
                    <?= htmlspecialchars($primaryMedia['caption'] ?? 'No caption available for this view.') ?>
                </p>
                <?php if ($primaryMedia && !empty($primaryMedia['license_type'])): ?>
                <div class="mt-3 pt-3 border-t tc-border flex items-center justify-between">
                    <span class="text-xs tc-text-muted uppercase tracking-wider font-medium">License</span>
                    <span class="text-xs font-semibold tc-primary-text tc-surface tc-border px-3 py-1 tc-radius"><?= htmlspecialchars($primaryMedia['license_type']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Description (Moved from right column) -->
            <?php 
                $descClean = trim(strip_tags($item['physical_description'] ?? ''));
                if (!empty($descClean)): 
            ?>
            <div class="mt-8 tc-text text-base leading-relaxed serif quill-content">
                <h3 class="text-xs font-bold uppercase tracking-widest tc-text-muted mb-4 opacity-70">Physical Description</h3>
                <?= $item['physical_description'] ?>
            </div>
            <?php endif; ?>

            <!-- YouTube Embeds -->
            <?php foreach ($youtubeMedia as $yt): ?>
            <div class="mt-8 aspect-video tc-radius overflow-hidden tc-border">
                <iframe class="w-full h-full"
                        src="https://www.youtube.com/embed/<?= htmlspecialchars($yt['file_path']) ?>"
                        title="<?= htmlspecialchars($yt['caption'] ?? $item['title']) ?>"
                        frameborder="0" allowfullscreen></iframe>
            </div>
            <?php endforeach; ?>

            <!-- PDF Documents -->
            <?php foreach ($pdfMedia as $pdf): ?>
            <a href="<?= SITE_URL ?>/uploads/pdfs/<?= rawurlencode($pdf['file_path']) ?>" target="_blank"
               class="mt-4 flex items-center gap-4 p-5 tc-surface tc-border tc-radius hover:shadow-md transition-shadow group block">
                <div class="w-12 h-12 tc-radius flex items-center justify-center tc-text-muted">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold tc-text truncate"><?= htmlspecialchars($pdf['caption'] ?: 'Download Document') ?></p>
                    <p class="text-xs tc-text-muted">PDF · <?= round(($pdf['file_size'] ?? 0) / 1024, 1) ?> KB</p>
                </div>
                <svg class="w-4 h-4 tc-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- ── Right: Item Information ─────────────────────────────────── -->
        <div class="lg:col-span-5">

            <!-- Title & Tags -->
            <header class="mb-10">
                <h1 class="text-4xl font-extrabold tc-primary-text serif leading-tight mb-5">
                    <?= htmlspecialchars($item['title']) ?>
                </h1>
                <?php if ($itemTags): ?>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($itemTags as $tag): ?>
                        <a href="<?= SITE_URL ?>/tag/<?= htmlspecialchars($tag['slug']) ?>"
                           class="text-xs font-bold tc-accent-text tc-border tc-radius px-3 py-1 hover:tc-accent-bg hover:text-white transition-colors">
                            #<?= htmlspecialchars($tag['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </header>

            <!-- Specifications Table -->
            <div class="tc-surface tc-border tc-radius overflow-hidden mb-10">
                <div class="p-5 border-b tc-border">
                    <h3 class="text-xs font-bold uppercase tracking-widest tc-text-muted">Specifications</h3>
                </div>
                <dl class="divide-y" style="border-color: var(--color-border, #e5e7eb);">
                    <div class="p-5 grid grid-cols-3 gap-4">
                        <dt class="spec-label col-span-1">Reg #</dt>
                        <dd class="spec-value col-span-2 font-mono tc-accent-text"><?= htmlspecialchars($item['reg_number']) ?></dd>
                    </div>
                    <?php if (!empty($item['category_name'])): ?>
                    <div class="p-5 grid grid-cols-3 gap-4">
                        <dt class="spec-label col-span-1">Category</dt>
                        <dd class="col-span-2">
                            <a href="<?= SITE_URL ?>/search.php?category_ids[]=<?= (int)$item['category_id'] ?>" class="spec-value tc-accent-text hover:underline"><?= htmlspecialchars($item['category_name']) ?></a>
                        </dd>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($item['production_date'])): ?>
                    <div class="p-5 grid grid-cols-3 gap-4">
                        <dt class="spec-label col-span-1">Date</dt>
                        <dd class="spec-value col-span-2"><?= htmlspecialchars($item['production_date']) ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($item['material'])): ?>
                    <div class="p-5 grid grid-cols-3 gap-4">
                        <dt class="spec-label col-span-1">Material</dt>
                        <dd class="spec-value col-span-2"><?= htmlspecialchars($item['material']) ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($item['credit_line'])): ?>
                    <div class="p-5 flex flex-col sm:flex-row sm:items-center">
                        <span class="spec-label sm:w-1/3 mb-1 sm:mb-0">Provenance</span>
                        <span class="spec-value sm:w-2/3 tc-text-muted text-sm font-normal"><?= htmlspecialchars($item['credit_line']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="p-5 flex flex-col sm:flex-row sm:items-center bg-tc-accent-bg/5">
                        <span class="spec-label sm:w-1/3 mb-1 sm:mb-0">Total Views</span>
                        <span class="spec-value sm:w-2/3 tc-accent-text font-bold"><?= number_format(($item['view_count'] ?? 0) + 1) ?></span>
                    </div>
                    <?php 
                        $histClean = trim(strip_tags($item['historical_significance'] ?? ''));
                        if (!empty($histClean)): 
                    ?>
                    <div class="p-5">
                        <dt class="spec-label mb-2">Historical Significance</dt>
                        <dd class="tc-text text-sm leading-relaxed quill-content"><?= $item['historical_significance'] ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- Related Items Section -->
            <?php if (!empty($relatedItems)): ?>
            <div class="mb-12">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold tc-primary-text serif">You May Also Like</h3>
                    <div class="h-px flex-1 bg-tc-border ml-6 opacity-30"></div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 gap-6">
                    <?php foreach ($relatedItems as $ri): ?>
                    <a href="<?= SITE_URL ?>/item/<?= $ri['id'] ?>" class="group block h-full">
                        <div class="tc-surface tc-border tc-radius overflow-hidden h-full flex flex-col hover:shadow-lg transition-all hover:-translate-y-1">
                            <div class="aspect-[4/3] bg-tc-accent-bg/5 flex items-center justify-center overflow-hidden">
                                <?php if ($ri['thumb']): ?>
                                    <img src="<?= MediaProcessor::url($ri['thumb'], 'thumbs', 'image', $storage ?? null) ?>" 
                                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="<?= htmlspecialchars($ri['title']) ?>">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gray-100">
                                        <svg class="w-10 h-10 tc-text-muted opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-4 flex-1">
                                <span class="text-[9px] font-bold uppercase tracking-widest tc-text-muted mb-1 block"><?= htmlspecialchars($ri['reg_number']) ?></span>
                                <h4 class="text-xs font-bold tc-text group-hover:tc-accent-text line-clamp-2 leading-snug"><?= htmlspecialchars($ri['title']) ?></h4>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Related Stories -->
            <?php if ($stories): ?>
            <div class="mb-10">
                <h3 class="text-lg font-bold tc-primary-text serif mb-5">Related Stories</h3>
                <div class="space-y-3">
                    <?php foreach ($stories as $story): ?>
                    <a href="<?= SITE_URL ?>/story/<?= (int)$story['id'] ?>"
                       class="block p-5 tc-surface tc-border tc-radius hover:shadow-md transition-shadow">
                        <h4 class="font-semibold tc-accent-text hover:underline mb-1"><?= htmlspecialchars($story['title']) ?></h4>
                        <p class="text-sm tc-text-muted line-clamp-2"><?= htmlspecialchars(substr(strip_tags($story['content_body']), 0, 140)) ?>…</p>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Citation -->
            <div class="tc-surface tc-border tc-radius p-5">
                <h4 class="text-xs font-bold uppercase tracking-widest tc-text-muted mb-3">How to Cite</h4>
                <div class="text-xs font-mono tc-text leading-relaxed cursor-text select-all"><?= $citation ?></div>
            </div>
        </div>
    </div>
</div>

<script>
function switchImage(el) {
    const main    = document.getElementById('main-viewer');
    const caption = document.getElementById('image-caption');

    main.style.opacity = '0';
    setTimeout(() => {
        main.src          = el.dataset.full;
        caption.innerText = el.dataset.caption || 'No caption available for this view.';
        document.querySelectorAll('.gallery-thumbnail').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
        main.style.opacity = '1';
    }, 200);
}
</script>

<?php
if (class_exists('HookRegistry')) { HookRegistry::doAction('item_after_content', $item); }
require_once ThemeManager::getFooter();
?>
