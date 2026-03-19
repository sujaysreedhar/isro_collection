<?php
// modules/contact_us/module.php

class ContactUsModule extends BaseModule {

    public function boot() {
        // Add navigation link on frontend
        HookRegistry::addFilter('frontend_nav_links', function($links) {
            $links['contact'] = ['url' => SITE_URL . '/contact.php', 'label' => 'Contact'];
            return $links;
        });

        // Admin sidebar links
        HookRegistry::addAction('admin_menu', function() {
            echo '<div class="sidebar-section">Contact</div>';
            echo '<a href="' . SITE_URL . '/admin/module_page.php?m=contact_us" class="sidebar-link text-slate-300">';
            echo '<svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>';
            echo 'Messages</a>';
        });

        HookRegistry::addAction('admin_menu_mobile', function() {
            echo '<a href="' . SITE_URL . '/admin/module_page.php?m=contact_us" class="sidebar-link text-slate-300">Messages</a>';
        });

        // Admin page content
        HookRegistry::addAction('admin_page_contact_us', function() {
            require_once __DIR__ . '/admin_messages.php';
        });
    }

    public function activate() {
        if (class_exists('ModuleDB')) {
            ModuleDB::createTable($this->pdo, 'contact_messages', "
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                subject VARCHAR(500) DEFAULT '',
                message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ");
        }
    }
}
