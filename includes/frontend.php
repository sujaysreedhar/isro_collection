<?php
// includes/frontend.php

if (!function_exists('renderFrontendNav')) {
    /**
     * Renders a navigation menu.
     * 
     * @param string $activeMenuItem The ID/slug of the currently active menu item (for highlighting)
     * @param bool $isMobile Whether to render for mobile view
     * @param string $menuSlug The slug of the menu to render (default 'header')
     */
    function renderFrontendNav(string $activeMenuItem = '', bool $isMobile = false, string $menuSlug = 'header') {
        // Handle case where first arg might be the slug if more than one arg is passed 
        // and the first one doesn't look like a typical active item ID but a slug.
        // Actually, for consistency, we'll stick to the signature. Themes usually call:
        // renderFrontendNav($currentMenu) or renderFrontendNav($currentMenu, true)
        
        $cachePath = __DIR__ . '/cache/menus/' . $menuSlug . '.json';
        $links = [];

        // Try loading from cache
        if (file_exists($cachePath)) {
            $links = json_decode(file_get_contents($cachePath), true) ?: [];
        }

        // Fallback to database or defaults if cache is empty
        if (empty($links)) {
            global $pdo;
            if (isset($pdo)) {
                try {
                    $stmt = $pdo->prepare("
                        SELECT i.* FROM navigation_menu_items i
                        JOIN navigation_menus m ON i.menu_id = m.id
                        WHERE m.slug = :slug AND i.is_visible = 1
                        ORDER BY i.sort_order ASC
                    ");
                    $stmt->execute([':slug' => $menuSlug]);
                    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if ($rawData) {
                        // Build tree structure
                        require_once __DIR__ . '/frontend.php'; // Ensure recursion helper is visible
                        $links = buildNavTree($rawData);
                        
                        // Regenerate cache for next time
                        $cacheDir = dirname($cachePath);
                        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
                        @file_put_contents($cachePath, json_encode($links));
                    }
                } catch (Exception $e) {
                    // Silently fail if table doesn't exist yet
                }
            }
        }

        // Absolute fallback to defaults for 'header' if still empty
        if (empty($links) && $menuSlug === 'header') {
            $links = [
                ['url' => 'search.php', 'label' => 'Explore Collections', 'slug' => 'explore'],
                ['url' => 'gallery.php', 'label' => 'Visual Gallery', 'slug' => 'gallery'],
            ];
        }

        // Apply filters for backward compatibility
        $links = class_exists('HookRegistry') 
            ? HookRegistry::applyFilters('frontend_nav_' . $menuSlug . '_links', $links) 
            : $links;

        if (empty($links)) return;

        if ($isMobile) {
            echo '<nav class="flex flex-col space-y-4 p-6">';
            renderNavItems($links, true, 0, $activeMenuItem);
            echo '</nav>';
        } else {
            echo '<nav class="hidden lg:flex space-x-8 ml-8 flex-shrink-0 items-center">';
            renderNavItems($links, false, 0, $activeMenuItem);
            echo '</nav>';
        }
    }

    /**
     * Recursive helper to build a tree from flat menu data
     */
    function buildNavTree(array &$data, $parentId = null) {
        $branch = [];
        foreach ($data as $item) {
            if ($item['parent_id'] == $parentId) {
                $children = buildNavTree($data, $item['id']);
                if ($children) {
                    $item['children'] = $children;
                }
                $branch[] = $item;
            }
        }
        return $branch;
    }

    /**
     * Recursive helper to render navigation items
     */
    function renderNavItems(array $items, bool $isMobile, int $depth = 0, string $activeMenuItem = '') {
        foreach ($items as $link) {
            $url = $link['url'];
            if (!preg_match('/^https?:\/\//', $url) && !str_starts_with($url, '/')) {
                $url = SITE_URL . '/' . $url;
            }
            
            $label = htmlspecialchars($link['label']);
            $targetAttr = (!empty($link['target_blank'])) ? ' target="_blank"' : '';
            $hasChildren = !empty($link['children']);
            $itemSlug = $link['slug'] ?? '';
            
            $isActive = ($activeMenuItem !== '' && $itemSlug === $activeMenuItem);

            if ($isMobile) {
                $activeClass = $isActive ? 'font-bold text-black bg-gray-50' : 'text-gray-700 hover:text-black';
                $indent = str_repeat('ml-4 ', $depth);
                echo '<div class="flex flex-col">';
                echo '<a href="' . htmlspecialchars($url) . '"' . $targetAttr . ' class="' . $indent . ' text-lg transition-all duration-200 p-2 rounded-lg ' . $activeClass . '">' . $label . '</a>';
                if ($hasChildren) {
                    echo '<div class="flex flex-col mt-2">';
                    renderNavItems($link['children'], true, $depth + 1, $activeMenuItem);
                    echo '</div>';
                }
                echo '</div>';
            } else {
                if ($hasChildren && $depth === 0) {
                    $activeClass = $isActive ? 'text-gray-900 font-bold border-gray-900' : 'text-gray-500 hover:text-gray-900 border-transparent hover:border-gray-300';
                    echo '<div class="relative group">';
                    echo '<a href="' . htmlspecialchars($url) . '"' . $targetAttr . ' class="' . $activeClass . ' font-medium pb-1 border-b-2 flex items-center gap-1 inline-block">' . $label . ' <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></a>';
                    echo '<div class="absolute left-0 mt-2 w-48 bg-white border border-gray-100 rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-[100]">';
                    echo '<div class="py-1">';
                    renderNavItems($link['children'], false, $depth + 1, $activeMenuItem);
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                } elseif ($depth > 0) {
                    $activeClass = $isActive ? 'bg-gray-100 text-gray-900 font-bold' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900';
                    echo '<a href="' . htmlspecialchars($url) . '"' . $targetAttr . ' class="block px-4 py-2 text-sm ' . $activeClass . '">' . $label . '</a>';
                } else {
                    $activeClass = $isActive ? 'text-gray-900 font-bold border-gray-900' : 'text-gray-500 hover:text-gray-900 border-transparent hover:border-gray-300';
                    echo '<a href="' . htmlspecialchars($url) . '"' . $targetAttr . ' class="' . $activeClass . ' font-medium pb-1 border-b-2 transition-colors">' . $label . '</a>';
                }
            }
        }
    }
}
