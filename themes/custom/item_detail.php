<?php
require_once __DIR__ . '/partials/archive_helpers.php';
$pageTitle = $item['title'] . ' - ' . SITE_TITLE;
$currentMenu = '';
$bodyClass = 'archive-ui';
$primaryImageUrl = $primaryMedia ? MediaProcessor::url($primaryMedia['file_path'], 'display', 'image', $storage ?? null) : '';
$primaryLicense = $primaryMedia['license_type'] ?? '';
require_once ThemeManager::getHeader();
?>
<main class="archive-page archive-detail">
    <nav class="archive-breadcrumbs" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= SITE_URL ?>" aria-label="Home"><?= archiveUiIcon('home') ?></a></li>
            <li><?= archiveUiIcon('chevron-right') ?><a href="<?= SITE_URL ?>/search.php">Collections</a></li>
            <?php foreach ($itemCategories as $cat): ?>
            <li><?= archiveUiIcon('chevron-right') ?><a href="<?= SITE_URL ?>/search.php?category_ids[]=<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
            <?php endforeach; ?>
            <li aria-current="page"><?= archiveUiIcon('chevron-right') ?><span><?= htmlspecialchars($item['title']) ?></span></li>
        </ol>
    </nav>
    <?php if (class_exists('HookRegistry')) HookRegistry::doAction('item_before_content', $item); ?>

    <div class="archive-detail-grid">
        <section class="archive-gallery archive-panel" aria-label="Item images">
            <div class="main-image-container archive-main-image">
                <?php if ($primaryMedia): ?>
                <figure>
                    <img id="main-viewer" src="<?= htmlspecialchars($primaryImageUrl) ?>" alt="<?= htmlspecialchars($item['title'] . ' - ' . $item['reg_number']) ?>" data-caption="<?= htmlspecialchars($primaryMedia['caption'] ?? '') ?>" tabindex="0" role="button" aria-label="View full size image of <?= htmlspecialchars($item['title']) ?>" width="1200" height="800" fetchpriority="high">
                    <figcaption class="sr-only"><?= htmlspecialchars($item['title']) ?></figcaption>
                </figure>
                <button class="archive-expand" type="button" data-open-viewer aria-label="Expand image"><?= archiveUiIcon('arrows-pointing-out') ?></button>
                <?php else: ?>
                <img id="main-viewer" style="display:none" alt="">
                <div class="archive-empty"><?= archiveUiIcon('photo') ?><p>Digitization Pending</p></div>
                <?php endif; ?>
                <div id="panorama-viewer" class="absolute inset-0 w-full h-full hidden"></div>
            </div>
            <div class="archive-thumbnails" aria-label="Choose an image">
                <?php foreach ($imageMedia as $index => $img):
                    $thumbSrc = MediaProcessor::url($img['file_path'], 'thumbnails', 'image', $storage ?? null);
                    $fullSrc = MediaProcessor::url($img['file_path'], 'display', 'image', $storage ?? null);
                ?>
                <button type="button" class="archive-thumbnail-button" aria-label="View image <?= $index + 1 ?> of <?= count($imageMedia) ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>" onclick="switchImage(this.querySelector('img'))">
                    <img src="<?= htmlspecialchars($thumbSrc) ?>" class="gallery-thumbnail<?= $index === 0 ? ' active' : '' ?>" data-full="<?= htmlspecialchars($fullSrc) ?>" data-mv-index="<?= $index ?>" data-caption="<?= htmlspecialchars($img['caption'] ?? '') ?>" data-license="<?= htmlspecialchars($img['license_type'] ?? '') ?>" alt="<?= htmlspecialchars($item['title'] . ' - View ' . ($index + 1)) ?>" width="88" height="58" loading="lazy">
                </button>
                <?php endforeach; ?>
                <?php if (class_exists('HookRegistry')) HookRegistry::doAction('item_gallery_thumbnails', $item); ?>
            </div>
            <p id="image-caption" class="archive-image-caption"<?= empty($primaryMedia['caption']) ? ' hidden' : '' ?>><?= htmlspecialchars($primaryMedia['caption'] ?? '') ?></p>
        </section>

        <section class="archive-item-info" aria-labelledby="item-title">
            <header class="archive-item-heading">
                <?php if ($itemCategories): ?><span class="archive-category-badge"><?= archiveUiIcon('tag') ?> <?= htmlspecialchars($itemCategories[0]['name']) ?></span><?php endif; ?>
                <h1 id="item-title"><?= htmlspecialchars($item['title']) ?></h1>
                <span class="archive-title-rule" aria-hidden="true"></span>
            </header>
            <div class="archive-specs archive-panel">
                <dl class="archive-spec-column">
                    <div><?= archiveUiIcon('clipboard-document-check') ?><dt>Registration No.</dt><dd><?= htmlspecialchars($item['reg_number']) ?></dd></div>
                    <?php if (!empty($item['production_date'])): ?><div><?= archiveUiIcon('calendar-days') ?><dt>Date</dt><dd><?= htmlspecialchars(archiveUiDate($item['production_date'])) ?></dd></div><?php endif; ?>
                    <div><?= archiveUiIcon('eye') ?><dt>Total Views</dt><dd><?= number_format(($item['view_count'] ?? 0) + 1) ?></dd></div>
                    <?php if (!empty($item['material'])): ?><div><?= archiveUiIcon('square-3-stack-3d') ?><dt>Material</dt><dd><?= htmlspecialchars($item['material']) ?></dd></div><?php endif; ?>
                </dl>
                <dl class="archive-spec-column">
                    <div><?= archiveUiIcon('tag') ?><dt>Category</dt><dd><?php foreach ($itemCategories as $index => $cat): ?><?= $index ? ', ' : '' ?><a href="<?= SITE_URL ?>/search.php?category_ids[]=<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></a><?php endforeach; ?><?= !$itemCategories ? 'Uncategorized' : '' ?></dd></div>
                    <?php if ($imageMedia): ?><div><?= archiveUiIcon('square-3-stack-3d') ?><dt>Image License</dt><dd id="archive-image-license"><?= htmlspecialchars($primaryLicense ?: 'Not specified') ?></dd></div><?php endif; ?>
                    <?php if (!empty($item['credit_line'])): ?><div><?= archiveUiIcon('book-open') ?><dt>Provenance</dt><dd><?= htmlspecialchars($item['credit_line']) ?></dd></div><?php endif; ?>
                </dl>
            </div>
            <div class="archive-actions">
                <?php if ($primaryMedia): ?>
                <button type="button" class="archive-button archive-button--primary" data-open-viewer><?= archiveUiIcon('magnifying-glass-plus') ?> View Full Size</button>
                <a id="archive-download" class="archive-button" href="<?= htmlspecialchars($primaryImageUrl) ?>" download target="_blank" rel="noopener"><?= archiveUiIcon('arrow-down-tray') ?> Download</a>
                <?php endif; ?>
                <button type="button" class="archive-button" data-archive-favorite aria-pressed="false"><?= archiveUiIcon('heart') ?><span data-favorite-label>Add to Favorites</span></button>
                <button type="button" class="archive-button" data-archive-share><?= archiveUiIcon('share') ?> Share</button>
            </div>
            <p class="archive-action-status" role="status" aria-live="polite" data-archive-status></p>
            <div class="archive-share-fallback" hidden><label for="archive-share-url">Copy this item link</label><input id="archive-share-url" type="url" readonly value="<?= htmlspecialchars(SITE_URL . '/item/' . (int) $item['id']) ?>"></div>
            <?php if ($itemTags): ?>
            <div class="archive-tags" aria-label="Item tags"><?php foreach ($itemTags as $tag): ?><a href="<?= SITE_URL ?>/tag/<?= htmlspecialchars($tag['slug']) ?>">#<?= htmlspecialchars($tag['name']) ?></a><?php endforeach; ?></div>
            <?php endif; ?>
        </section>
    </div>

    <div class="archive-description-grid">
        <?php if (trim(strip_tags($item['physical_description'] ?? '')) !== ''): ?>
        <section class="archive-description archive-panel" aria-labelledby="description-title">
            <h2 id="description-title"><?= archiveUiIcon('book-open') ?> Physical Description</h2>
            <div class="quill-content"><?= $item['physical_description'] ?></div>
        </section>
        <?php endif; ?>
        <?php if (trim(strip_tags($item['historical_significance'] ?? '')) !== ''): ?>
        <section class="archive-description archive-description--history archive-panel" aria-labelledby="history-title">
            <h2 id="history-title"><?= archiveUiIcon('globe-alt') ?> Historical Significance</h2>
            <div class="quill-content"><?= $item['historical_significance'] ?></div>
        </section>
        <?php endif; ?>
    </div>

    <?php if ($youtubeMedia || $pdfMedia): ?>
    <section class="archive-section" aria-labelledby="media-title">
        <div class="archive-section-heading"><h2 id="media-title"><?= archiveUiIcon('document-text') ?> Related Media</h2></div>
        <div class="archive-media-grid">
            <?php foreach ($youtubeMedia as $yt): ?>
            <div class="archive-video archive-panel"><iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($yt['file_path']) ?>" title="<?= htmlspecialchars($yt['caption'] ?: $item['title']) ?>" loading="lazy" allowfullscreen></iframe></div>
            <?php endforeach; ?>
            <?php foreach ($pdfMedia as $pdf): ?>
            <a href="<?= htmlspecialchars(MediaProcessor::url($pdf['file_path'], 'pdfs', 'pdf', $storage ?? null)) ?>" target="_blank" rel="noopener" class="archive-document archive-panel"><?= archiveUiIcon('document-text') ?><span><strong><?= htmlspecialchars($pdf['caption'] ?: 'Download Document') ?></strong><small>PDF · <?= round(($pdf['file_size'] ?? 0) / 1024, 1) ?> KB</small></span><?= archiveUiIcon('arrow-down-tray') ?></a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($stories): ?>
    <section class="archive-section" aria-labelledby="stories-title">
        <div class="archive-section-heading"><h2 id="stories-title"><?= archiveUiIcon('book-open') ?> Related Stories</h2></div>
        <div class="archive-media-grid">
            <?php foreach ($stories as $story): ?>
            <a class="archive-story archive-panel" href="<?= SITE_URL ?>/story/<?= (int) $story['id'] ?>"><h3><?= htmlspecialchars($story['title']) ?></h3><p><?= htmlspecialchars(archiveUiExcerpt($story['content_body'])) ?></p></a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <?php if (AppConfig::get('site_show_citation', '1') === '1'): ?>
    <section class="archive-citation archive-panel"><h2>How to Cite</h2><div><?= $citation ?></div></section>
    <?php endif; ?>

    <?php if ($relatedItems): ?>
    <section class="archive-section" aria-labelledby="related-title">
        <div class="archive-section-heading"><h2 id="related-title"><?= archiveUiIcon('square-3-stack-3d') ?> Related Items</h2></div>
        <div class="archive-related-grid">
            <?php foreach (array_slice($relatedItems, 0, 4) as $ri): ?>
            <a class="archive-related-card archive-panel" href="<?= SITE_URL ?>/item/<?= (int) $ri['id'] ?>">
                <div><?php if (!empty($ri['thumb'])): ?><img src="<?= htmlspecialchars(MediaProcessor::url($ri['thumb'], 'card', 'image', $storage ?? null)) ?>" alt="<?= htmlspecialchars($ri['title']) ?>" width="360" height="230" loading="lazy"><?php else: ?><?= archiveUiIcon('photo') ?><?php endif; ?></div>
                <span class="archive-reg"><?= htmlspecialchars($ri['reg_number']) ?></span><h3><?= htmlspecialchars($ri['title']) ?></h3>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</main>

