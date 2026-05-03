<?php
// modules/exhibition_planner/admin/manage_init.php

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if (!function_exists('exhibitionPlannerAdminUrl')) {
    function exhibitionPlannerAdminUrl(string $query = ''): string
    {
        $base = SITE_URL . '/admin/module_page.php?m=exhibition_planner';
        return $query === '' ? $base : $base . '&' . $query;
    }
}

if (!function_exists('exhibitionPlannerSlugify')) {
    function exhibitionPlannerSlugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim((string)$value, '-');
    }
}

if (!function_exists('exhibitionPlannerSlugExists')) {
    function exhibitionPlannerSlugExists(PDO $pdo, string $slug, int $excludeId = 0): bool
    {
        $sql = "SELECT COUNT(*) FROM module_exhibition_pages WHERE slug = ?";
        $params = [$slug];

        if ($excludeId > 0) {
            $sql .= " AND id <> ?";
            $params[] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('exhibitionPlannerUniqueSlug')) {
    function exhibitionPlannerUniqueSlug(PDO $pdo, string $preferred, int $excludeId = 0): string
    {
        $base = exhibitionPlannerSlugify($preferred);
        if ($base === '') {
            return '';
        }

        $candidate = $base;
        $suffix = 2;
        while (exhibitionPlannerSlugExists($pdo, $candidate, $excludeId)) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}

if (!function_exists('exhibitionPlannerNextSortOrder')) {
    function exhibitionPlannerNextSortOrder(PDO $pdo, int $pageId): int
    {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM module_exhibition_items WHERE page_id = ?");
        $stmt->execute([$pageId]);
        return (int)$stmt->fetchColumn();
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    die('Invalid CSRF token.');
}

if ($action === 'save') {
    $title = trim($_POST['title'] ?? '');
    $slugInput = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $bannerImage = trim($_POST['banner_image'] ?? '');

    if ($title === '') {
        header('Location: ' . exhibitionPlannerAdminUrl(($id > 0 ? 'action=edit&id=' . $id : 'action=new') . '&msg=missing_title'));
        exit;
    }

    $slugSeed = $slugInput !== '' ? $slugInput : $title;
    $slug = exhibitionPlannerUniqueSlug($this->pdo, $slugSeed, $id);

    if ($slug === '') {
        header('Location: ' . exhibitionPlannerAdminUrl(($id > 0 ? 'action=edit&id=' . $id : 'action=new') . '&msg=invalid_slug'));
        exit;
    }

    if ($id > 0) {
        $stmt = $this->pdo->prepare("
            UPDATE module_exhibition_pages
            SET title = ?, slug = ?, description = ?, banner_image = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $slug, $description, $bannerImage, $id]);
    } else {
        $stmt = $this->pdo->prepare("
            INSERT INTO module_exhibition_pages (title, slug, description, banner_image)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$title, $slug, $description, $bannerImage]);
        $id = (int)$this->pdo->lastInsertId();
    }

    header('Location: ' . exhibitionPlannerAdminUrl('action=edit&id=' . $id . '&msg=saved'));
    exit;
}

if ($id <= 0) {
    header('Location: ' . exhibitionPlannerAdminUrl('msg=invalid_exhibition'));
    exit;
}

if (isset($_POST['delete_exhibition'])) {
    $stmt = $this->pdo->prepare("DELETE FROM module_exhibition_pages WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: ' . exhibitionPlannerAdminUrl('msg=deleted'));
    exit;
}

if (isset($_POST['add_item_id'])) {
    $itemId = (int)($_POST['add_item_id'] ?? 0);
    $annotation = trim($_POST['annotation'] ?? '');
    $sortOrder = trim((string)($_POST['sort_order'] ?? '')) === ''
        ? exhibitionPlannerNextSortOrder($this->pdo, $id)
        : (int)$_POST['sort_order'];

    $itemCheck = $this->pdo->prepare("SELECT id FROM items WHERE id = ? LIMIT 1");
    $itemCheck->execute([$itemId]);
    if (!(int)$itemCheck->fetchColumn()) {
        header('Location: ' . exhibitionPlannerAdminUrl('action=edit&id=' . $id . '&msg=invalid_item'));
        exit;
    }

    $existing = $this->pdo->prepare("SELECT id FROM module_exhibition_items WHERE page_id = ? AND item_id = ? LIMIT 1");
    $existing->execute([$id, $itemId]);
    if ((int)$existing->fetchColumn() > 0) {
        header('Location: ' . exhibitionPlannerAdminUrl('action=edit&id=' . $id . '&msg=duplicate_item'));
        exit;
    }

    $stmt = $this->pdo->prepare("
        INSERT INTO module_exhibition_items (page_id, item_id, sort_order, annotation)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$id, $itemId, $sortOrder, $annotation]);

    header('Location: ' . exhibitionPlannerAdminUrl('action=edit&id=' . $id . '&msg=item_added'));
    exit;
}

if (isset($_POST['update_item_id'])) {
    $itemId = (int)($_POST['update_item_id'] ?? 0);
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $annotation = trim($_POST['annotation'] ?? '');

    $stmt = $this->pdo->prepare("
        UPDATE module_exhibition_items
        SET sort_order = ?, annotation = ?
        WHERE page_id = ? AND item_id = ?
    ");
    $stmt->execute([$sortOrder, $annotation, $id, $itemId]);

    header('Location: ' . exhibitionPlannerAdminUrl('action=edit&id=' . $id . '&msg=item_updated'));
    exit;
}

if (isset($_POST['remove_item_id'])) {
    $itemId = (int)($_POST['remove_item_id'] ?? 0);
    $stmt = $this->pdo->prepare("DELETE FROM module_exhibition_items WHERE page_id = ? AND item_id = ?");
    $stmt->execute([$id, $itemId]);

    header('Location: ' . exhibitionPlannerAdminUrl('action=edit&id=' . $id . '&msg=item_removed'));
    exit;
}

header('Location: ' . exhibitionPlannerAdminUrl('action=edit&id=' . $id));
exit;
