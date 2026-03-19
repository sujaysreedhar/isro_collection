<?php
// admin/layout.php
// Premium admin layout with sidebar, topbar, and mobile drawer

function renderAdminHeader($title) {
    ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: {
                        sidebar: { DEFAULT: '#0f172a', hover: '#1e293b', border: '#1e293b', muted: '#64748b', accent: '#3b82f6' }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link { @apply flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150; }
        .sidebar-link:hover { background: rgba(255,255,255,0.06); }
        .sidebar-link.active { background: rgba(59,130,246,0.12); color: #60a5fa; }
        .sidebar-section { @apply px-3 pt-5 pb-1.5 text-[11px] font-bold uppercase tracking-[0.1em] text-slate-500; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
    </style>
    <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('admin_head'); } ?>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
    <div class="flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-[260px] bg-sidebar flex-shrink-0 hidden lg:flex flex-col border-r border-sidebar-border">
        <div class="h-16 flex items-center px-5 border-b border-sidebar-border gap-3">
            <?php
                $siteLogo = $GLOBALS['appSettings']['site_logo'] ?? '';
                $brandDir = realpath(__DIR__ . '/../uploads/branding');
                if ($siteLogo && $brandDir && file_exists($brandDir . '/' . $siteLogo)):
            ?>
                <img src="<?= SITE_URL ?>/uploads/branding/<?= rawurlencode($siteLogo) ?>" alt="Logo" class="h-8 w-auto object-contain">
            <?php else: ?>
                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-blue-500/20">
                    <?= substr(SITE_TITLE, 0, 1) ?>
                </div>
                <span class="text-white font-bold text-lg tracking-tight">Admin</span>
            <?php endif; ?>
        </div>
        <nav class="flex-1 py-2 px-2 overflow-y-auto">
            <div class="sidebar-section">Overview</div>
            <a href="<?= SITE_URL ?>/admin/index.php" class="sidebar-link text-slate-300">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>
                Dashboard
            </a>

            <div class="sidebar-section">Catalog</div>
            <a href="<?= SITE_URL ?>/admin/items.php" class="sidebar-link text-slate-300">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                Items
            </a>
            <a href="<?= SITE_URL ?>/admin/categories.php" class="sidebar-link text-slate-300">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"></path></svg>
                Categories
            </a>

            <div class="sidebar-section">Content</div>
            <a href="<?= SITE_URL ?>/admin/narratives.php" class="sidebar-link text-slate-300">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                Stories & Narratives
            </a>

            <div class="sidebar-section">System</div>
            <a href="<?= SITE_URL ?>/admin/modules.php" class="sidebar-link text-slate-300">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"></path></svg>
                Modules
            </a>
            <a href="<?= SITE_URL ?>/admin/themes.php" class="sidebar-link text-slate-300">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                Themes
            </a>
            <a href="<?= SITE_URL ?>/admin/users.php" class="sidebar-link text-slate-300">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                Administrators
            </a>
            <a href="<?= SITE_URL ?>/admin/site_settings.php" class="sidebar-link text-slate-300">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Site Settings
            </a>
            <a href="<?= SITE_URL ?>/admin/settings.php" class="sidebar-link text-slate-300">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path></svg>
                Storage
            </a>

            <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('admin_menu'); } ?>
        </nav>
        <div class="p-3 border-t border-sidebar-border">
            <div class="flex items-center gap-3 px-2 py-2">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-slate-600 to-slate-700 flex items-center justify-center text-white text-xs font-bold shadow-inner">
                    <?= strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-300 truncate"><?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></p>
                    <p class="text-[11px] text-slate-500">Administrator</p>
                </div>
                <a href="<?= SITE_URL ?>/admin/logout.php" class="p-1.5 text-slate-500 hover:text-red-400 hover:bg-white/5 rounded-lg transition-colors" title="Log Out">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Mobile Drawer -->
    <div id="mobile-nav-overlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 hidden lg:hidden" onclick="closeMobileNav()"></div>
    <aside id="mobile-nav" class="fixed inset-y-0 left-0 w-[280px] max-w-[85vw] bg-sidebar z-50 transform -translate-x-full transition-transform duration-300 ease-out lg:hidden flex flex-col shadow-2xl">
        <div class="h-14 flex items-center justify-between px-4 border-b border-sidebar-border">
            <span class="text-white font-bold text-lg">Menu</span>
            <button type="button" onclick="closeMobileNav()" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <nav class="flex-1 py-2 px-2 overflow-y-auto">
            <a href="<?= SITE_URL ?>/admin/index.php" class="sidebar-link text-slate-300">Dashboard</a>
            <div class="sidebar-section">Catalog</div>
            <a href="<?= SITE_URL ?>/admin/items.php" class="sidebar-link text-slate-300">Items</a>
            <a href="<?= SITE_URL ?>/admin/categories.php" class="sidebar-link text-slate-300">Categories</a>
            <div class="sidebar-section">Content</div>
            <a href="<?= SITE_URL ?>/admin/narratives.php" class="sidebar-link text-slate-300">Stories & Narratives</a>
            <div class="sidebar-section">System</div>
            <a href="<?= SITE_URL ?>/admin/modules.php" class="sidebar-link text-slate-300">Modules</a>
            <a href="<?= SITE_URL ?>/admin/themes.php" class="sidebar-link text-slate-300">Themes</a>
            <a href="<?= SITE_URL ?>/admin/users.php" class="sidebar-link text-slate-300">Administrators</a>
            <a href="<?= SITE_URL ?>/admin/site_settings.php" class="sidebar-link text-slate-300">Site Settings</a>
            <a href="<?= SITE_URL ?>/admin/settings.php" class="sidebar-link text-slate-300">Storage</a>
            <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('admin_menu_mobile'); } ?>
        </nav>
        <div class="p-3 border-t border-sidebar-border">
            <a href="<?= SITE_URL ?>/admin/logout.php" class="sidebar-link text-red-400 hover:bg-red-500/10">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                Log Out
            </a>
        </div>
    </aside>

    <!-- Main Content wrapper -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top Bar -->
        <header class="bg-white border-b border-gray-200 h-14 flex items-center justify-between px-4 md:px-6 shrink-0 shadow-sm shadow-gray-100/50">
            <div class="flex items-center gap-3">
                <button type="button" onclick="openMobileNav()" class="lg:hidden p-2 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors" aria-label="Open menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h1 class="text-base font-semibold text-gray-800 hidden sm:block"><?= htmlspecialchars($title) ?></h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?= SITE_URL ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors border border-blue-100">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    View Site
                </a>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto bg-gray-50 p-4 md:p-8">
            <div class="max-w-7xl mx-auto">
<?php
    return ob_get_clean();
}

function renderAdminFooter() {
    ob_start();
?>
            </div>
        </main>
    </div><!-- /main wrapper -->
    </div><!-- /flex container -->

<script>
function openMobileNav() {
    document.getElementById('mobile-nav').classList.remove('-translate-x-full');
    document.getElementById('mobile-nav-overlay').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeMobileNav() {
    document.getElementById('mobile-nav').classList.add('-translate-x-full');
    document.getElementById('mobile-nav-overlay').classList.add('hidden');
    document.body.style.overflow = '';
}

// Highlight active sidebar link
document.addEventListener('DOMContentLoaded', function() {
    const currentPath = window.location.pathname;
    document.querySelectorAll('.sidebar-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.endsWith(new URL(href, window.location.origin).pathname.split('/').pop())) {
            link.classList.add('active');
        }
    });
});
</script>
<?php if (class_exists('HookRegistry')) { HookRegistry::doAction('admin_footer'); } ?>
</body>
</html>
<?php
    return ob_get_clean();
}
?>
