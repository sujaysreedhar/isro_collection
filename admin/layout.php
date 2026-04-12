<?php
// admin/layout.php
// Premium admin layout with sidebar, topbar, and mobile drawer

/**
 * Render sidebar nav links from a data structure.
 * Defined at file scope to avoid "Cannot redeclare" if renderAdminHeader() is
 * called more than once per request.
 * $withIcons: desktop shows SVG icons; mobile omits them.
 */
if (!function_exists('renderAdminSidebarNav')) {
    function renderAdminSidebarNav(array $sections, string $linkClass, string $sectionClass, bool $withIcons = true): void {
        foreach ($sections as $sectionLinks) {
            echo '<div class="' . $sectionClass . '">' . htmlspecialchars($sectionLinks['label']) . '</div>';
            foreach ($sectionLinks['links'] as $link) {
                echo '<a href="' . htmlspecialchars($link['url']) . '" class="' . $linkClass . ' active-link-target">';
                if ($withIcons && !empty($link['icon'])) {
                    echo '<svg class="w-5 h-5 flex-shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
                       . $link['icon']
                       . '</svg>';
                }
                echo $link['label'];
                echo '</a>';
            }
        }
    }
}

function renderAdminHeader($title) {
    ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Admin</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/themes/common/dist/tailwind.css">
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .sidebar-scroller::-webkit-scrollbar-thumb { background: #334155; }

        /* TomSelect Premium Tags */
        .ts-control { border-radius: 0.5rem !important; padding: 0.5rem 0.75rem !important; border-color: #E2E8F0 !important; box-shadow: none !important; transition: all 0.2s; }
        .focus .ts-control { border-color: #111827 !important; ring: 2px #111827; }
        .ts-wrapper.multi .ts-control > div {
            background: #F1F5F9 !important;
            color: #475569 !important;
            border: 1px solid #E2E8F0 !important;
            border-radius: 6px !important;
            padding: 3px 10px !important;
            margin: 3px 6px 3px 0 !important;
            font-weight: 600 !important;
            font-size: 0.75rem !important;
            display: inline-flex;
            align-items: center;
        }
        .ts-wrapper.multi .ts-control > div.active { background: #1E293B !important; color: #fff !important; }
        .ts-wrapper.multi .ts-control > div .remove { border-left: 1px solid #CBD5E1 !important; margin-left: 8px !important; padding-left: 6px !important; opacity: 0.7; }
        .ts-wrapper.multi .ts-control > div .remove:hover { opacity: 1; background: rgba(0,0,0,0.05); }
        .ts-dropdown { border-radius: 0.75rem !important; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important; border: 1px solid #E2E8F0 !important; margin-top: 8px !important; padding: 4px !important; }
        .ts-dropdown .option { border-radius: 0.5rem !important; padding: 8px 12px !important; margin: 2px 0 !important; font-size: 0.875rem !important; }
        .ts-dropdown .active { background-color: #F8FAFC !important; color: #0F172A !important; }
        .ts-dropdown .create { padding: 8px 12px !important; color: #2563EB !important; font-weight: 500 !important; }
    </style>
    <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('admin_head'); } ?>
</head>
<body class="bg-[#f4f7f9] text-gray-800 antialiased font-sans">
    <div class="flex h-screen overflow-hidden">

    <?php
    $linkClass    = "sidebar-link-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 text-slate-300 hover:bg-white/10 hover:text-white";
    $sectionClass = "px-3 pt-6 pb-2 text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500";

    // ── Admin Sidebar Data Structure ──────────────────────────────────────────
    // Sections are keyed by slug. Each has a 'label' and an array of 'links'.
    // Links have: url, label, and an optional SVG icon string.
    // Pass through admin_sidebar_links filter so modules can add to any section.
    $adminSidebarSections = [
        'overview' => [
            'label' => 'Overview',
            'links' => [
                'dashboard' => [
                    'url'   => SITE_URL . '/admin/index.php',
                    'label' => 'Dashboard',
                    'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>',
                ],
            ],
        ],
        'catalog' => [
            'label' => 'Catalog',
            'links' => [
                'items' => [
                    'url'   => SITE_URL . '/admin/items.php',
                    'label' => 'Items',
                    'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>',
                ],
                'categories' => [
                    'url'   => SITE_URL . '/admin/categories.php',
                    'label' => 'Categories',
                    'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>',
                ],
                'tags' => [
                    'url'   => SITE_URL . '/admin/tags.php',
                    'label' => 'Tags',
                    'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>',
                ],
            ],
        ],
        'content' => [
            'label' => 'Content',
            'links' => [
                'narratives' => [
                    'url'   => SITE_URL . '/admin/narratives.php',
                    'label' => 'Stories &amp; Narratives',
                    'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
                ],
            ],
        ],
        'system' => [
            'label' => 'System',
            'links' => [
                'modules' => [
                    'url'   => SITE_URL . '/admin/modules.php',
                    'label' => 'Modules',
                    'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/>',
                ],
                'themes' => [
                    'url'   => SITE_URL . '/admin/themes.php',
                    'label' => 'Themes',
                    'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>',
                ],
                'users' => [
                    'url'   => SITE_URL . '/admin/users.php',
                    'label' => 'Administrators',
                    'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>',
                ],
                'site_settings' => [
                    'url'   => SITE_URL . '/admin/site_settings.php',
                    'label' => 'Site Settings',
                    'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
                ],
                'api_keys' => [
                    'url'   => SITE_URL . '/admin/api_keys.php',
                    'label' => 'API Management',
                    'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>',
                ],
                'storage' => [
                    'url'   => SITE_URL . '/admin/settings.php',
                    'label' => 'Storage',
                    'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>',
                ],
            ],
        ],
    ];

    // Allow modules to add links to any section, or add entirely new sections.
    // Filter signature: applyFilters('admin_sidebar_links', array $sections) → array
    if (class_exists('HookRegistry')) {
        $adminSidebarSections = HookRegistry::applyFilters('admin_sidebar_links', $adminSidebarSections);
    }


    // Module-injected links via admin_menu hook (legacy string technique kept for compatibility).
    // Captured once, reused in both sidebars to avoid running hooks twice.
    ob_start();
    if (class_exists('HookRegistry')) { HookRegistry::doAction('admin_menu'); }
    $moduleMenuLinks = ob_get_clean();
    $moduleMenuLinks = str_replace(
        ['sidebar-section', 'sidebar-link text-slate-300'],
        [$sectionClass,     $linkClass . ' active-link-target'],
        $moduleMenuLinks
    );
    ?>

    <!-- Sidebar (Desktop) -->
    <aside class="w-[260px] bg-sidebar flex-shrink-0 hidden lg:flex flex-col border-r border-sidebar-border shadow-xl z-20">
        <div class="h-16 flex items-center px-6 border-b border-sidebar-border gap-3 shrink-0">
            <?php
                $siteLogo = $GLOBALS['appSettings']['site_logo'] ?? '';
                $brandDir = realpath(__DIR__ . '/../uploads/branding');
                if ($siteLogo && $brandDir && file_exists($brandDir . '/' . $siteLogo)):
            ?>
                <img src="<?= SITE_URL ?>/uploads/branding/<?= rawurlencode($siteLogo) ?>" alt="Logo" class="h-8 w-auto object-contain brightness-0 invert">
            <?php else: ?>
                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-blue-500/20">
                    <?= substr(SITE_TITLE ?? 'M', 0, 1) ?>
                </div>
                <span class="text-white font-bold text-lg tracking-tight">Museum<span class="text-blue-400 font-normal">Admin</span></span>
            <?php endif; ?>
        </div>

        <nav class="flex-1 py-4 px-3 overflow-y-auto sidebar-scroller">
            <?php renderAdminSidebarNav($adminSidebarSections, $linkClass, $sectionClass, true); ?>
            <?= $moduleMenuLinks ?>
        </nav>

        <div class="p-4 border-t border-sidebar-border bg-slate-900 shadow-inner">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold shadow-md">
                    <?= strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-white truncate"><?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></p>
                    <p class="text-xs text-slate-400">Administrator</p>
                </div>
                <a href="<?= SITE_URL ?>/admin/logout.php" class="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors" title="Log Out">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Mobile Drawer — rendered from same data structure, no icons -->
    <div id="mobile-nav-overlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity" onclick="closeMobileNav()"></div>
    <aside id="mobile-nav" class="fixed inset-y-0 left-0 w-[280px] max-w-[85vw] bg-sidebar z-50 transform -translate-x-full transition-transform duration-300 ease-out lg:hidden flex flex-col shadow-2xl">
        <div class="h-16 flex items-center justify-between px-4 border-b border-sidebar-border shrink-0">
            <span class="text-white font-bold text-lg">Menu</span>
            <button type="button" onclick="closeMobileNav()" class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors" aria-label="Close">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <nav class="flex-1 py-4 px-3 overflow-y-auto sidebar-scroller">
            <?php renderAdminSidebarNav($adminSidebarSections, $linkClass, $sectionClass, false); ?>
            <?= $moduleMenuLinks ?>
        </nav>
    </aside>


    <!-- Main Content wrapper -->
    <div class="flex-1 flex flex-col min-w-0 bg-slate-50 relative">
        <!-- Top Bar -->
        <header class="bg-white/80 backdrop-blur-md border-b border-slate-200/80 h-16 flex items-center justify-between px-4 md:px-8 shrink-0 shadow-sm shadow-slate-200/20 sticky top-0 z-10 transition-shadow">
            <div class="flex items-center gap-4">
                <button type="button" onclick="openMobileNav()" class="lg:hidden p-2 -ml-2 rounded-lg text-slate-500 hover:bg-slate-100 transition-colors" aria-label="Open menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h1 class="text-xl font-bold text-slate-800 tracking-tight hidden sm:block"><?= htmlspecialchars($title) ?></h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="<?= SITE_URL ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 hover:text-slate-900 transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                    View Live Site
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                </a>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-y-auto p-4 md:p-8">
            <div class="max-w-7xl mx-auto w-full">
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
}
function closeMobileNav() {
    document.getElementById('mobile-nav').classList.add('-translate-x-full');
    document.getElementById('mobile-nav-overlay').classList.add('hidden');
}

// Active State Logic
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const mParam = urlParams.get('m');
    const path = window.location.pathname;
    
    document.querySelectorAll('.active-link-target').forEach(link => {
        const href = link.getAttribute('href');
        if (!href) return;
        
        try {
            const url = new URL(href, window.location.origin);
            const linkMParam = url.searchParams.get('m');
            
            let isMatch = false;
            
            if (mParam && linkMParam) {
                // If we are on a module page, exactly match the ?m= parameter
                isMatch = (mParam === linkMParam && path.endsWith(url.pathname.split('/').pop()));
            } else if (!mParam && !linkMParam) {
                // Not on a module page, match the path exactly
                isMatch = path.endsWith(url.pathname.split('/').pop());
            }

            if (isMatch) {
                // Apply Tailwind active classes directly
                link.classList.add('bg-blue-600/10', 'text-blue-400');
                link.classList.remove('text-slate-300', 'hover:bg-white/10');
            }
        } catch(e) {}
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
