<?php
// modules/media_manager/module.php

class MediaManagerModule extends BaseModule {

    public function boot() {
        HookRegistry::addAction('admin_menu', function() {
            echo '<div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Media Tools</div>';
            echo '<a href="' . SITE_URL . '/admin/module_page.php?m=media_manager&page=audit" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">Media Audit</a>';
        });

        HookRegistry::addAction('admin_page_media_manager', function() {
            $page = $_GET['page'] ?? 'audit';
            
            if ($page === 'audit') {
                require_once __DIR__ . '/admin_media.php';
            } else {
                echo "<p>Unknown page.</p>";
            }
        });
    }
}
