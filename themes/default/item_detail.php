<?php
// themes/default/item_detail.php

$pageTitle = $item['title'] . ' - ' . SITE_TITLE;
$currentMenu = '';

// ── Build the zoomable image gallery data from all image media ──────────────
$mvFormatBytes = function ($bytes) {
    if (empty($bytes)) return '';
    $units = ['B', 'KB', 'MB', 'GB'];
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = (int) min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), 1) . ' ' . $units[$pow];
};

// URL helper honouring the S3/local storage driver
$mvUrl = function (string $folder, string $path) use ($storage) {
    return isset($storage)
        ? $storage->url($folder . '/' . $path)
        : SITE_URL . '/uploads/' . $folder . '/' . rawurlencode($path);
};

// Visible image: prefer the sized "display" render, fall back to the original
$mvDisplay = function (string $path) use ($storage, $mvUrl) {
    if (!isset($storage)) {
        return is_file(ABSPATH . '/uploads/display/' . $path)
            ? $mvUrl('display', $path)
            : $mvUrl('originals', $path);
    }
    return $mvUrl('display', $path);
};

// Zoom source: the full-resolution original when available
$mvFull = function (string $path) use ($storage, $mvUrl) {
    if (!isset($storage)) {
        return is_file(ABSPATH . '/uploads/originals/' . $path)
            ? $mvUrl('originals', $path)
            : $mvUrl('display', $path);
    }
    return $mvUrl('originals', $path);
};

$galleryImages = [];
foreach ($imageMedia as $img) {
    $fp = $img['file_path'] ?? '';
    if ($fp === '') continue;
    $galleryImages[] = [
        'thumb'      => $mvUrl('thumbnails', $fp),
        'display'    => $mvDisplay($fp),
        'full'       => $mvFull($fp),
        'caption'    => $img['caption'] ?? '',
        'license'    => $img['license_type'] ?? '',
        'dimensions' => $img['dimensions'] ?? '',
        'filesize'   => $mvFormatBytes($img['file_size'] ?? 0),
        'mime'       => (!empty($img['mime_type']) && $img['mime_type'] !== 'image/youtube')
            ? strtoupper(str_replace('image/', '', $img['mime_type'])) : '',
        'date'       => !empty($img['upload_date']) ? date('M j, Y', strtotime($img['upload_date'])) : '',
        'alt'        => trim($item['title'] . ($img['caption'] ? ' — ' . $img['caption'] : '')),
    ];
}

