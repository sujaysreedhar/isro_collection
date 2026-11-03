<?php
// Public downloads expose image bytes only, after checking the parent item's visibility.
if (!defined('SITE_URL')) { http_response_code(404); exit; }
global $pdo, $storage;
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$mediaId = (int)($_GET['media_id'] ?? 0);
$stmt = $pdo->prepare("SELECT m.id, m.file_path FROM media m JOIN items i ON i.id = m.item_id WHERE m.id = ? AND m.media_type = 'image' AND i.is_visible = 1");
$stmt->execute([$mediaId]);
$media = $stmt->fetch(PDO::FETCH_ASSOC);
$path = $media ? MediaProcessor::originalStoragePath($media['file_path'], $storage ?? null) : null;
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if (!$path) { http_response_code(404); echo 'Original image is unavailable.'; return; }
if (($storage ?? null) instanceof S3Storage) {
    header('Location: ' . $storage->downloadUrl($path, basename($path)), true, 302);
    return;
}
$uploadRoot = realpath(ABSPATH . '/uploads');
$localPath = realpath(ABSPATH . '/uploads/' . $path);
if (!$uploadRoot || !$localPath || !str_starts_with($localPath, $uploadRoot . DIRECTORY_SEPARATOR) || !is_file($localPath)) {
    http_response_code(404); return;
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($localPath);
if (!in_array($mime, MediaProcessor::ALLOWED_IMG, true)) { http_response_code(404); return; }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($localPath));
$downloadName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($path));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
session_write_close();
readfile($localPath);
