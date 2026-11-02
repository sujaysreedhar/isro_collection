<?php
// themes/custom/header.php
require_once __DIR__ . '/partials/archive_helpers.php';
// CSS-variable driven header — inherits logic from default but uses tc-* classes

$pageTitle      = $pageTitle      ?? SITE_TITLE;
$ogTitle        = $ogTitle        ?? $pageTitle;
$ogDescription  = $ogDescription  ?? ($appSettings['site_description'] ?? 'Pictorial Cancellation Collection Archive');
$ogUrl          = $ogUrl          ?? SITE_URL;
$ogImage        = $ogImage        ?? '';
$jsonLdJson     = $jsonLdJson     ?? '';
$currentMenu    = $currentMenu    ?? '';
$hideHeaderSearch = $hideHeaderSearch ?? false;
$searchParams   = $searchParams   ?? ['q' => ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <?php
    $siteFavicon = $appSettings['site_favicon'] ?? '';
    if ($siteFavicon && file_exists(dirname(__DIR__, 2) . '/uploads/branding/' . $siteFavicon)):
    ?>
        <link rel="icon" href="<?= SITE_URL ?>/uploads/branding/<?= rawurlencode($siteFavicon) ?>"
              type="image/<?= pathinfo($siteFavicon, PATHINFO_EXTENSION) === 'svg' ? 'svg+xml' : pathinfo($siteFavicon, PATHINFO_EXTENSION) ?>">
    <?php endif; ?>

    <meta name="description" content="<?= htmlspecialchars($ogDescription) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($ogUrl) ?>">

    <meta property="og:type"        content="website">
    <meta property="og:url"         content="<?= htmlspecialchars($ogUrl) ?>">
    <meta property="og:title"       content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDescription) ?>">
    <?php if ($ogImage): ?><meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>"><?php endif; ?>
    <meta property="og:site_name"   content="<?= SITE_TITLE ?>">

    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?= htmlspecialchars($ogTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDescription) ?>">
    <?php if ($ogImage): ?><meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>"><?php endif; ?>

    <?php if ($jsonLdJson): ?>
        <script type="application/ld+json"><?= $jsonLdJson ?></script>
    <?php endif; ?>

    <!-- Tailwind CSS (pre-built shared) -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/themes/common/dist/tailwind.css">

    <!-- Custom theme stylesheet (CSS vars injected by Theme Studio via frontend_head) -->
    <?php echo AssetManager::renderStyles(['themes/custom/style.css']); ?>

    <!-- Autocomplete JS (same as default) -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[data-autocomplete="true"]').forEach(input => {
            let timeout = null, currentFocus = -1;
            const container = input.parentElement;
            container.classList.add('relative');
            const dropdown = document.createElement('div');
            dropdown.className = 'absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-2xl hidden max-h-96 overflow-y-auto left-0 top-full';
            container.appendChild(dropdown);
            input.addEventListener('input', function () {
                clearTimeout(timeout);
                const val = this.value.trim();
                if (!val || val.length < 2) { dropdown.classList.add('hidden'); return; }
                timeout = setTimeout(() => {
                    fetch('<?= SITE_URL ?>/ajax_search.php?q=' + encodeURIComponent(val))
                        .then(r => r.json()).then(data => {
                            dropdown.innerHTML = '';
                            currentFocus = -1;
                            if (!data.length) {
                                dropdown.innerHTML = '<div class="p-4 text-sm text-gray-500 text-center">No matching items found.</div>';
                            } else {
                                data.forEach(item => {
                                    const a = document.createElement('a');
                                    a.href = item.url;
                                    a.className = 'flex items-center gap-3 p-3 hover:bg-gray-50 border-b border-gray-100 transition-colors last:border-0 autocomp-item';
                                    const imgHtml = item.image_url
                                        ? `<img src="${item.image_url}" class="w-12 h-12 object-cover rounded bg-gray-100 flex-shrink-0">`
                                        : `<div class="w-12 h-12 rounded bg-gray-100 flex items-center justify-center flex-shrink-0 text-gray-400"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>`;
                                    a.innerHTML = `${imgHtml}<div class="min-w-0 flex-1"><div class="text-xs font-bold text-gray-500 mb-0.5">${item.reg_number}</div><div class="text-sm font-semibold text-gray-900 truncate">${item.title}</div></div>`;
                                    dropdown.appendChild(a);
                                });
                            }
                            dropdown.classList.remove('hidden');
                        }).catch(e => console.error(e));
                }, 300);
            });
            document.addEventListener('click', e => { if (e.target !== input && !dropdown.contains(e.target)) dropdown.classList.add('hidden'); });
            input.addEventListener('keydown', function (e) {
                const items = dropdown.querySelectorAll('.autocomp-item');
                if (!items.length || dropdown.classList.contains('hidden')) return;
                if (e.key === 'ArrowDown')      { e.preventDefault(); currentFocus = (currentFocus + 1) % items.length; }
                else if (e.key === 'ArrowUp')   { e.preventDefault(); currentFocus = (currentFocus - 1 + items.length) % items.length; }
                else if (e.key === 'Enter' && currentFocus > -1) { e.preventDefault(); items[currentFocus].click(); }
                items.forEach((it, i) => it.classList.toggle('bg-gray-100', i === currentFocus));
                if (currentFocus > -1) items[currentFocus].scrollIntoView({ block: 'nearest' });
            });
        });
    });
    </script>

    <!-- User injected head -->
    <?php if (isset($additionalHead)) echo $additionalHead; ?>

    <!-- Module hooks (Theme Studio injects :root CSS vars here) -->
    <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_head'); } ?>
    <link rel="stylesheet" href="<?= SITE_URL ?>/themes/custom/archive.css?v=<?= filemtime(__DIR__ . '/archive.css') ?>">
    <script src="<?= SITE_URL ?>/themes/custom/archive.js?v=<?= filemtime(__DIR__ . '/archive.js') ?>" defer></script>