require_once ThemeManager::getHeader();
?>
    <div class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-10 flex flex-col md:flex-row gap-8">
        
        <!-- Main Content Area -->
        <main class="flex-1 min-w-0">
            <!-- Breadcrumbs -->
            <nav class="flex text-sm text-gray-500 mb-8" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center"><a href="<?= SITE_URL ?>" class="hover:text-gray-900">Collections</a></li>
                    <?php if (!empty($itemCategories)): ?>
                        <?php foreach ($itemCategories as $idx => $cat): ?>
                        <li>
                            <div class="flex items-center">
                                <svg class="w-4 h-4 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                <a href="<?= SITE_URL ?>/search.php?category_ids[]=<?= (int)$cat['id'] ?>" class="hover:text-gray-900"><?= htmlspecialchars($cat['name']) ?></a>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                            <span class="text-gray-800"><?= htmlspecialchars($item['title']) ?></span>
                        </div>
                    </li>
                </ol>
            </nav>

            <!-- Item Detail Grid -->
            <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('item_before_content', $item); } ?>
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-12">
                
                <!-- Left Column: Media Viewer -->
                <div class="xl:col-span-7">
                  <?php if (!empty($galleryImages)): ?>
                    <div class="mv" id="mediaViewer" data-gallery='<?= htmlspecialchars(json_encode($galleryImages, JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES) ?>'>
                        <!-- Main stage -->
                        <div class="mv-stage bg-gray-100 border border-gray-200 rounded-t relative overflow-hidden" role="button" tabindex="0" aria-label="Open full-screen zoom">
                            <img id="mvMain" src="<?= htmlspecialchars($galleryImages[0]['display']) ?>" alt="<?= htmlspecialchars($galleryImages[0]['alt']) ?>" class="w-full h-auto block select-none">
                            <span class="mv-zoom-hint" aria-hidden="true">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 8v6M8 11h6m5 0a8 8 0 11-16 0 8 8 0 0116 0z"/></svg>
                                Click to zoom
                            </span>
                        </div>

                        <!-- Caption & license (updates on image change) -->
                        <div class="bg-gray-50 flex justify-between items-start gap-3 text-sm text-gray-500 p-4 border-x border-gray-200">
                            <cite id="mvCaption" class="not-italic font-medium text-gray-600"><?= htmlspecialchars($galleryImages[0]['caption'] ?: 'No description provided.') ?></cite>
                            <span id="mvLicense" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-800 <?= $galleryImages[0]['license'] ? '' : 'hidden' ?>"><?= htmlspecialchars($galleryImages[0]['license']) ?></span>
                        </div>

                        <!-- Technical metadata (updates on image change) -->
                        <div id="mvMeta" class="bg-white border-b border-x border-gray-200 text-xs text-gray-500 p-3 rounded-b-lg flex flex-wrap gap-x-6 gap-y-2 <?= count($galleryImages) > 1 ? '' : 'mb-4' ?>">
                            <div class="mv-m-dim flex items-center gap-1.5 <?= $galleryImages[0]['dimensions'] ? '' : 'hidden' ?>"><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg><span class="font-medium text-gray-700"><?= htmlspecialchars($galleryImages[0]['dimensions']) ?> px</span></div>
                            <div class="mv-m-size flex items-center gap-1.5 <?= $galleryImages[0]['filesize'] ? '' : 'hidden' ?>"><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg><span class="font-medium text-gray-700"><?= htmlspecialchars($galleryImages[0]['filesize']) ?></span></div>
                            <div class="mv-m-mime flex items-center gap-1.5 <?= $galleryImages[0]['mime'] ? '' : 'hidden' ?>"><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg><span class="font-medium text-gray-700"><?= htmlspecialchars($galleryImages[0]['mime']) ?></span></div>
                            <div class="mv-m-date flex items-center gap-1.5 <?= $galleryImages[0]['date'] ? '' : 'hidden' ?>"><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg><span class="font-medium text-gray-700">Added <?= htmlspecialchars($galleryImages[0]['date']) ?></span></div>
                        </div>

                        <!-- Thumbnail strip (only when >1 image) -->
                        <?php if (count($galleryImages) > 1): ?>
                        <div class="mv-thumbs flex flex-wrap gap-2 mt-3 mb-4" role="listbox" aria-label="Item images">
                            <?php foreach ($galleryImages as $gi => $g): ?>
                            <button type="button" class="mv-thumb <?= $gi === 0 ? 'is-active' : '' ?>" data-index="<?= $gi ?>" role="option" aria-selected="<?= $gi === 0 ? 'true' : 'false' ?>" aria-label="View image <?= $gi + 1 ?>">
                                <img src="<?= htmlspecialchars($g['thumb']) ?>" alt="" loading="lazy">
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <div class="border border-gray-200 rounded bg-gray-100 flex items-center justify-center h-[500px] w-full text-gray-400 flex-col">
                        <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span>No Image Available</span>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Right Column: Item Information -->
                <div class="xl:col-span-5">
                    <div class="mb-2">
                        <span class="text-sm font-semibold tracking-wider text-gray-500 uppercase">Reg: <?= htmlspecialchars($item['reg_number']) ?></span>
                    </div>
                    <h1 class="text-4xl font-bold mb-4 leading-tight"><?= htmlspecialchars($item['title']) ?></h1>

                    <?php if ($itemTags): ?>
                    <div class="flex flex-wrap gap-2 mb-6">
                        <?php foreach ($itemTags as $tag): ?>
                            <a href="<?= SITE_URL ?>/tag/<?= htmlspecialchars($tag['slug']) ?>"
                               class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-800 hover:text-white transition-colors duration-200">
                                <span class="mr-1 text-gray-400">#</span><?= htmlspecialchars($tag['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="prose prose-gray mb-8 serif text-lg leading-relaxed text-gray-700">
                        <p>
                            <?= nl2br(htmlspecialchars($item['physical_description'] ?? '')) ?>
                        </p>
                    </div>

                    <!-- Structured Specifications Table -->
                    <div class="border-t border-gray-200 pt-6 mb-10">
                        <h3 class="text-lg font-bold mb-4">Specifications</h3>
                        <dl class="divide-y divide-gray-200 text-sm">
                            <?php if (!empty($itemCategories)): ?>
                            <div class="py-3 flex justify-between">
                                <dt class="text-gray-500 font-medium w-1/3">Categories</dt>
                                <dd class="text-gray-900 w-2/3 text-right flex flex-wrap justify-end gap-x-2">
                                    <?php foreach ($itemCategories as $idx => $cat): ?>
                                        <a href="<?= SITE_URL ?>/search.php?category_ids[]=<?= (int)$cat['id'] ?>" class="text-blue-600 hover:text-blue-800 hover:underline" title="Find more items in this category">
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </a><?= ($idx < count($itemCategories) - 1) ? ',' : '' ?>
                                    <?php endforeach; ?>
                                </dd>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['production_date'])): ?>
                            <div class="py-3 flex justify-between">
                                <dt class="text-gray-500 font-medium w-1/3">Production Date</dt>
                                <dd class="text-gray-900 w-2/3 text-right">
                                    <a href="<?= SITE_URL ?>/search.php?q=<?= urlencode($item['production_date']) ?>" class="text-blue-600 hover:text-blue-800 hover:underline" title="Find more items from this period">
                                        <?= htmlspecialchars($item['production_date']) ?>
                                    </a>
                                </dd>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['credit_line'])): ?>
                            <div class="py-3 flex justify-between">
                                <dt class="text-gray-500 font-medium w-1/3">Credit Line</dt>
                                <dd class="text-gray-900 w-2/3 text-right">
                                    <a href="<?= SITE_URL ?>/search.php?q=<?= urlencode($item['credit_line']) ?>" class="text-blue-600 hover:text-blue-800 hover:underline" title="Find more items from this collection/donor">
                                        <?= htmlspecialchars($item['credit_line']) ?>
                                    </a>
                                </dd>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($item['historical_significance'])): ?>
                            <div class="py-3 flex flex-col sm:flex-row justify-between">
                                <dt class="text-gray-500 font-medium w-full sm:w-1/3 mb-1 sm:mb-0">Historical Significance</dt>
                                <dd class="text-gray-900 w-full sm:w-2/3 sm:text-right"><?= htmlspecialchars($item['historical_significance']) ?></dd>
                            </div>
                            <?php endif; ?>
                        </dl>
                    </div>

                    <!-- YouTube Videos -->
                    <?php if ($youtubeMedia): ?>
                    <div class="mb-10">
                        <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                            <span class="bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded">▶ VIDEO</span>
                            Video<?= count($youtubeMedia) > 1 ? 's' : '' ?>
                        </h3>
                        <div class="space-y-4">
                            <?php foreach ($youtubeMedia as $yt): ?>
                            <div class="rounded-lg overflow-hidden shadow-sm border border-gray-200">
                                <div class="relative w-full" style="padding-top:56.25%">
                                    <iframe class="absolute inset-0 w-full h-full"
                                            src="https://www.youtube.com/embed/<?= htmlspecialchars($yt['file_path']) ?>"
                                            title="<?= htmlspecialchars($yt['caption'] ?? $item['title']) ?>"
                                            frameborder="0"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                            allowfullscreen></iframe>
                                </div>
                                <?php if (!empty($yt['caption'])): ?>
                                <div class="bg-gray-50 px-4 py-2 text-sm text-gray-500 italic"><?= htmlspecialchars($yt['caption']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- PDF Documents -->
                    <?php if ($pdfMedia): ?>
                    <div class="mb-10">
                        <h3 class="text-lg font-bold mb-4">📄 Documents</h3>
                        <div class="space-y-2">
                            <?php foreach ($pdfMedia as $pdf): ?>
                            <a href="<?= SITE_URL ?>/uploads/pdfs/<?= rawurlencode($pdf['file_path']) ?>"
                               target="_blank"
                               class="flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-red-400 hover:shadow-sm transition group">
                                <svg class="w-8 h-8 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                <div>
                                    <p class="text-sm font-medium text-gray-800 group-hover:text-red-700"><?= htmlspecialchars($pdf['caption'] ?: basename($pdf['file_path'])) ?></p>
                                    <?php if (!empty($pdf['file_size'])): ?>
                                    <p class="text-xs text-gray-400"><?= round($pdf['file_size'] / 1024, 1) ?> KB · PDF</p>
                                    <?php endif; ?>
                                </div>
                                <svg class="w-4 h-4 text-gray-400 ml-auto group-hover:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Related Stories (Narratives) -->
                    <?php if (count($stories) > 0): ?>
                    <div class="mb-10 bg-white p-6 shadow-sm border border-gray-200 border-l-4 border-l-gray-800">
                        <h3 class="text-lg font-bold mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            Related Stories
                        </h3>
                        <ul class="space-y-4">
                            <?php foreach ($stories as $story): ?>
                            <li>
                                <a href="<?= SITE_URL ?>/story/<?= (int)$story['id'] ?>" class="group block">
                                    <h4 class="text-md font-semibold text-blue-800 group-hover:underline transition-colors"><?= htmlspecialchars($story['title']) ?></h4>
                                    <p class="text-sm text-gray-600 mt-1 line-clamp-2">
                                        <?= htmlspecialchars(substr(strip_tags($story['content_body']), 0, 150)) ?>...
                                    </p>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- How to Cite -->
                    <div class="border border-gray-200 rounded p-4 bg-white">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-2">How to Cite</h3>
                        <div class="bg-gray-50 p-3 border border-gray-200 font-mono text-xs text-gray-800 break-words cursor-text select-all">
                            <?= $citation ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('item_after_content', $item); } ?>
        </main>
    </div>

