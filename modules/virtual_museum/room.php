<?php
// Full-viewport 3D room and accessible 2D browse mode.
global $pdo;
if (!isset($pdo)) require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/ThemeManager.php';

$previewId = (int) ($_GET['preview_id'] ?? 0);
$slug = trim($_GET['slug'] ?? '');
$ex = $previewId > 0 ? $this->getExhibitionById($previewId) : $this->getExhibitionBySlug($slug, true);

if (!$ex) {
    http_response_code(404);
    $pageTitle = 'Exhibition not found - ' . SITE_TITLE;
    require_once ThemeManager::getHeader();
    echo '<main class="max-w-3xl mx-auto px-6 py-24 text-center"><h1 class="text-4xl font-bold mb-4">Exhibition not found</h1><p class="text-gray-500 mb-8">This exhibition may be unpublished or the link is incorrect.</p><a href="' . SITE_URL . '/museum" class="inline-flex min-h-11 items-center bg-slate-900 text-white px-6 py-3 rounded-lg">Back to the museum</a></main>';
    require_once ThemeManager::getFooter();
    return;
}

$theme = in_array($ex['theme'] ?? '', VirtualMuseumModule::THEMES, true) ? $ex['theme'] : 'classic';
$layout = in_array($ex['layout'] ?? '', VirtualMuseumModule::LAYOUTS, true) ? $ex['layout'] : 'grand_hall';
$centerpiece = in_array($ex['centerpiece'] ?? '', VirtualMuseumModule::CENTERPIECES, true) ? $ex['centerpiece'] : 'auto';
$items = $this->resolveItems($ex);
$is2d = ($_GET['view'] ?? '') === '2d';
$baseRoomUrl = $previewId > 0 ? SITE_URL . '/museum/preview/' . $previewId : SITE_URL . '/museum/' . rawurlencode($ex['slug']);

