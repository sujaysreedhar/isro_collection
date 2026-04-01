<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/MediaProcessor.php';
require_once __DIR__ . '/../../includes/ThemeManager.php';

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Fetch media items with pagination
$stmt = $pdo->prepare("
    SELECT m.file_path, m.media_type, m.youtube_url, i.id as item_id, i.title, i.reg_number
    FROM media m
    INNER JOIN items i ON m.item_id = i.id
    WHERE i.is_visible = 1 AND m.media_type IN ('image', 'youtube')
    ORDER BY m.upload_date DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$mediaItems = $stmt->fetchAll();

// If AJAX request, return only the items partial
if (isset($_GET['ajax'])) {
    if (empty($mediaItems)) {
        exit; // No more items
    }
    require ThemeManager::getTemplatePath('partials/gallery_items.php');
    exit;
}

require_once ThemeManager::getTemplatePath('gallery.php');