</head>

<body class="<?= htmlspecialchars($bodyClass ?? '') ?>" data-archive-base="<?= htmlspecialchars(SITE_URL) ?>">
    <a class="archive-skip-link" href="#archive-main">Skip to content</a>
    <header class="tc-header archive-header">
        <?php if (class_exists('HookRegistry')) HookRegistry::doAction('frontend_header'); ?>
        <div class="archive-header-inner">
            <a href="<?= SITE_URL ?>" class="archive-brand">
                <?php
                $siteLogo = $appSettings['site_logo'] ?? '';
                if ($siteLogo && is_file(dirname(__DIR__, 2) . '/uploads/branding/' . $siteLogo)):
                ?>
                    <img src="<?= SITE_URL ?>/uploads/branding/<?= rawurlencode($siteLogo) ?>" alt="<?= htmlspecialchars(SITE_TITLE) ?>" width="160" height="56">
                    <span class="archive-brand-title"><?= htmlspecialchars(SITE_TITLE) ?></span>
                <?php else: ?>
                    <span class="archive-brand-name"><?= htmlspecialchars(SITE_TITLE) ?></span>
                <?php endif; ?>
            </a>
            <div class="archive-desktop-nav">
                <?php if (function_exists('renderFrontendNav')) renderFrontendNav($currentMenu); ?>
            </div>
            <?php if (!$hideHeaderSearch): ?>
                <form action="<?= SITE_URL ?>/search.php" method="GET" class="archive-header-search" role="search">
                    <?= archiveUiIcon('magnifying-glass') ?>
                    <?php archiveUiSearchFields($searchParams, ['q']); ?>
                    <input aria-label="Search the collections" type="search" name="q" data-autocomplete="true" autocomplete="off" value="<?= htmlspecialchars($searchParams['q'] ?? '') ?>" placeholder="Search the collections...">
                </form>
            <?php endif; ?>
            <a class="archive-account" href="<?= SITE_URL ?>/admin/index.php" aria-label="Collection administration"><?= archiveUiIcon('user') ?></a>
            <button type="button" id="archive-menu-toggle" class="archive-menu-toggle" onclick="toggleMobileMenu()" aria-expanded="false" aria-controls="mobile-menu" aria-label="Open main menu">
                <span id="menu-icon-open"><?= archiveUiIcon('bars-3') ?></span>
                <span id="menu-icon-close" hidden><?= archiveUiIcon('x-mark') ?></span>
            </button>
        </div>
        <div id="mobile-menu" class="archive-mobile-menu" hidden>
            <?php if (!$hideHeaderSearch): ?>
            <form action="<?= SITE_URL ?>/search.php" method="GET" class="archive-mobile-search" role="search">
                <?php archiveUiSearchFields($searchParams, ['q']); ?>
                <?= archiveUiIcon('magnifying-glass') ?>
                <input aria-label="Search the collections" type="search" name="q" placeholder="Search the collections..." value="<?= htmlspecialchars($searchParams['q'] ?? '') ?>">
                <button type="submit">Search</button>
            </form>
            <?php endif; ?>
            <?php if (function_exists('renderFrontendNav')) renderFrontendNav($currentMenu, true); ?>
        </div>
    </header>
    <span id="archive-main" tabindex="-1"></span>
    <script>
        function toggleMobileMenu(forceOpen) {
            const menu = document.getElementById('mobile-menu');
            const toggle = document.getElementById('archive-menu-toggle');
            const open = typeof forceOpen === 'boolean' ? forceOpen : menu.hidden;
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Close main menu' : 'Open main menu');
            document.getElementById('menu-icon-open').hidden = open;
            document.getElementById('menu-icon-close').hidden = !open;
        }
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !document.getElementById('mobile-menu').hidden) {
                toggleMobileMenu(false);
                document.getElementById('archive-menu-toggle').focus();
            }
        });
    </script>