if ($is2d) {
    $pageTitle = $ex['title'] . ' - Virtual Museum - ' . SITE_TITLE;
    require_once ThemeManager::getHeader();
    ?>
    <style>
    .museum-2d{--accent:#d8ad70;background:radial-gradient(circle at 80% 0,#26384a 0,#0e1720 35%,#080d13 78%);color:#edf3f7;min-height:75vh}
    .museum-2d-card{background:linear-gradient(145deg,rgba(28,42,57,.92),rgba(12,20,29,.96));border:1px solid rgba(160,184,205,.2)}
    .museum-2d a:focus-visible{outline:3px solid rgba(240,200,135,.7);outline-offset:3px}
    @media(prefers-reduced-motion:reduce){.museum-2d *{scroll-behavior:auto!important;transition:none!important}}
    </style>
    <main class="museum-2d -mt-px">
        <header class="max-w-7xl mx-auto px-5 sm:px-7 lg:px-8 pt-14 pb-10 border-b border-white/10">
            <nav class="flex flex-wrap gap-3 mb-8" aria-label="Exhibition controls">
                <a href="<?= SITE_URL ?>/museum" class="inline-flex min-h-11 items-center px-4 py-2 rounded-xl bg-white/10 border border-white/10 font-bold text-sm">← Museum lobby</a>
                <a href="<?= htmlspecialchars($baseRoomUrl) ?>" class="inline-flex min-h-11 items-center px-4 py-2 rounded-xl bg-[#d8ad70] text-[#111820] font-bold text-sm">Enter 3D view</a>
            </nav>
            <p class="text-xs uppercase tracking-[.2em] text-[#d8ad70] font-bold">Accessible exhibition view · <?= count($items) ?> objects</p>
            <h1 class="text-4xl md:text-6xl font-bold tracking-[-.035em] mt-4"><?= htmlspecialchars($ex['title']) ?></h1>
            <?php if (!empty($ex['description'])): ?><p class="text-[#a4b3c0] max-w-3xl text-lg leading-relaxed mt-5"><?= htmlspecialchars(strip_tags($ex['description'])) ?></p><?php endif; ?>
        </header>
        <section class="max-w-7xl mx-auto px-5 sm:px-7 lg:px-8 py-12" aria-labelledby="museum-2d-grid-title">
            <h2 id="museum-2d-grid-title" class="sr-only">Objects in this exhibition</h2>
            <?php if (!$items): ?><div class="museum-2d-card rounded-2xl p-12 text-center"><h2 class="text-xl font-bold">No displayable objects yet</h2><p class="text-[#9dafbd] mt-2">This exhibition needs visible items with images.</p></div><?php else: ?>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                <?php foreach ($items as $index=>$item): ?>
                <article class="museum-2d-card rounded-2xl overflow-hidden flex flex-col">
                    <img src="<?= htmlspecialchars($item['thumb']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" loading="lazy" decoding="async" width="520" height="390" class="w-full aspect-[4/3] bg-black/30 object-contain">
                    <div class="p-5 flex-1 flex flex-col"><p class="text-[11px] text-[#d8ad70] font-bold uppercase tracking-[.14em]">Object <?= $index+1 ?><?= $item['reg'] ? ' · '.htmlspecialchars($item['reg']) : '' ?></p><h2 class="font-bold text-lg mt-2"><?= htmlspecialchars($item['title']) ?></h2><?php if ($item['desc']): ?><p class="text-sm text-[#9dafbd] leading-relaxed mt-3 line-clamp-4"><?= htmlspecialchars($item['desc']) ?></p><?php endif; ?><a href="<?= htmlspecialchars($item['url']) ?>" class="mt-5 text-sm font-bold text-[#f0c887]">View full record →</a></div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    </main>
    <?php
    require_once ThemeManager::getFooter();
    return;
}

$assetUrl = SITE_URL . '/modules/virtual_museum/assets';
$assetDir = __DIR__ . '/assets';
$config = [
    'slug'=>$ex['slug'], 'title'=>$ex['title'], 'theme'=>$theme, 'layout'=>$layout, 'centerpiece'=>$centerpiece,
    'dataUrl'=>SITE_URL . '/ajax.php?action=museum_data&slug=' . rawurlencode($ex['slug']),
    'items'=>$previewId > 0 ? $items : null,
    'lobbyUrl'=>SITE_URL . '/museum', 'view2dUrl'=>$baseRoomUrl . '?view=2d', 'assetUrl'=>$assetUrl,
];
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($ex['title']) ?> — 3D Exhibition</title>
    <link rel="stylesheet" href="<?= $assetUrl ?>/museum.css?v=<?= @filemtime($assetDir.'/museum.css') ?: 1 ?>">
    <script type="importmap">{"imports":{"three":"<?= $assetUrl ?>/lib/three.module.js?v=<?= @filemtime($assetDir.'/lib/three.module.js') ?: 1 ?>"}}</script>
</head>
<body class="museum-body" data-theme="<?= htmlspecialchars($theme) ?>" data-layout="<?= htmlspecialchars($layout) ?>">
    <a class="museum-skip" href="#museum-controls">Skip to controls</a>
    <canvas id="museum-canvas" aria-label="Interactive 3D exhibition"></canvas>

    <div id="museum-loader" class="museum-loader" role="status" aria-live="polite">
        <div class="loader-inner"><svg class="loader-mark" viewBox="0 0 64 64" fill="none" stroke="currentColor"><path d="M8 54h48M12 54V24L32 12l20 12v30M22 54V39h20v15"/></svg><div class="loader-title"><?= htmlspecialchars($ex['title']) ?></div><div class="loader-bar"><div id="loader-bar-fill" class="loader-bar-fill"></div></div><div id="loader-status" class="loader-status">Preparing the gallery…</div></div>
    </div>

    <section id="museum-welcome" class="museum-welcome hidden" role="dialog" aria-modal="true" aria-labelledby="welcome-title">
        <div class="welcome-card"><p class="eyebrow">Virtual exhibition</p><h1 id="welcome-title"><?= htmlspecialchars($ex['title']) ?></h1><p>Walk with W A S D or the arrow keys. Look around with the mouse or touch. Select an artwork to inspect it.</p><div class="welcome-actions"><button id="museum-start" class="museum-primary">Enter exhibition</button><a href="<?= htmlspecialchars($config['view2dUrl']) ?>" class="museum-secondary">Browse in 2D</a></div></div>
    </section>

    <div id="museum-hud" class="museum-hud hidden">
        <div class="hud-top" id="museum-controls">
            <a href="<?= $config['lobbyUrl'] ?>" class="hud-btn" title="Leave exhibition">← Exit</a>
            <div class="hud-title"><span><?= htmlspecialchars($ex['title']) ?></span><small id="museum-progress">Room 1 · <?= count($items) ?> objects</small></div>
            <div class="hud-actions"><button id="museum-reset" class="hud-btn" type="button" aria-label="Reset position"><span class="btn-ico" aria-hidden="true">⟲</span><span class="btn-text">Reset position</span></button><a class="hud-btn" href="<?= htmlspecialchars($config['view2dUrl']) ?>">2D view</a></div>
        </div>
        <div id="hud-hint" class="hud-hint"><span class="hint-desktop">Click to look · <b>W A S D</b> to walk · hold <b>Shift</b> to run · focus an artwork and press <b>Enter</b></span><span class="hint-touch">Drag to look · use the movement pad · tap artwork to inspect</span></div>
        <div id="museum-crosshair" class="museum-crosshair"></div>
        <div id="touch-pad" class="touch-pad" aria-label="Movement controls"><button class="pad-btn pad-up" data-dir="forward" aria-label="Move forward">▲</button><button class="pad-btn pad-left" data-dir="left" aria-label="Move left">◀</button><button class="pad-btn pad-right" data-dir="right" aria-label="Move right">▶</button><button class="pad-btn pad-down" data-dir="back" aria-label="Move backward">▼</button></div>
    </div>

    <aside id="museum-panel" class="museum-panel hidden" aria-labelledby="panel-title" aria-hidden="true"><div class="panel-grip" aria-hidden="true"></div><button id="panel-close" class="panel-close" aria-label="Close object details">×</button><div class="panel-media"><img id="panel-img" src="" alt=""></div><div class="panel-body"><h2 id="panel-title"></h2><div id="panel-meta" class="panel-meta"></div><p id="panel-desc" class="panel-desc"></p><a id="panel-link" class="panel-link" href="#">View full record →</a></div></aside>

    <div id="museum-empty" class="museum-empty hidden"><div><svg class="empty-mark" viewBox="0 0 64 64" fill="none" stroke="currentColor"><path d="M8 54h48M12 54V24L32 12l20 12v30M20 32h24"/></svg><h2>This exhibition has no displayable objects</h2><p>Add visible items with images, then publish again.</p><a href="<?= $config['lobbyUrl'] ?>" class="hud-btn">Back to the museum</a></div></div>

    <div id="webgl-fallback" class="museum-empty hidden"><div><h2>3D view is unavailable</h2><p>Your browser or device could not start the gallery renderer.</p><div class="fallback-actions"><button id="museum-retry" class="hud-btn" type="button">Retry 3D</button><a href="<?= htmlspecialchars($config['view2dUrl']) ?>" class="hud-btn">Browse in 2D</a><a href="<?= $config['lobbyUrl'] ?>" class="hud-btn">Museum lobby</a></div></div></div>

    <script>window.MUSEUM_CONFIG=<?= json_encode($config, JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
    <script type="module" src="<?= $assetUrl ?>/museum.js?v=<?= @filemtime($assetDir.'/museum.js') ?: 1 ?>"></script>
</body></html>
