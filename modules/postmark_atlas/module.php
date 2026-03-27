<?php
// modules/postmark_atlas/module.php

class PostmarkAtlasModule extends BaseModule {

    public function boot() {
        HookRegistry::addAction('admin_menu', function() {
            echo '<div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Postmark Atlas</div>';
            echo '<a href="' . SITE_URL . '/admin/module_page.php?m=postmark_atlas&page=locations" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">Locations Tracker</a>';
            echo '<a href="' . SITE_URL . '/admin/module_page.php?m=postmark_atlas&page=map" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">Atlas Map</a>';
            echo '<a href="' . SITE_URL . '/admin/module_page.php?m=postmark_atlas&page=import" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">Import KML</a>';
        });

        HookRegistry::addFilter('frontend_nav_links', function($links) {
            $links['atlas'] = ['url' => SITE_URL . '/atlas.php', 'label' => 'Atlas Map'];
            return $links;
        });

        HookRegistry::addAction('admin_page_postmark_atlas', function() {
            $page = $_GET['page'] ?? 'locations';
            
            if ($page === 'locations') {
                require_once __DIR__ . '/admin_locations.php';
            } elseif ($page === 'map') {
                require_once __DIR__ . '/admin_map.php';
            } elseif ($page === 'import') {
                require_once __DIR__ . '/import_kml.php';
            } else {
                echo "<p>Unknown page.</p>";
            }
        });
    }

    public function activate() {
        $schemaDef = "
            id INT AUTO_INCREMENT PRIMARY KEY,
            pin_code VARCHAR(20) NOT NULL,
            post_office VARCHAR(100) NOT NULL,
            district VARCHAR(100),
            state VARCHAR(100),
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            is_acquired TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ";
        
        if (class_exists('ModuleDB')) {
            ModuleDB::createTable($this->pdo, 'postmark_locations', $schemaDef);
        }
    }
}
