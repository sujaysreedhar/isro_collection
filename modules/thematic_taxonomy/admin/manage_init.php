<?php

$action = $_GET['action'] ?? 'list';
$themeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_theme'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }

    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $parentId = (int)($_POST['parent_id'] ?? 0);
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isPublic = isset($_POST['is_public']) ? 1 : 0;

    if ($name === '') {
        header('Location: ' . SITE_URL . '/admin/module_page.php?m=' . $this->slug . '&action=' . ($themeId > 0 ? 'edit&id=' . $themeId : 'edit') . '&msg=missing_name');
        exit;
    }

    if ($slug === '') {
        $slug = $this->slugify($name);
    } else {
        $slug = $this->slugify($slug);
    }

    if ($slug === '') {
        header('Location: ' . SITE_URL . '/admin/module_page.php?m=' . $this->slug . '&action=' . ($themeId > 0 ? 'edit&id=' . $themeId : 'edit') . '&msg=invalid_slug');
        exit;
    }

    if ($parentId <= 0) {
        $parentId = null;
    }

    if ($parentId !== null && $themeId > 0 && $parentId === $themeId) {
        header('Location: ' . SITE_URL . '/admin/module_page.php?m=' . $this->slug . '&action=edit&id=' . $themeId . '&msg=self_parent');
        exit;
    }

    if ($themeId > 0 && $this->wouldCreateCycle($themeId, $parentId)) {
        header('Location: ' . SITE_URL . '/admin/module_page.php?m=' . $this->slug . '&action=edit&id=' . $themeId . '&msg=cycle');
        exit;
    }

    if (!$this->isThemeSlugAvailable($slug, $themeId)) {
        header('Location: ' . SITE_URL . '/admin/module_page.php?m=' . $this->slug . '&action=' . ($themeId > 0 ? 'edit&id=' . $themeId : 'edit') . '&msg=slug_taken');
        exit;
    }

    if ($themeId > 0) {
        $stmt = $this->pdo->prepare("
            UPDATE module_themes
            SET parent_id = ?, name = ?, slug = ?, description = ?, sort_order = ?, is_public = ?
            WHERE id = ?
        ");
        $stmt->execute([$parentId, $name, $slug, $description, $sortOrder, $isPublic, $themeId]);
    } else {
        $stmt = $this->pdo->prepare("
            INSERT INTO module_themes (parent_id, name, slug, description, sort_order, is_public)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$parentId, $name, $slug, $description, $sortOrder, $isPublic]);
        $themeId = (int)$this->pdo->lastInsertId();
    }

    header('Location: ' . SITE_URL . '/admin/module_page.php?m=' . $this->slug . '&action=edit&id=' . $themeId . '&msg=saved');
    exit;
}

if ($action === 'delete' && $themeId > 0) {
    if (!verifyCsrfToken($_GET['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }

    $this->deleteTheme($themeId);
    header('Location: ' . SITE_URL . '/admin/module_page.php?m=' . $this->slug . '&msg=deleted');
    exit;
}
