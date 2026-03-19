<?php
// includes/frontend.php

if (!function_exists('renderFrontendNav')) {
    function renderFrontendNav(string $currentMenu = '') {
        $defaultLinks = [
            'explore' => ['url' => SITE_URL . '/search.php', 'label' => 'Explore Collections'],
            'gallery' => ['url' => SITE_URL . '/gallery.php', 'label' => 'Visual Gallery'],
        ];

        // Apply filters to allow modules to register their own navigation items
        $links = class_exists('HookRegistry') 
            ? HookRegistry::applyFilters('frontend_nav_links', $defaultLinks) 
            : $defaultLinks;
        
        echo '<nav class="hidden lg:flex space-x-8 ml-8 flex-shrink-0 items-center">';
        foreach ($links as $id => $link) {
            $activeClass = ($currentMenu === $id) 
                ? 'text-gray-900 font-bold border-b-2 border-gray-900 pb-1' 
                : 'text-gray-500 hover:text-gray-900 font-medium pb-1 border-b-2 border-transparent hover:border-gray-300';
            echo '<a href="' . htmlspecialchars($link['url']) . '" class="' . $activeClass . ' text-sm transition-colors">' . htmlspecialchars($link['label']) . '</a>';
        }
        echo '</nav>';
    }
}
