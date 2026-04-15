<?php
// modules/postmark_atlas/module.php

class PostmarkAtlasModule extends BaseModule {

    public function boot() {
        // Ensure new columns exist on existing installations
        try { $this->runMigrations(); } catch (\Throwable $e) { /* table may not exist yet */ }

        HookRegistry::addFilter('admin_sidebar_links', function($sections) {
            $sections['atlas'] = [
                'label' => 'Postmark Atlas',
                'links' => [
                    'atlas_locations' => [
                        'url'   => SITE_URL . '/admin/module_page.php?m=postmark_atlas&page=locations',
                        'label' => 'Locations Tracker',
                        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />'
                    ],
                    'atlas_map' => [
                        'url'   => SITE_URL . '/admin/module_page.php?m=postmark_atlas&page=map',
                        'label' => 'Atlas Map',
                        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-10.5v.15m0 4.5v.15m0 4.5v.15m0 4.5V21l-3.375-3.375L9 21l-3.375-3.375H3.75a1.125 1.125 0 01-1.125-1.125V4.875c0-.621.504-1.125 1.125-1.125h2.25l3.375 3.375L12.75 3.75h2.25c.621 0 1.125.504 1.125 1.125V18" />'
                    ],
                    'atlas_validate' => [
                        'url'   => SITE_URL . '/admin/module_page.php?m=postmark_atlas&page=validate',
                        'label' => 'Validate Coords',
                        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />'
                    ],
                ]
            ];
            return $sections;
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
            } elseif ($page === 'validate') {
                require_once __DIR__ . '/admin_validate.php';
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
