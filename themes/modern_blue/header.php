<?php
// themes/modern_blue/header.php
global $pageTitle, $additionalHead, $currentMenu;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? SITE_TITLE) ?></title>
    <?php
        $siteFavicon = $appSettings['site_favicon'] ?? '';
        if ($siteFavicon && file_exists(__DIR__ . '/../uploads/branding/' . $siteFavicon)):
    ?>
        <link rel="icon" href="<?= SITE_URL ?>/uploads/branding/<?= rawurlencode($siteFavicon) ?>" type="image/<?= pathinfo($siteFavicon, PATHINFO_EXTENSION) === 'svg' ? 'svg+xml' : pathinfo($siteFavicon, PATHINFO_EXTENSION) ?>">
    <?php endif; ?>
    <!-- Tailwind CSS (pre-built shared) -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/themes/common/dist/tailwind.css">
    <style>

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .glass-header {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        }
    </style>
    <?= $additionalHead ?? '' ?>
    <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_head'); } ?>
</head>
<body class="text-slate-800 antialiased flex flex-col min-h-screen">

    <header class="glass-header sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <?php
 $siteLogo = $GLOBALS['appSettings']['site_logo'] ?? '';//$appSettings['site_logo'] ?? '';
                    if ($siteLogo && file_exists(dirname(__DIR__, 2) . '/uploads/branding/' . $siteLogo)):
                ?>
                    <a href="<?= SITE_URL ?>" class="flex items-center">
                        <img src="<?= SITE_URL ?>/uploads/branding/<?= rawurlencode($siteLogo) ?>" alt="<?= SITE_TITLE ?>" class="h-12 w-auto object-contain">
                    </a>
                <?php else: ?>
                    <div class="w-10 h-10 bg-gradient-to-br from-modern-500 to-modern-700 rounded-xl shadow-lg shadow-modern-500/30 flex items-center justify-center text-white font-bold text-xl">
                        <?= substr(SITE_TITLE, 0, 1) ?>
                    </div>
                    <a href="<?= SITE_URL ?>" class="text-2xl font-extrabold tracking-tight text-slate-900"><?= SITE_TITLE ?></a>
                <?php endif; ?>
            </div>
            
            <div class="flex-1 max-w-xl mx-8 hidden md:block">
                <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-slate-400 group-focus-within:text-modern-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" name="q" class="block w-full pl-10 pr-3 py-2.5 border border-slate-200 rounded-full bg-slate-50 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-modern-500/20 focus:border-modern-500 focus:bg-white text-sm transition-all duration-300 shadow-sm" placeholder="Search archives, documents, artifacts...">
                </form>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="hidden lg:flex items-center gap-6">
                    <?php renderFrontendNav($currentMenu ?? ''); ?>
                </div>

                <!-- Mobile Menu Button -->
                <button type="button" onclick="toggleMobileMenu()" class="lg:hidden inline-flex items-center justify-center p-2 rounded-xl text-slate-400 hover:text-modern-600 hover:bg-modern-50 transition-all border border-transparent hover:border-modern-200">
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
        <div id="mobile-menu" class="hidden lg:hidden border-b border-slate-200 bg-white/95 backdrop-blur-2xl">
            <div class="px-4 py-8 text-slate-800">
                <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative group mb-8">
                    <input type="text" name="q" class="block w-full pl-4 pr-3 py-3 border border-slate-200 rounded-2xl bg-slate-50 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-modern-500/20 text-sm transition-all" placeholder="Search...">
                </form>
                <?php renderFrontendNav($currentMenu ?? '', true); ?>
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
