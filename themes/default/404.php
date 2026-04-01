<?php
// themes/default/404.php
$pageTitle = '404 Not Found - ' . SITE_TITLE;
$currentMenu = '';
require_once ThemeManager::getHeader();
?>
<main class="flex-grow flex items-center justify-center py-20 px-4 sm:px-6 lg:px-8 bg-gray-50">
    <div class="max-w-max mx-auto text-center">
        <p class="text-sm font-semibold text-blue-600 uppercase tracking-wide">404 error</p>
        <h1 class="mt-2 text-4xl font-extrabold text-gray-900 tracking-tight sm:text-5xl">Page not found.</h1>
        <p class="mt-4 text-base text-gray-500">Sorry, we couldn’t find the page you’re looking for.</p>
        <div class="mt-6 flex justify-center gap-4">
            <a href="<?= SITE_URL ?>/" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Go back home
            </a>
            <a href="<?= SITE_URL ?>/search.php" class="inline-flex items-center px-4 py-2 border border-blue-600 text-sm font-medium rounded-md shadow-sm text-blue-600 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Search Collection
            </a>
        </div>
    </div>
</main>
<?php require_once ThemeManager::getFooter(); ?>