<?php if (!empty($galleryImages)): ?>
<!-- ── Zoomable media viewer: styles, lightbox, behaviour ─────────────────── -->
<style>
    .mv-stage { cursor: zoom-in; }
    .mv-stage:focus-visible { outline: 2px solid #111827; outline-offset: 2px; }
    .mv-zoom-hint {
        position: absolute; bottom: .75rem; right: .75rem;
        display: inline-flex; align-items: center; gap: .3rem;
        background: rgba(17,24,39,.78); color: #fff;
        font-size: .72rem; font-weight: 600; letter-spacing: .02em;
        padding: .28rem .6rem; border-radius: 999px;
        opacity: 0; transition: opacity .18s ease; pointer-events: none;
    }
    .mv-stage:hover .mv-zoom-hint, .mv-stage:focus-visible .mv-zoom-hint { opacity: 1; }

    .mv-thumb {
        width: 68px; height: 68px; border-radius: 6px; overflow: hidden;
        border: 2px solid transparent; background: #f3f4f6;
        padding: 0; cursor: pointer; transition: border-color .15s ease, opacity .15s ease;
        opacity: .75;
    }
    .mv-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .mv-thumb:hover { opacity: 1; }
    .mv-thumb.is-active { border-color: #111827; opacity: 1; }

    .mv-lightbox {
        position: fixed; inset: 0; z-index: 100;
        background: rgba(15,17,21,.94);
        display: none; align-items: center; justify-content: center;
        overscroll-behavior: contain;
    }
    .mv-lightbox.open { display: flex; }
    .mv-lightbox-img {
        max-width: 94vw; max-height: 90vh; object-fit: contain;
        cursor: zoom-in; transition: transform .12s ease-out;
        transform: scale(1); user-select: none; -webkit-user-drag: none;
    }
    .mv-lightbox-img.zoomed { cursor: zoom-out; transition: none; }
    .mv-lb-btn {
        position: absolute; top: 50%; transform: translateY(-50%);
        width: 48px; height: 48px; border-radius: 999px; border: none;
        background: rgba(255,255,255,.12); color: #fff; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background .15s ease;
    }
    .mv-lb-btn:hover { background: rgba(255,255,255,.25); }
    .mv-lb-prev { left: 1.25rem; }
    .mv-lb-next { right: 1.25rem; }
    .mv-lb-close { top: 1.1rem; right: 1.25rem; transform: none; }
    .mv-lb-counter {
        position: absolute; bottom: 1.1rem; left: 50%; transform: translateX(-50%);
        color: #e5e7eb; font-size: .8rem; letter-spacing: .03em;
        background: rgba(0,0,0,.4); padding: .3rem .8rem; border-radius: 999px;
    }
    @media (max-width: 640px) {
        .mv-lb-prev { left: .4rem; } .mv-lb-next { right: .4rem; }
        .mv-lb-btn { width: 42px; height: 42px; }
    }
</style>

<div class="mv-lightbox" id="mvLightbox" aria-modal="true" role="dialog" aria-label="Image viewer">
    <button type="button" class="mv-lb-btn mv-lb-close" id="mvClose" aria-label="Close">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <button type="button" class="mv-lb-btn mv-lb-prev" id="mvPrev" aria-label="Previous image">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </button>
    <img class="mv-lightbox-img" id="mvLightboxImg" src="" alt="">
    <button type="button" class="mv-lb-btn mv-lb-next" id="mvNext" aria-label="Next image">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div class="mv-lb-counter" id="mvCounter"></div>
</div>

<script>
(function () {
    var root = document.getElementById('mediaViewer');
    if (!root) return;
    var images;
    try { images = JSON.parse(root.getAttribute('data-gallery')); }
    catch (e) { return; }
    if (!images || !images.length) return;

    var current = 0;
    var single = images.length < 2;

    var mainImg  = document.getElementById('mvMain');
    var capEl    = document.getElementById('mvCaption');
    var licEl    = document.getElementById('mvLicense');
    var metaEl   = document.getElementById('mvMeta');
    var thumbs   = Array.prototype.slice.call(root.querySelectorAll('.mv-thumb'));

    var lb       = document.getElementById('mvLightbox');
    var lbImg    = document.getElementById('mvLightboxImg');
    var counter  = document.getElementById('mvCounter');
    var prevBtn  = document.getElementById('mvPrev');
    var nextBtn  = document.getElementById('mvNext');

    function setMetaField(sel, value, text) {
        var el = metaEl && metaEl.querySelector(sel);
        if (!el) return;
        if (value) { el.classList.remove('hidden'); el.querySelector('span').textContent = text; }
        else { el.classList.add('hidden'); }
    }

    // Update the inline stage (thumbnail click) without opening the lightbox
    function select(i) {
        current = (i + images.length) % images.length;
        var g = images[current];
        mainImg.src = g.display;
        mainImg.alt = g.alt || '';
        if (capEl) capEl.textContent = g.caption || 'No description provided.';
        if (licEl) { if (g.license) { licEl.classList.remove('hidden'); licEl.textContent = g.license; } else { licEl.classList.add('hidden'); } }
        setMetaField('.mv-m-dim',  g.dimensions, g.dimensions + ' px');
        setMetaField('.mv-m-size', g.filesize,   g.filesize);
        setMetaField('.mv-m-mime', g.mime,       g.mime);
        setMetaField('.mv-m-date', g.date,       'Added ' + g.date);
        thumbs.forEach(function (t, ti) {
            var on = ti === current;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    }

    thumbs.forEach(function (t) {
        t.addEventListener('click', function () { select(parseInt(t.getAttribute('data-index'), 10)); });
    });

    // ── Lightbox ──────────────────────────────────────────────────────────
    function resetZoom() {
        lbImg.classList.remove('zoomed');
        lbImg.style.transform = 'scale(1)';
        lbImg.style.transformOrigin = 'center center';
    }
    function renderLightbox() {
        var g = images[current];
        resetZoom();
        lbImg.src = g.full || g.display;
        lbImg.alt = g.alt || '';
        counter.textContent = single ? '' : (current + 1) + ' / ' + images.length;
    }
    function openLightbox() {
        renderLightbox();
        lb.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
        lb.classList.remove('open');
        document.body.style.overflow = '';
        resetZoom();
    }
    function step(delta) {
        current = (current + delta + images.length) % images.length;
        select(current);        // keep the inline stage in sync
        renderLightbox();
    }

    root.querySelector('.mv-stage').addEventListener('click', openLightbox);
    root.querySelector('.mv-stage').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openLightbox(); }
    });

    document.getElementById('mvClose').addEventListener('click', closeLightbox);
    prevBtn.addEventListener('click', function (e) { e.stopPropagation(); step(-1); });
    nextBtn.addEventListener('click', function (e) { e.stopPropagation(); step(1); });
    if (single) { prevBtn.style.display = 'none'; nextBtn.style.display = 'none'; }

    // Click the backdrop (not the image) to close
    lb.addEventListener('click', function (e) { if (e.target === lb) closeLightbox(); });

    // Cursor-anchored magnifier zoom
    lbImg.addEventListener('click', function (e) {
        e.stopPropagation();
        if (lbImg.classList.contains('zoomed')) { resetZoom(); return; }
        lbImg.classList.add('zoomed');
        applyZoom(e);
    });
    function applyZoom(e) {
        var r = lbImg.getBoundingClientRect();
        var x = ((e.clientX - r.left) / r.width) * 100;
        var y = ((e.clientY - r.top) / r.height) * 100;
        lbImg.style.transformOrigin = x + '% ' + y + '%';
        lbImg.style.transform = 'scale(2.6)';
    }
    lbImg.addEventListener('mousemove', function (e) {
        if (lbImg.classList.contains('zoomed')) applyZoom(e);
    });

    document.addEventListener('keydown', function (e) {
        if (!lb.classList.contains('open')) return;
        if (e.key === 'Escape') closeLightbox();
        else if (e.key === 'ArrowLeft' && !single) step(-1);
        else if (e.key === 'ArrowRight' && !single) step(1);
    });
})();
</script>
<?php endif; ?>

<?php require_once ThemeManager::getFooter(); ?>
