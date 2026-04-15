<?php
// modules/media_manager/module.php

class MediaManagerModule extends BaseModule {

    public function boot() {
        HookRegistry::addFilter('admin_sidebar_links', function($sections) {
            $sections['system']['links']['media_manager'] = [
                'url'   => SITE_URL . '/admin/module_page.php?m=media_manager&page=audit',
                'label' => 'Media Audit',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />'
            ];
            return $sections;
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
