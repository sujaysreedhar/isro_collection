<?php
// themes/glass/header.php
global $pageTitle, $additionalHead, $currentMenu;
?>
<!DOCTYPE html>
<html lang="en" class="dark">
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
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        glass: {
                            50: '#f5f3ff',
                            100: '#ede9fe',
                            200: '#ddd6fe',
                            300: '#c4b5fd',
                            400: '#a78bfa',
                            500: '#8b5cf6',
                            600: '#7c3aed',
                            700: '#6d28d9',
                            800: '#5b21b6',
                            900: '#4c1d95',
                            950: '#2e1065',
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-header {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
    <?= $additionalHead ?? '' ?>
    <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_head'); } ?>
</head>
<body class="text-slate-200 antialiased flex flex-col min-h-screen bg-slate-950 relative overflow-x-hidden">
    
    <!-- Global Glass Theme Background -->
    <div class="fixed inset-0 z-[-1] bg-slate-950 pointer-events-none">
        <div class="absolute top-[-20%] left-[-10%] w-[50vw] h-[50vw] bg-fuchsia-600/20 rounded-full mix-blend-screen filter blur-[120px] opacity-60 animate-pulse"></div>
        <div class="absolute bottom-[-20%] right-[-10%] w-[60vw] h-[60vw] bg-glass-600/20 rounded-full mix-blend-screen filter blur-[140px] opacity-60" style="animation: pulse 8s cubic-bezier(0.4, 0, 0.6, 1) infinite;"></div>
        <div class="absolute top-[30%] right-[30%] w-[30vw] h-[30vw] bg-cyan-600/20 rounded-full mix-blend-screen filter blur-[100px] opacity-40"></div>
    </div>

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
                    <div class="w-10 h-10 bg-white/10 border border-white/20 backdrop-blur-md rounded-xl shadow-[0_4px_16px_rgba(0,0,0,0.2)] flex items-center justify-center text-white font-bold text-xl">
                        <?= substr(SITE_TITLE, 0, 1) ?>
                    </div>
                    <a href="<?= SITE_URL ?>" class="text-2xl font-extrabold tracking-tight text-white"><?= SITE_TITLE ?></a>
                <?php endif; ?>
            </div>
            
            <div class="flex-1 max-w-xl mx-8 hidden md:block">
                <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-slate-400 group-focus-within:text-glass-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" name="q" class="block w-full pl-10 pr-3 py-2.5 border border-white/10 rounded-full bg-white/5 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-glass-500/50 focus:border-glass-400 focus:bg-white/10 text-white text-sm transition-all duration-300 shadow-sm backdrop-blur-sm" placeholder="Search archives, documents, artifacts...">
                </form>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="hidden lg:flex items-center gap-6">
                    <?php renderFrontendNav($currentMenu ?? ''); ?>
                </div>

                <!-- Mobile Menu Button -->
                <button type="button" onclick="toggleMobileMenu()" class="lg:hidden inline-flex items-center justify-center p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 transition-all border border-transparent hover:border-white/10">
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
        <div id="mobile-menu" class="hidden lg:hidden border-b border-white/10 bg-slate-900/90 backdrop-blur-2xl">
            <div class="px-4 py-8">
                <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative group mb-8">
                    <input type="text" name="q" class="block w-full pl-4 pr-3 py-3 border border-white/10 rounded-2xl bg-white/5 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-glass-500/50 text-white text-sm transition-all" placeholder="Search...">
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
