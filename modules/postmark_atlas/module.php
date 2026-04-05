<?php
// modules/postmark_atlas/module.php

class PostmarkAtlasModule extends BaseModule {

    public function boot() {
        // Ensure new columns exist on existing installations
        try { $this->runMigrations(); } catch (\Throwable $e) { /* table may not exist yet */ }

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
            ppc_name VARCHAR(200) DEFAULT NULL,
            district VARCHAR(100),
            state VARCHAR(100),
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            is_acquired TINYINT(1) DEFAULT 0,
            linked_item_id INT DEFAULT NULL,
            is_locked TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ";

        if (class_exists('ModuleDB')) {
            ModuleDB::createTable($this->pdo, 'postmark_locations', $schemaDef);
        }

        // Safe migration for existing tables — add new columns if absent
        $this->runMigrations();
    }

    /**
     * Safe ALTER TABLE migrations — idempotent, can be called on existing DBs.
     */
    public function runMigrations(): void {
        $cols = $this->pdo->query("SHOW COLUMNS FROM postmark_locations")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('ppc_name', $cols)) {
            $this->pdo->exec("ALTER TABLE postmark_locations ADD COLUMN ppc_name VARCHAR(200) DEFAULT NULL AFTER post_office");
        }
        if (!in_array('linked_item_id', $cols)) {
            $this->pdo->exec("ALTER TABLE postmark_locations ADD COLUMN linked_item_id INT DEFAULT NULL AFTER is_acquired");
        }
        if (!in_array('is_locked', $cols)) {
            $this->pdo->exec("ALTER TABLE postmark_locations ADD COLUMN is_locked TINYINT(1) DEFAULT 0 AFTER linked_item_id");
        }
    }
}
