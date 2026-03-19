<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/MediaProcessor.php';

// Fetch all visible images and youtube videos
$stmt = $pdo->prepare("
    SELECT m.file_path, m.media_type, m.youtube_url, i.id as item_id, i.title, i.reg_number
    FROM media m
    INNER JOIN items i ON m.item_id = i.id
    WHERE i.is_visible = 1 AND m.media_type IN ('image', 'youtube')
    ORDER BY m.upload_date DESC
");
$stmt->execute();
$mediaItems = $stmt->fetchAll();
?>
<?php require_once ThemeManager::getTemplatePath('gallery.php'); ?>
