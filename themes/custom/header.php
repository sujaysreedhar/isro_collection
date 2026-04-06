<?php
// themes/custom/header.php
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

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

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
</head>

<body>
    <!-- Global Header -->
    <header class="tc-header sticky top-0 z-50">
        <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_header'); } ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center flex-shrink-0">
                <a href="<?= SITE_URL ?>" class="flex items-center gap-3">
                    <?php
                    $siteLogo = $GLOBALS['appSettings']['site_logo'] ?? '';
                    if ($siteLogo && file_exists(dirname(__DIR__, 2) . '/uploads/branding/' . $siteLogo)):
                    ?>
                        <img src="<?= SITE_URL ?>/uploads/branding/<?= rawurlencode($siteLogo) ?>" alt="<?= SITE_TITLE ?>" class="h-10 w-auto object-contain">
                    <?php else: ?>
                        <span class="text-2xl font-bold serif tracking-tight tc-primary-text"><?= SITE_TITLE ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <?php if (!$hideHeaderSearch): ?>
                <div class="flex-1 max-w-2xl ml-8 hidden md:block">
                    <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <?php foreach ($searchParams['category_ids'] ?? [] as $cid): ?>
                            <input type="hidden" name="category_ids[]" value="<?= htmlspecialchars($cid) ?>">
                        <?php endforeach; ?>
                        <?php if (!empty($searchParams['has_images'])): ?>
                            <input type="hidden" name="has_images" value="1">
                        <?php endif; ?>
                        <?php if (!empty($searchParams['tag'])): ?>
                            <input type="hidden" name="tag" value="<?= htmlspecialchars($searchParams['tag']) ?>">
                        <?php endif; ?>
                        <input type="text" name="q" data-autocomplete="true" autocomplete="off"
                               value="<?= htmlspecialchars($searchParams['q'] ?? '') ?>"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-gray-50 placeholder-gray-500 focus:outline-none focus:ring-2 focus:border-transparent sm:text-sm tc-input"
                               placeholder="Search the collections...">
                    </form>
                </div>
            <?php endif; ?>

            <div class="flex items-center gap-4">
                <?php if (function_exists('renderFrontendNav')) {
                    renderFrontendNav($currentMenu);
                } ?>

                <!-- Mobile Menu Button -->
                <button type="button" onclick="toggleMobileMenu()" class="lg:hidden inline-flex items-center justify-center p-2 rounded-xl tc-text-muted hover:tc-primary-text hover:bg-black/5 transition-all outline-none">
                    <span class="sr-only">Open main menu</span>
                    <svg id="menu-icon-open" class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg id="menu-icon-close" class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Container -->
        <div id="mobile-menu" class="hidden lg:hidden tc-header border-b tc-border shadow-2xl">
            <div class="px-4 py-8">
                <?php if (!$hideHeaderSearch): ?>
                    <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative mb-8">
                        <input type="text" name="q" class="block w-full pl-4 pr-3 py-3 border tc-border rounded-xl bg-black/5 tc-text text-sm transition-all focus:ring-2 focus:ring-black/10" placeholder="Search...">
                    </form>
                <?php endif; ?>
                <?php renderFrontendNav($currentMenu, true); ?>
            </div>
        </div>

        <script>
            function toggleMobileMenu() {
                const menu = document.getElementById('mobile-menu');
                const openIcon = document.getElementById('menu-icon-open');
                const closeIcon = document.getElementById('menu-icon-close');
                menu.classList.toggle('hidden');
                openIcon.classList.toggle('hidden');
                closeIcon.classList.toggle('hidden');
            }
        </script>
        </div>
    </header>
