<?php
// modules/exhibition_planner/module.php

class ExhibitionPlannerModule extends BaseModule
{
    public function boot()
    {
        HookRegistry::addFilter('admin_sidebar_links', function ($links) {
            $links['content']['links']['exhibition_planner'] = [
                'url' => SITE_URL . '/admin/module_page.php?m=exhibition_planner',
                'label' => 'Exhibition Planner',
                'icon' => 'image'
            ];
            return $links;
        });

        HookRegistry::addAction('admin_init_exhibition_planner', function () {
            require_once __DIR__ . '/admin/manage_init.php';
        });

        HookRegistry::addAction('admin_page_exhibition_planner', function () {
            require_once __DIR__ . '/admin/manage.php';
        });

        HookRegistry::addFilter('frontend_nav_links', function ($links) {
            $links['exhibitions'] = [
                'url' => SITE_URL . '/exhibitions',
                'label' => 'Exhibitions'
            ];
            return $links;
        });

        HookRegistry::addFilter('route_request', function ($handled, $uri) {
            if ($handled) {
                return $handled;
            }

            if ($uri === 'exhibitions') {
                require_once __DIR__ . '/exhibitions.php';
                return true;
            }

            if (preg_match('#^exhibition/([a-zA-Z0-9_-]+)/?$#', $uri, $matches)) {
                $_GET['slug'] = $matches[1];
                require_once __DIR__ . '/exhibition.php';
                return true;
            }

            return false;
        }, 10, 2);
    }

    public function activate()
    {
        ModuleDB::createTable($this->pdo, 'module_exhibition_pages', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            description TEXT,
            banner_image VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ");

        ModuleDB::createTable($this->pdo, 'module_exhibition_items', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_id INT NOT NULL,
            item_id INT NOT NULL,
            sort_order INT DEFAULT 0,
            annotation TEXT,
            UNIQUE KEY uniq_module_exhibition_page_item (page_id, item_id),
            INDEX idx_module_exhibition_page_sort (page_id, sort_order, id),
            FOREIGN KEY (page_id) REFERENCES module_exhibition_pages(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ");
    }

    public function uninstall()
    {
        $this->pdo->exec("DROP TABLE IF EXISTS module_exhibition_items");
        $this->pdo->exec("DROP TABLE IF EXISTS module_exhibition_pages");
    }
}
