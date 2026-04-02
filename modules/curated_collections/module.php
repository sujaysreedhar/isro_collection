<?php

class CuratedCollectionsModule extends BaseModule {

    public function boot() {
        // Add Collections to the main navigation
        HookRegistry::addFilter('frontend_nav_links', [$this, 'addNavLinks']);

        // Inject matching collections into search results
        HookRegistry::addAction('search_results_before_items', [$this, 'injectIntoSearch'], 10, 2);

        // Admin hooks
        HookRegistry::addAction('admin_menu', [$this, 'addAdminMenu']);
        HookRegistry::addAction('admin_init_curated_collections', [$this, 'handleAdminLogic']);
        HookRegistry::addAction('admin_page_curated_collections', [$this, 'renderAdminPage']);

        // Home page modular section
        HookRegistry::addAction('home_page_sections', [$this, 'renderHomeSection']);
    }

    public function handleAdminLogic() {
        require_once __DIR__ . '/admin_collections_logic.php';
    }

    public function addNavLinks($links) {
        $links['collections'] = [
            'url' => SITE_URL . '/collections.php',
            'label' => 'Collections'
        ];
        return $links;
    }

    public function injectIntoSearch($results, $params) {
        $q = $params['q'] ?? '';
        if (empty($q)) return;

        $stmt = $this->pdo->prepare("SELECT * FROM collections WHERE is_public = 1 AND (title LIKE ? OR description LIKE ?) LIMIT 3");
        $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
        $matches = $stmt->fetchAll();

        if ($matches) {
            echo '<div class="mb-10">';
            echo '<h2 class="text-sm font-bold uppercase tracking-wider text-gray-400 mb-4 px-2">Matching Collections</h2>';
            echo '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">';
            foreach ($matches as $col) {
                $url = SITE_URL . '/collection.php?slug=' . urlencode($col['slug']);
                echo '<a href="' . $url . '" class="flex items-center p-3 bg-white border border-gray-200 rounded-xl hover:shadow-md transition group">';
                echo '<div class="w-12 h-12 bg-gray-100 rounded-lg flex-shrink-0 flex items-center justify-center text-gray-400 group-hover:bg-blue-50 group-hover:text-blue-500 transition">';
                echo '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>';
                echo '</div>';
                echo '<div class="ml-4 min-w-0">';
                echo '<h3 class="font-bold text-gray-900 truncate">' . htmlspecialchars($col['title']) . '</h3>';
                echo '<p class="text-xs text-gray-500">Curated Collection</p>';
                echo '</div>';
                echo '</a>';
            }
            echo '</div>';
            echo '</div>';
        }
    }

    public function addAdminMenu() {
        $url = SITE_URL . '/admin/module_page.php?m=curated_collections';
        echo '<a href="' . $url . '" class="sidebar-link text-slate-300">Curated Collections</a>';
    }

    public function renderAdminPage() {
        require_once __DIR__ . '/admin_collections.php';
    }

    public function renderHomeSection() {
        $stmt = $this->pdo->query("SELECT * FROM collections WHERE is_public = 1 ORDER BY id DESC LIMIT 3");
        $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$collections) return;

        echo '<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 border-t border-gray-100 dark:border-gray-800">';
        echo '<div class="flex items-center justify-between mb-8">';
        echo '<h2 class="text-3xl font-bold text-gray-900 dark:text-white serif">Curated Collections</h2>';
        echo '<a href="' . SITE_URL . '/collections.php" class="text-sm font-semibold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white flex items-center">View All <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></a>';
        echo '</div>';
        echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-8">';
        
        foreach ($collections as $col) {
            $url = SITE_URL . '/collection.php?slug=' . urlencode($col['slug']);
            echo '<a href="' . $url . '" class="group block bg-gray-50 dark:bg-gray-800 rounded-2xl p-6 border border-gray-200 dark:border-gray-700 hover:shadow-lg dark:hover:border-gray-600 transition-all">';
            echo '<div class="w-12 h-12 bg-white dark:bg-gray-700 rounded-xl flex items-center justify-center text-gray-400 dark:text-gray-300 mb-4 shadow-sm group-hover:scale-110 transition-transform">';
            echo '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>';
            echo '</div>';
            echo '<h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-blue-600 dark:group-hover:text-blue-400">' . htmlspecialchars($col['title']) . '</h3>';
            echo '<p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-3">' . htmlspecialchars(strip_tags($col['description'] ?? '')) . '</p>';
            echo '</a>';
        }
        
        echo '</div></div>';
    }
}
