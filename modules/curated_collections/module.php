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
}