<?php
$viewerImages = array_map(static fn($img) => [
    'src' => MediaProcessor::url($img['file_path'], 'display', 'image', $storage ?? null),
    'caption' => $img['caption'] ?? '',
    'license' => $img['license_type'] ?? '',
], $imageMedia);
$archiveItemData = [
    'id' => (int) $item['id'],
    'title' => $item['title'],
    'reg' => $item['reg_number'],
    'image' => $primaryImageUrl,
    'remember' => !empty($item['is_visible']),
    'images' => $viewerImages,
];
?>
<script type="application/json" id="archive-item-data"><?= json_encode($archiveItemData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<?php if ($imageMedia): ?>
<dialog class="archive-lightbox" id="archive-lightbox" aria-label="Full size item images">
    <div class="archive-lightbox-toolbar"><span><?= htmlspecialchars($item['title']) ?></span><button type="button" data-viewer-close aria-label="Close image viewer"><?= archiveUiIcon('x-mark') ?></button></div>
    <button type="button" class="archive-lightbox-prev" data-viewer-step="-1" aria-label="Previous image"><?= archiveUiIcon('chevron-left') ?></button>
    <div class="archive-lightbox-stage"><img id="archive-lightbox-image" alt="<?= htmlspecialchars($item['title']) ?>"></div>
    <button type="button" class="archive-lightbox-next" data-viewer-step="1" aria-label="Next image"><?= archiveUiIcon('chevron-right') ?></button>
    <p class="archive-lightbox-caption" id="archive-lightbox-caption"></p>
</dialog>
<?php endif; ?>
<?php
if (class_exists('HookRegistry')) HookRegistry::doAction('item_after_content', $item);
require_once ThemeManager::getFooter();
?>
