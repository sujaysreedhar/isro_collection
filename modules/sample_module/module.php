<?php
/*
Module Name: Sample Module
Description: Demonstrates activation hooks, injecting CSS, adding custom admin pages, and content hooks.
Version: 1.1
Author: System
*/

if (!class_exists('HookRegistry')) {
    return;
}

// 1. Activation & Deactivation 
HookRegistry::addAction('activate_sample_module', function() {
    error_log("Sample Module Activated at " . date('Y-m-d H:i:s'));
});

HookRegistry::addAction('deactivate_sample_module', function() {
    error_log("Sample Module Deactivated at " . date('Y-m-d H:i:s'));
});

// 2. Add Custom Admin Page Link
HookRegistry::addAction('admin_menu', function() {
    echo '<div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Sample Module</div>';
    echo '<a href="' . SITE_URL . '/admin/module_page.php?m=sample_module" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">Module Settings</a>';
});

// 3. Render Custom Admin Page Content
HookRegistry::addAction('admin_page_sample_module', function() {
    echo '<h2 class="text-xl font-semibold mb-4 text-blue-800">Welcome to the Sample Module Settings</h2>';
    echo '<p class="text-gray-600">This page is completely rendered by the sample module using the <code>admin_page_{module}</code> hook.</p>';
    echo '<div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded text-sm text-blue-700">You can build complex forms here, save configurations to the settings table, and manage your module data.</div>';
});

// 4. Inject into Frontend Head (CSS)
HookRegistry::addAction('frontend_head', function() {
    echo '<style>.sample-module-badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-weight: bold; background: linear-gradient(to right, #ec4899, #8b5cf6); color: white; margin-top: 10px; font-size: 0.85rem; }</style>';
});

// 5. Inject into item_detail.php
HookRegistry::addAction('item_after_content', function($item) {
    if (!$item) return;
    echo '<div class="mt-8 pt-6 border-t border-gray-200 text-center">';
    echo '  <p class="text-gray-500">This artifact view is enhanced by a module.</p>';
    echo '  <div class="sample-module-badge">Sample Module Loaded</div>';
    echo '</div>';
});
