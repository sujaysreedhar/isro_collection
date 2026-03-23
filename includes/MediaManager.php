<?php

/**
 * MediaManager - Handles secure image upload, validation, and multi-size GD processing.
 * 
 * Usage:
 *   $mm = new MediaManager($pdo);
 *   $result = $mm->upload($_FILES['media_upload'], $item_id, $caption, $license, $isPrimary);
 */
class MediaManager {
    
    private PDO $db;
    private string $baseDir;
    
    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    // Target dimensions for each derived size
    const SIZES = [
        'thumbs'  => [300, 300],  // Square crops for search grids
        'display' => [1200, 900], // Main display, proportional
        'archive' => null,        // Original resolution, just converted
    ];

    public function __construct(PDO $db) {
        $this->db = $db;
        // Base directory is the collection root's uploads folder
        $this->baseDir = realpath(__DIR__ . '/../uploads') . DIRECTORY_SEPARATOR;
        
        // Ensure all subdirectories exist
        foreach (array_keys(self::SIZES) as $folder) {
            $path = $this->baseDir . $folder;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /**
     * Main public entry point. Accepts $_FILES element, validates, processes, and stores.
     */
    public function upload(array $file, int $itemId, string $caption = '', string $license = 'Public Domain', bool $isPrimary = false): array {
        // --- Validation ---
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => $this->uploadErrorMessage($file['error'])];
        }
        
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File exceeds the 5MB maximum size.'];
        }
        
        // Validate by MIME type directly (more secure than extension check alone)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, self::ALLOWED_TYPES, true)) {
            return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.'];
        }
        
        // --- Generate a unique base filename ---
        $ext = $this->mimeToExt($mimeType);
        $baseName = uniqid('img_' . $itemId . '_', true) . '.' . $ext;
        
        // --- Load source image using GD ---
        $srcImage = $this->loadGdImage($file['tmp_name'], $mimeType);
        if ($srcImage === false) {
            return ['success' => false, 'message' => 'Could not read image data. The file may be corrupt.'];
        }
        
        // Capture original dimensions
        $origWidth  = imagesx($srcImage);
        $origHeight = imagesy($srcImage);
        
        // --- Process and save each size ---
        $archivePath = $this->baseDir . 'archive' . DIRECTORY_SEPARATOR . $baseName;
        $thumbPath   = $this->baseDir . 'thumbs'  . DIRECTORY_SEPARATOR . $baseName;
        $displayPath = $this->baseDir . 'display' . DIRECTORY_SEPARATOR . $baseName;
        
        // Archive: save original at maximum quality
        $this->saveGdImage($srcImage, $archivePath, $mimeType, 95);
        
        // Display: proportionally resize to fit within 1200x900
        $displayImg = $this->resizeProportional($srcImage, self::SIZES['display'][0], self::SIZES['display'][1]);
        $this->saveGdImage($displayImg, $displayPath, $mimeType, 88);
        imagedestroy($displayImg);
        
        // Thumb: smart crop to 300x300 square
        $thumbImg = $this->cropSquare($srcImage, self::SIZES['thumbs'][0]);
        $this->saveGdImage($thumbImg, $thumbPath, $mimeType, 82);
        imagedestroy($thumbImg);
        
        imagedestroy($srcImage);
        
        // --- Database Operations ---
        try {
            if ($isPrimary) {
                // Unset any existing primary for this item
                $this->db->prepare("UPDATE media SET is_primary = 0 WHERE item_id = :id")
                         ->execute([':id' => $itemId]);
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO media (item_id, file_path, caption, license_type, is_primary, file_size, dimensions, upload_date)
                VALUES (:item_id, :filename, :caption, :license, :primary, :size, :dims, NOW())
            ");
            $stmt->execute([
                ':item_id'  => $itemId,
                ':filename' => $baseName,  // Only the base filename, as per requirement
                ':caption'  => $caption,
                ':license'  => $license,
                ':primary'  => $isPrimary ? 1 : 0,
                ':size'     => $file['size'],
                ':dims'     => "{$origWidth}x{$origHeight}",
            ]);
            
            return [
                'success'   => true,
                'message'   => 'Image uploaded and processed successfully.',
                'file_name' => $baseName,
                'media_id'  => (int)$this->db->lastInsertId(),
                'dimensions'=> "{$origWidth}x{$origHeight}",
            ];
            
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // Proportionally fit image within $maxW x $maxH box
    private function resizeProportional($src, int $maxW, int $maxH) {
        $w = imagesx($src);
        $h = imagesy($src);
        
        $ratio = min($maxW / $w, $maxH / $h, 1); // Never upscale
        $newW = (int)round($w * $ratio);
        $newH = (int)round($h * $ratio);
        
        $dst = imagecreatetruecolor($newW, $newH);
        $this->preserveTransparency($dst);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        
        return $dst;
    }
    
    // Crop to a centered square of $size x $size
    private function cropSquare($src, int $size) {
        $w = imagesx($src);
        $h = imagesy($src);
        
        // Scale so the shorter side fills $size
        $ratio = max($size / $w, $size / $h);
        $scaledW = (int)round($w * $ratio);
        $scaledH = (int)round($h * $ratio);
        
        $tmp = imagecreatetruecolor($scaledW, $scaledH);
        $this->preserveTransparency($tmp);
        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $scaledW, $scaledH, $w, $h);
        
        // Center crop
        $x = (int)(($scaledW - $size) / 2);
        $y = (int)(($scaledH - $size) / 2);
        
        $dst = imagecreatetruecolor($size, $size);
        $this->preserveTransparency($dst);
        imagecopy($dst, $tmp, 0, 0, $x, $y, $size, $size);
        imagedestroy($tmp);
        
        return $dst;
    }
    
    private function loadGdImage(string $path, string $mimeType) {
        return match($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/gif'  => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default      => false
        };
    }
    
    private function saveGdImage($image, string $path, string $mimeType, int $quality = 85): bool {
        return match($mimeType) {
            'image/jpeg' => imagejpeg($image, $path, $quality),
            'image/png'  => imagepng($image, $path, (int)round((100 - $quality) / 11)),
            'image/gif'  => imagegif($image, $path),
            'image/webp' => imagewebp($image, $path, $quality),
            default      => false
        };
    }
    
    private function preserveTransparency($image): void {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
    }
    
    private function mimeToExt(string $mime): string {
        return match($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'jpg'
        };
    }
    
    private function uploadErrorMessage(int $code): string {
        return match($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the maximum allowed size.',
            UPLOAD_ERR_PARTIAL   => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE   => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            default              => 'Unknown upload error.',
        };
    }
    
    /**
     * Helper to get the public URL for a given filename and size variant.
     * @param string $filename  The base filename stored in the database
     * @param string $size      'thumbs', 'display', or 'archive'
     */
    public static function url(string $filename, string $size = 'display'): string {
        return SITE_URL . '/uploads/' . $size . '/' . htmlspecialchars($filename);
    }
}
