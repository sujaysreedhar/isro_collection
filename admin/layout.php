<?php
// admin/layout.php
// This simple layout encapsulates the SaaS style sidebar and Topbar

function renderAdminHeader($title) {
    ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-64 bg-gray-900 text-white flex-shrink-0 hidden md:flex flex-col">
        <div class="h-16 flex items-center px-6 border-b border-gray-800">
            <a href="<?= SITE_URL ?>/admin/index.php" class="text-xl font-bold tracking-tight">Museum<span class="text-gray-400 font-normal">Admin</span></a>
        </div>
        <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
            <a href="<?= SITE_URL ?>/admin/index.php" class="block px-3 py-2 rounded-md hover:bg-gray-800 hover:text-white text-gray-300 font-medium transition-colors">Dashboard</a>
            <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Catalog</div>
            <a href="<?= SITE_URL ?>/admin/items.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">Manage Items</a>
            <a href="<?= SITE_URL ?>/admin/categories.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">Categories</a>
            <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Content</div>
            <a href="<?= SITE_URL ?>/admin/narratives.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">Stories & Narratives</a>
            <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">System</div>
            <a href="<?= SITE_URL ?>/admin/users.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">Administrators</a>
            <a href="<?= SITE_URL ?>/admin/settings.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">Storage Settings</a>
            <a href="<?= SITE_URL ?>/admin/storage_migration.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">Storage Migration</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <p class="text-xs text-gray-500 mb-2">Logged in as <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></p>
            <a href="<?= SITE_URL ?>/admin/logout.php" class="block w-full text-center px-4 py-2 text-sm text-gray-300 bg-gray-800 rounded hover:bg-gray-700 transition">Log Out</a>
        </div>
    </aside>

    <!-- Mobile Drawer -->
    <div id="mobile-nav-overlay" class="fixed inset-0 bg-black/40 z-40 hidden md:hidden" onclick="closeMobileNav()"></div>
    <aside id="mobile-nav" class="fixed inset-y-0 left-0 w-72 max-w-[90vw] bg-gray-900 text-white z-50 transform -translate-x-full transition-transform duration-200 md:hidden flex flex-col">
        <div class="h-16 flex items-center justify-between px-4 border-b border-gray-800">
            <a href="<?= SITE_URL ?>/admin/index.php" class="text-lg font-bold tracking-tight">Museum<span class="text-gray-400 font-normal">Admin</span></a>
            <button type="button" onclick="closeMobileNav()" class="p-2 rounded hover:bg-gray-800" aria-label="Close menu">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
            <a href="<?= SITE_URL ?>/admin/index.php" class="block px-3 py-2 rounded-md hover:bg-gray-800 text-gray-300">Dashboard</a>
            <a href="<?= SITE_URL ?>/admin/items.php" class="block px-3 py-2 rounded-md hover:bg-gray-800 text-gray-300">Manage Items</a>
            <a href="<?= SITE_URL ?>/admin/categories.php" class="block px-3 py-2 rounded-md hover:bg-gray-800 text-gray-300">Categories</a>
            <a href="<?= SITE_URL ?>/admin/narratives.php" class="block px-3 py-2 rounded-md hover:bg-gray-800 text-gray-300">Stories & Narratives</a>
            <a href="<?= SITE_URL ?>/admin/users.php" class="block px-3 py-2 rounded-md hover:bg-gray-800 text-gray-300">Administrators</a>
            <a href="<?= SITE_URL ?>/admin/settings.php" class="block px-3 py-2 rounded-md hover:bg-gray-800 text-gray-300">Storage Settings</a>
            <a href="<?= SITE_URL ?>/admin/storage_migration.php" class="block px-3 py-2 rounded-md hover:bg-gray-800 text-gray-300">Storage Migration</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <a href="<?= SITE_URL ?>/admin/logout.php" class="block w-full text-center px-4 py-2 text-sm text-gray-300 bg-gray-800 rounded hover:bg-gray-700 transition">Log Out</a>
        </div>
    </aside>

    <!-- Main Content wrapper -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-6 md:justify-end shrink-0">
            <div class="md:hidden flex items-center gap-3">
                <button type="button" onclick="openMobileNav()" class="p-2 rounded-md border border-gray-300 text-gray-700" aria-label="Open menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <span class="text-lg font-bold">Museum<span class="text-gray-500">Admin</span></span>
            </div>
            <div class="flex items-center space-x-4">
                <a href="<?= SITE_URL ?>" target="_blank" class="text-sm text-blue-600 hover:underline">View Live Site &nearr;</a>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto bg-gray-50 p-6 md:p-8">
            <div class="max-w-7xl mx-auto">
<?php
    return ob_get_clean();
}

function renderAdminFooter() {
    ob_start();
?>
            </div>
        </main>
    </div>

<script>
function openMobileNav() {
    document.getElementById('mobile-nav').classList.remove('-translate-x-full');
    document.getElementById('mobile-nav-overlay').classList.remove('hidden');
}

function closeMobileNav() {
    document.getElementById('mobile-nav').classList.add('-translate-x-full');
    document.getElementById('mobile-nav-overlay').classList.add('hidden');
}
</script>
</body>
</html>
<?php
    return ob_get_clean();
}
?>
