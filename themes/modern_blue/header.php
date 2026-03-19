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
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        modern: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a',
                            950: '#172554',
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
                <div class="w-10 h-10 bg-gradient-to-br from-modern-500 to-modern-700 rounded-xl shadow-lg shadow-modern-500/30 flex items-center justify-center text-white font-bold text-xl">
                    <?= substr(SITE_TITLE, 0, 1) ?>
                </div>
                <a href="<?= SITE_URL ?>" class="text-2xl font-extrabold tracking-tight text-slate-900"><?= SITE_TITLE ?></a>
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
            
            <div class="flex items-center gap-6">
                <?php renderFrontendNav($currentMenu ?? ''); ?>
            </div>
        </div>
    </header>
