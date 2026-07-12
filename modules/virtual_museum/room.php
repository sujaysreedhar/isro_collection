<?php
// modules/virtual_museum/room.php — full-viewport 3D exhibition room.
// Reached via route_request: /museum/{slug}. Intentionally does NOT use the
// theme header/footer because the WebGL canvas needs the entire viewport.

global $pdo, $storage;

if (!isset($pdo)) {
    require_once __DIR__ . '/../../config/config.php';
    global $pdo, $storage;
}

$slug = trim($_GET['slug'] ?? '');
$ex = null;
if ($slug !== '') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM museum_exhibitions WHERE slug = ? AND is_published = 1");
        $stmt->execute([$slug]);
        $ex = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (\PDOException $e) {
        $ex = null;
    }
}

if (!$ex) {
    http_response_code(404);
    if (class_exists('ThemeManager')) {
        require_once ThemeManager::getHeader();
        echo '<div class="max-w-3xl mx-auto px-6 py-24 text-center">'
            . '<h1 class="text-4xl font-bold mb-4">Exhibition not found</h1>'
            . '<p class="text-gray-500 mb-8">This exhibition may be unpublished or the link is incorrect.</p>'
            . '<a href="' . SITE_URL . '/museum" class="inline-block bg-slate-900 text-white px-6 py-3 rounded-lg">Back to the museum</a>'
            . '</div>';
        require_once ThemeManager::getFooter();
    } else {
        echo 'Exhibition not found.';
    }
    return;
}

$validThemes = ['classic', 'dark', 'white_cube'];
$theme = in_array($ex['theme'], $validThemes, true) ? $ex['theme'] : 'classic';

$config = [
    'slug'     => $ex['slug'],
    'title'    => $ex['title'],
    'theme'    => $theme,
    'dataUrl'  => SITE_URL . '/ajax.php?action=museum_data&slug=' . rawurlencode($ex['slug']),
    'lobbyUrl' => SITE_URL . '/museum',
    'assetUrl' => SITE_URL . '/modules/virtual_museum/assets',
];

$assetDir = __DIR__ . '/assets';
$cssVer   = @filemtime($assetDir . '/museum.css') ?: 1;
$jsVer    = @filemtime($assetDir . '/museum.js') ?: 1;
$libVer   = @filemtime($assetDir . '/lib/three.module.js') ?: 1;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($ex['title']) ?> — 3D Exhibition</title>
    <link rel="stylesheet" href="<?= $config['assetUrl'] ?>/museum.css?v=<?= $cssVer ?>">
    <script type="importmap">
    {
        "imports": {
            "three": "<?= $config['assetUrl'] ?>/lib/three.module.js?v=<?= $libVer ?>"
        }
    }
    </script>
</head>
<body class="museum-body" data-theme="<?= htmlspecialchars($theme) ?>">

    <canvas id="museum-canvas"></canvas>

    <!-- Loading screen -->
    <div id="museum-loader" class="museum-loader">
        <div class="loader-inner">
            <div class="loader-icon">🏛️</div>
            <div class="loader-title"><?= htmlspecialchars($ex['title']) ?></div>
            <div class="loader-bar"><div id="loader-bar-fill" class="loader-bar-fill"></div></div>
            <div id="loader-status" class="loader-status">Preparing the gallery…</div>
        </div>
    </div>

    <!-- Heads-up overlay -->
    <div id="museum-hud" class="museum-hud hidden">
        <div class="hud-top">
            <a href="<?= $config['lobbyUrl'] ?>" class="hud-btn" title="Leave exhibition">
                &larr; Exit
            </a>
            <div class="hud-title"><?= htmlspecialchars($ex['title']) ?></div>
        </div>
        <div id="hud-hint" class="hud-hint">
            <span class="hint-desktop">Click to look around · <b>W A S D</b> to walk · click a piece to inspect · <b>Esc</b> to release</span>
            <span class="hint-touch">Drag to look · use the pad to walk · tap a piece to inspect</span>
        </div>
        <div id="museum-crosshair" class="museum-crosshair"></div>

        <!-- Touch movement pad -->
        <div id="touch-pad" class="touch-pad">
            <button class="pad-btn pad-up"    data-dir="forward">▲</button>
            <button class="pad-btn pad-left"  data-dir="left">◀</button>
            <button class="pad-btn pad-right" data-dir="right">▶</button>
            <button class="pad-btn pad-down"  data-dir="back">▼</button>
        </div>
    </div>

    <!-- Item info panel -->
    <div id="museum-panel" class="museum-panel hidden">
        <button id="panel-close" class="panel-close" aria-label="Close">&times;</button>
        <div class="panel-media"><img id="panel-img" src="" alt=""></div>
        <div class="panel-body">
            <h2 id="panel-title"></h2>
            <div id="panel-meta" class="panel-meta"></div>
            <p id="panel-desc" class="panel-desc"></p>
            <a id="panel-link" class="panel-link" href="#">View full details &rarr;</a>
        </div>
    </div>

    <!-- Empty state -->
    <div id="museum-empty" class="museum-empty hidden">
        <div>
            <div class="empty-icon">🖼️</div>
            <h2>This exhibition has no items yet</h2>
            <p>Add visible items with images to its source, then republish.</p>
            <a href="<?= $config['lobbyUrl'] ?>" class="hud-btn">&larr; Back to the museum</a>
        </div>
    </div>

    <script>window.MUSEUM_CONFIG = <?= json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script type="module" src="<?= $config['assetUrl'] ?>/museum.js?v=<?= $jsVer ?>"></script>
</body>
</html>
