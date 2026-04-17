<?php
/*
Module Name: Navigation Menu Manager
Description: Manage multiple frontend navigation menus with nested items and performance caching.
Version: 1.0
Author: System
*/

if (!class_exists('HookRegistry')) return;

// ── Admin Menu Registration ───────────────────────────────────────────────────
HookRegistry::addFilter('admin_sidebar_links', function ($sections) {
    $sections['system']['links']['nav_manager'] = [
        'url'   => SITE_URL . '/admin/module_page.php?m=nav_manager',
        'label' => 'Menu Manager',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />'
    ];
    return $sections;
});

// ── Admin Page Handlers ───────────────────────────────────────────────────────
HookRegistry::addAction('admin_page_nav_manager', function () {
    include __DIR__ . '/admin.php';
});

// ── Cache Utility ─────────────────────────────────────────────────────────────
if (!function_exists('nav_manager_rebuild_cache')) {
    function nav_manager_rebuild_cache(string $menuSlug) {
        global $pdo;
        $cachePath = dirname(__DIR__, 2) . '/includes/cache/menus/' . $menuSlug . '.json';
        
        try {
            $stmt = $pdo->prepare("
                SELECT i.* FROM navigation_menu_items i
                JOIN navigation_menus m ON i.menu_id = m.id
                WHERE m.slug = :slug AND i.is_visible = 1
                ORDER BY i.sort_order ASC
            ");
            $stmt->execute([':slug' => $menuSlug]);
            $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            require_once dirname(__DIR__, 2) . '/includes/frontend.php';
            $links = buildNavTree($rawData);
            
            $cacheDir = dirname($cachePath);
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
            file_put_contents($cachePath, json_encode($links));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

// ── AJAX Handlers ────────────────────────────────────────────────────────────

// Fetch all menus
HookRegistry::addFilter('admin_ajax_nav_manager_get_menus', function ($handled) {
    global $pdo;
    $menus = $pdo->query("SELECT * FROM navigation_menus ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'menus' => $menus]);
    return true;
});

// Fetch items for a specific menu
HookRegistry::addFilter('admin_ajax_nav_manager_get_items', function ($handled) {
    global $pdo;
    $menuId = (int)($_GET['menu_id'] ?? 0);
    if (!$menuId) {
        echo json_encode(['success' => false, 'error' => 'Invalid menu ID']);
        return true;
    }

    $stmt = $pdo->prepare("SELECT * FROM navigation_menu_items WHERE menu_id = :id ORDER BY sort_order ASC");
    $stmt->execute([':id' => $menuId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'items' => $items]);
    return true;
});

// Save or Update an item
HookRegistry::addFilter('admin_ajax_nav_manager_save_item', function ($handled) {
    global $pdo;
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        return true;
    }

    $id = (int)($_POST['id'] ?? 0);
    $menuId = (int)($_POST['menu_id'] ?? 0);
    $label = trim($_POST['label'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $isVisible = (int)($_POST['is_visible'] ?? 1);
    $targetBlank = (int)($_POST['target_blank'] ?? 0);

    if (!$menuId || empty($label)) {
        echo json_encode(['success' => false, 'error' => 'Label and Menu ID are required']);
        return true;
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE navigation_menu_items SET label = ?, url = ?, slug = ?, is_visible = ?, target_blank = ? WHERE id = ?");
        $stmt->execute([$label, $url, $slug, $isVisible, $targetBlank, $id]);
    } else {
        // Get max sort order
        $sort = $pdo->prepare("SELECT MAX(sort_order) FROM navigation_menu_items WHERE menu_id = ?");
        $sort->execute([$menuId]);
        $nextSort = (int)$sort->fetchColumn() + 1;

        $stmt = $pdo->prepare("INSERT INTO navigation_menu_items (menu_id, label, url, slug, is_visible, target_blank, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$menuId, $label, $url, $slug, $isVisible, $targetBlank, $nextSort]);
    }

    // Rebuild cache
    $menuSlug = $pdo->query("SELECT slug FROM navigation_menus WHERE id = $menuId")->fetchColumn();
    nav_manager_rebuild_cache($menuSlug);

    echo json_encode(['success' => true]);
    return true;
});

// Delete an item
HookRegistry::addFilter('admin_ajax_nav_manager_delete_item', function ($handled) {
    global $pdo;
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        return true;
    }

    $id = (int)($_POST['id'] ?? 0);
    if (!$id) return true;

    // Get menu ID before deleting for cache rebuild
    $menuId = $pdo->query("SELECT menu_id FROM navigation_menu_items WHERE id = $id")->fetchColumn();
    
    // Set children to parent's parent (or NULL) to avoid orphans
    $parent = $pdo->query("SELECT parent_id FROM navigation_menu_items WHERE id = $id")->fetchColumn();
    $pdo->prepare("UPDATE navigation_menu_items SET parent_id = ? WHERE parent_id = ?")->execute([$parent, $id]);

    $pdo->prepare("DELETE FROM navigation_menu_items WHERE id = ?")->execute([$id]);

    if ($menuId) {
        $menuSlug = $pdo->query("SELECT slug FROM navigation_menus WHERE id = $menuId")->fetchColumn();
        nav_manager_rebuild_cache($menuSlug);
    }

    echo json_encode(['success' => true]);
    return true;
});

// Save Order (Automatic from SortableJS)
HookRegistry::addFilter('admin_ajax_nav_manager_save_order', function ($handled) {
    global $pdo;
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        return true;
    }

    $menuId = (int)($_POST['menu_id'] ?? 0);
    $order = $_POST['order'] ?? []; // Expected format: items with id and parent_id

    if (!$menuId || !is_array($order)) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return true;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE navigation_menu_items SET sort_order = ?, parent_id = ? WHERE id = ? AND menu_id = ?");
        foreach ($order as $index => $item) {
            $parentId = ($item['parent_id'] > 0) ? (int)$item['parent_id'] : null;
            $stmt->execute([$index, $parentId, (int)$item['id'], $menuId]);
        }
        $pdo->commit();

        $menuSlug = $pdo->query("SELECT slug FROM navigation_menus WHERE id = $menuId")->fetchColumn();
        nav_manager_rebuild_cache($menuSlug);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    return true;
});
