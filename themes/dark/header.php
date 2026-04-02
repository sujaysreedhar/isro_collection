<?php
// themes/dark/header.php
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
        <link rel="icon" href="<?= SITE_URL ?>/uploads/branding/<?= rawurlencode($siteFavicon) ?>"
            type="image/<?= pathinfo($siteFavicon, PATHINFO_EXTENSION) === 'svg' ? 'svg+xml' : pathinfo($siteFavicon, PATHINFO_EXTENSION) ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        dark: {
                            50: '#f9fafb',
                            100: '#f3f4f6',
                            200: '#e5e7eb',
                            300: '#d1d5db',
                            400: '#9ca3af',
                            500: '#6b7280',
                            600: '#4b5563',
                            700: '#374151',
                            800: '#1f2937',
                            900: '#111827',
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

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #111827;
            color: #f9fafb;
        }

        .glass-header {
            background: rgba(31, 41, 55, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(55, 65, 81, 0.8);
        }
    </style>
    <?= $additionalHead ?? '' ?>
    <?php if (class_exists('HookRegistry')) {
        HookRegistry::doAction('frontend_head');
    } ?>
</head>

<body class="antialiased flex flex-col min-h-screen">
    <header class="glass-header sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <?php
                $siteLogo = $GLOBALS['appSettings']['site_logo'] ?? '';//$appSettings['site_logo'] ?? '';
                if ($siteLogo && file_exists(dirname(__DIR__, 2) . '/uploads/branding/' . $siteLogo)):
                    ?>
                    <a href="<?= SITE_URL ?>" class="flex items-center">
                        <img src="<?= SITE_URL ?>/uploads/branding/<?= rawurlencode($siteLogo) ?>" alt="<?= SITE_TITLE ?>"
                            class="h-12 w-auto object-contain">
                    </a>
                <?php else: ?>
                    <div
                        class="w-10 h-10 bg-gradient-to-br from-dark-700 to-dark-900 rounded-xl shadow-lg flex items-center justify-center text-white font-bold text-xl">
                        <?= substr(SITE_TITLE, 0, 1) ?>
                    </div>
                    <a href="<?= SITE_URL ?>"
                        class="text-2xl font-extrabold tracking-tight text-white"><?= SITE_TITLE ?></a>
                <?php endif; ?>
            </div>
            <div class="flex-1 max-w-xl mx-8 hidden md:block">
                <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-slate-400 group-focus-within:text-dark-500 transition-colors"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" name="q"
                        class="block w-full pl-10 pr-3 py-2.5 border border-dark-700 rounded-full bg-dark-800 placeholder-dark-400 focus:outline-none focus:ring-2 focus:ring-dark-500/20 focus:border-dark-500 focus:bg-dark-700 text-sm transition-all duration-300 shadow-sm"
                        placeholder="Search archives, documents, artifacts..." />
                </form>
            </div>
            <div class="flex items-center gap-6">
                <?php renderFrontendNav($currentMenu ?? ''); ?>
            </div>
        </div>
    </header>