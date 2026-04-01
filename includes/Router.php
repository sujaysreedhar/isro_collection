<?php
// includes/Router.php

class Router {
    public static function dispatch() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Strip base path (SITE_URL path)
        $basePath = parse_url(SITE_URL, PHP_URL_PATH) ?? '';
        if ($basePath && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        $uri = trim($uri, '/');
        
        // 1. Let modules hook in first. 
        // If a module returns true, we assume it has handled the request and we exit.
        if (class_exists('HookRegistry')) {
            $handled = HookRegistry::applyFilters('route_request', false, $uri);
            if ($handled) {
                return;
            }
        }
        
        // 2. Fallback to core routes
        if ($uri === '') {
            require __DIR__ . '/pages/home.php';
        } elseif ($uri === 'search' || $uri === 'search.php') {
            require __DIR__ . '/pages/search.php';
        } elseif (preg_match('#^item/([0-9]+)(?:/[a-zA-Z0-9_-]+)?/?$#', $uri, $matches)) {
            $_GET['id'] = $matches[1];
            require __DIR__ . '/pages/item_detail.php';
        } elseif (preg_match('#^category/([a-zA-Z0-9_-]+)/?$#', $uri, $matches)) {
            $_GET['category'] = $matches[1];
            require __DIR__ . '/pages/search.php';
        } elseif (preg_match('#^tag/([a-zA-Z0-9_-]+)/?$#', $uri, $matches)) {
            $_GET['tag'] = $matches[1];
            require __DIR__ . '/pages/search.php';
        } elseif (preg_match('#^story/([0-9]+)/?$#', $uri, $matches)) {
            $_GET['id'] = $matches[1];
            require __DIR__ . '/../story.php'; // assuming story.php is at root
        } elseif ($uri === 'test_filter.php' || $uri === 'verify_changes.php' || $uri === 'verify_assets.php' || str_starts_with($uri, 'admin/')) {
            // we should not route admin directly if not routed by Apache, but htaccess !f filter handles existing files
            http_response_code(404);
            require_once ThemeManager::getTemplatePath('404.php');
        } else {
            // 404
            http_response_code(404);
            require_once ThemeManager::getTemplatePath('404.php');
        }
    }
}
