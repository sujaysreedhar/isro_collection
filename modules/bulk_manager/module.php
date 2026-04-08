<?php
// modules/bulk_manager/module.php

class BulkManagerModule extends BaseModule {

    public function boot() {
        // 1. Admin Menu
        HookRegistry::addFilter('admin_sidebar_links', function($links) {
            if (!isset($links['tools'])) {
                $links['tools'] = [
                    'label' => 'Tools',
                    'links' => []
                ];
            }
            $links['tools']['links']['bulk_manager'] = [
                'url' => SITE_URL . '/admin/module_page.php?m=bulk_manager',
                'label' => '📦 Bulk Manager',
                'icon' => 'archive'
            ];
            return $links;
        });

        HookRegistry::addAction('admin_page_bulk_manager', function() {
            require_once __DIR__ . '/admin/tool.php';
        });
    }

    public function activate() {
        // No specific tables needed for bulk operations usually, 
        // as we operate on core tables.
    }
}
