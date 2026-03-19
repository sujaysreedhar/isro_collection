<?php
// modules/media_manager/admin_media.php

if (!defined('SITE_URL')) {
    exit;
}

global $pdo;
require_once __DIR__ . '/../../config/config.php';

$originalsDir = __DIR__ . '/../../uploads/original/';
$thumbsDir = __DIR__ . '/../../uploads/thumbnails/';

if (!is_dir($thumbsDir)) {
    mkdir($thumbsDir, 0777, true);
}

// Handle Processing
$message = '';
if (isset($_POST['regenerate'])) {
    $stmt = $pdo->query("SELECT file_path FROM media WHERE media_type = 'image'");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $generated = 0;
    $errors = [];
    foreach ($files as $file) {
        $fileName = $file['file_path'];
        $source = $originalsDir . $fileName;
        $dest = $thumbsDir . $fileName;
        
        // If the file extension in DB is different from disk, try both
        if (!file_exists($source)) {
            $pathInfo = pathinfo($fileName);
            $altExts = ['webp', 'jpg', 'jpeg', 'png'];
            foreach ($altExts as $ext) {
                $altPath = $originalsDir . $pathInfo['filename'] . '.' . $ext;
                if (file_exists($altPath)) {
                    $source = $altPath;
                    break;
                }
            }
        }

        if (file_exists($source) && !file_exists($dest)) {
            $info = @getimagesize($source);
            if ($info) {
                list($width, $height) = $info;
                $mime = $info['mime'];
                
                $newWidth = 300;
                $newHeight = floor($height * ($newWidth / $width));
                
                $thumb = imagecreatetruecolor($newWidth, $newHeight);
                
                // Handle alpha transparency for PNG and WebP
                if ($mime == 'image/png' || $mime == 'image/webp') {
                    imagealphablending($thumb, false);
                    imagesavealpha($thumb, true);
                    $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
                    imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
                }

                $srcImg = null;
                switch ($mime) {
                    case 'image/jpeg': $srcImg = @imagecreatefromjpeg($source); break;
                    case 'image/png': $srcImg = @imagecreatefrompng($source); break;
                    case 'image/webp': $srcImg = @imagecreatefromwebp($source); break;
                }
                
                if ($srcImg) {
                    imagecopyresampled($thumb, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    
                    // Save as original extension or fallback to jpg
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    if ($ext == 'webp') {
                        imagewebp($thumb, $dest, 85);
                    } elseif ($ext == 'png') {
                        imagepng($thumb, $dest);
                    } else {
                        imagejpeg($thumb, $dest, 85);
                    }
                    
                    imagedestroy($thumb);
                    imagedestroy($srcImg);
                    $generated++;
                } else {
                    $errors[] = "Failed to load image: $fileName (Mime: $mime)";
                }
            } else {
                $errors[] = "Could not get image info for: $fileName";
            }
        }
    }
    $message = "Successfully generated $generated thumbnails.";
    if (!empty($errors)) {
        $message .= "\nErrors:\n" . implode("\n", $errors);
    }
}

// Scan for issues
$stmt = $pdo->query("SELECT m.*, i.title FROM media m JOIN items i ON m.item_id = i.id WHERE m.media_type = 'image'");
$allMedia = $stmt->fetchAll(PDO::FETCH_ASSOC);

$issues = [];
foreach ($allMedia as $item) {
    $fileName = $item['file_path'];
    $originalPath = $originalsDir . $fileName;
    $originalExists = file_exists($originalPath);
    
    // Check alternatives if main path fails
    if (!$originalExists) {
        $pathInfo = pathinfo($fileName);
        $altExts = ['webp', 'jpg', 'jpeg', 'png'];
        foreach ($altExts as $ext) {
            $altPath = $originalsDir . $pathInfo['filename'] . '.' . $ext;
            if (file_exists($altPath)) {
                $originalExists = true;
                break;
            }
        }
    }

    $thumbExists = file_exists($thumbsDir . $fileName);
    
    if (!$originalExists || !$thumbExists) {
        $issues[] = [
            'title' => $item['title'],
            'file' => $fileName,
            'original' => $originalExists,
            'thumb' => $thumbExists
        ];
    }
}
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Media Manager Audit</h1>
        <form method="POST">
            <button type="submit" name="regenerate" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors">
                Generate Missing Thumbnails
            </button>
        </form>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Filename</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($issues)): ?>
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-center text-gray-500">No media issues detected. All files and thumbnails are present.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($issues as $issue): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($issue['title']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($issue['file']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $issue['original'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    Original: <?= $issue['original'] ? 'PASS' : 'MISSING' ?>
                                </span>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $issue['thumb'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> ml-2">
                                    Thumb: <?= $issue['thumb'] ? 'PASS' : 'MISSING' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
