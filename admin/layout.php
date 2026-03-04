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
            <a href="<?= SITE_URL ?>/admin/index.php" class="block px-3 py-2 rounded-md hover:bg-gray-800 hover:text-white text-gray-300 font-medium transition-colors">
                Dashboard
            </a>
            
            <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Catalog</div>
            <a href="<?= SITE_URL ?>/admin/items.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">
                Manage Items
            </a>
            <a href="<?= SITE_URL ?>/admin/categories.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">
                Categories
            </a>
            
            <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Content</div>
            <a href="<?= SITE_URL ?>/admin/narratives.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">
                Stories & Narratives
            </a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <p class="text-xs text-gray-500 mb-2">Logged in as <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></p>
            <a href="<?= SITE_URL ?>/admin/logout.php" class="block w-full text-center px-4 py-2 text-sm text-gray-300 bg-gray-800 rounded hover:bg-gray-700 transition">Log Out</a>
        </div>
    </aside>

    <!-- Main Content wrapper -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top header area for mobile/small desktop -->
        <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-6 md:justify-end shrink-0">
            <div class="md:hidden">
                <span class="text-lg font-bold">Museum<span class="text-gray-500">Admin</span></span>
            </div>
            <div class="flex items-center space-x-4">
                <a href="<?= SITE_URL ?>" target="_blank" class="text-sm text-blue-600 hover:underline">View Live Site &nearr;</a>
            </div>
        </header>
        
        <!-- Scrollable Page Content -->
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
</body>
</html>
<?php
    return ob_get_clean();
}
?>
