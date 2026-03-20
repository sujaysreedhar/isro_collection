<?php
// modules/timeline/module.php

function timeline_activate() {
    // Database schema changes (year_start) are already part of the core items table now
    // Since we baked year_start and year_end into the core schema for search filters
    return true;
}

function timeline_deactivate() {
    return true;
}

// Add 'Timeline' to the main navigation menu
HookRegistry::addFilter('frontend_nav_links', function($links) {
    // Check if timeline link is already there to avoid duplicates
    foreach ($links as $link) {
        if ($link['url'] === SITE_URL . '/timeline.php') {
            return $links;
        }
    }
    
    // Insert after 'Explore' or just append
    $links[] = ['url' => SITE_URL . '/timeline.php', 'label' => 'Timeline'];
    return $links;
});
