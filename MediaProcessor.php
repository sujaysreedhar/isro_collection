<?php

/**
 * MediaProcessor — Production-grade image pipeline using GD Library.
 *
 * Generates three variants of every upload:
 *   • /uploads/thumbs/    — 200px wide WebP thumbnail (smart-cropped square)
 *   • /uploads/display/   — 1200px wide WebP for detail view
 *   • /uploads/original/  — Untouched original file preserved as-is
 *
 * Usage inside edit_item.php:
 *   require_once __DIR__ . '/../MediaProcessor.php';
 *   $mp = new MediaProcessor($pdo);
 *   $result = $mp->process($_FILES['media_upload'], $itemId, $caption, $license, $isPrimary);
 *   if (!$result['success']) { echo $result['message']; }
 */
class MediaProcessor {

    private PDO $db;
    private string $uploadRoot;

    const MAX_BYTES    = 5 * 1024 * 1024; // 5 MB
    const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    // [folder => max-width (null = keep original)]
    const VARIANTS = [
        'thumbs'   => 200,
        'display'  => 1200,
        'original' => null,
    ];

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->uploadRoot = realpath(__DIR__ . '/uploads') . DIRECTORY_SEPARATOR;

        foreach (array_keys(self::VARIANTS) as $dir) {
            $path = $this->uploadRoot . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /**
     * Main entry point: validate → process → store.
     */
    public function process(
        array  $file,
        int    $itemId,
        string $caption   = '',
        string $license   = 'Public Domain',
        bool   $isPrimary = false
    ): array {

        // ── Validation ──────────────────────────────────────────────────
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => $this->phpUploadError($file['error'])];
        }

        if ($file['size'] > self::MAX_BYTES) {
            return ['success' => false, 'message' => 'File exceeds the 5 MB maximum.'];
        }

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
            return ['success' => false, 'message' => 'Unsupported file type. Upload JPG, PNG, GIF, or WebP.'];
        }

        // ── Load source image ────────────────────────────────────────────
        $src = $this->gdLoad($file['tmp_name'], $mimeType);
        if (!$src) {
            return ['success' => false, 'message' => 'Could not decode image data — file may be corrupt.'];
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        // ── Generate a collision-safe base name (always stored as .webp) ─
        $baseName = sprintf('img_%d_%s', $itemId, bin2hex(random_bytes(8)));

        // ── Save original (untouched bytes, keep source extension) ───────
        $srcExt      = $this->mimeExt($mimeType);
        $originalFile = $this->uploadRoot . 'original' . DIRECTORY_SEPARATOR . $baseName . '.' . $srcExt;
        move_uploaded_file($file['tmp_name'], $originalFile);

        // ── Generate & save WebP variants ────────────────────────────────
        $webpBase = $baseName . '.webp';  // shared filename for thumbs + display
        $this->saveVariant($src, $origW, $origH, 'thumbs',  $webpBase, 200,  200,  true);
        $this->saveVariant($src, $origW, $origH, 'display', $webpBase, 1200, null, false);
        imagedestroy($src);

        // ── Database ─────────────────────────────────────────────────────
        try {
            if ($isPrimary) {
                $this->db
                     ->prepare('UPDATE media SET is_primary = 0 WHERE item_id = :id')
                     ->execute([':id' => $itemId]);
            }

            $stmt = $this->db->prepare('
                INSERT INTO media
                    (item_id, file_path, caption, license_type, is_primary, file_size, mime_type, dimensions, upload_date)
                VALUES
                    (:item_id, :fp, :cap, :lic, :primary, :size, :mime, :dims, NOW())
            ');
            $stmt->execute([
                ':item_id' => $itemId,
                ':fp'      => $webpBase,          // webp filename (thumbs/display share the same name)
                ':cap'     => $caption,
                ':lic'     => $license,
                ':primary' => $isPrimary ? 1 : 0,
                ':size'    => $file['size'],
                ':mime'    => 'image/webp',
                ':dims'    => "{$origW}x{$origH}",
            ]);

            return [
                'success'    => true,
                'message'    => 'Image processed and saved in three sizes (thumb, display, original).',
                'file'       => $webpBase,
                'media_id'   => (int) $this->db->lastInsertId(),
                'dimensions' => "{$origW}x{$origH}",
            ];

        } catch (\PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Resize + optionally crop-square, then encode to WebP.
     */
    private function saveVariant(
        $src,
        int    $srcW,
        int    $srcH,
        string $folder,
        string $filename,
        int    $targetW,
        ?int   $targetH,
        bool   $cropSquare
    ): void {
        $path = $this->uploadRoot . $folder . DIRECTORY_SEPARATOR . $filename;

        if ($cropSquare) {
            $img = $this->smartCropSquare($src, $srcW, $srcH, $targetW);
        } else {
            // Proportional downscale — never upscale
            $ratio = min(1, $targetW / $srcW);
            $newW  = (int) round($srcW * $ratio);
            $newH  = (int) round($srcH * $ratio);
            $img   = imagecreatetruecolor($newW, $newH);
            $this->preserveAlpha($img);
            imagecopyresampled($img, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        }

        imagewebp($img, $path, 85);   // WebP at quality 85
        imagedestroy($img);
    }

    /**
     * Scale so the shortest side fills $size, then centre-crop to $size × $size.
     */
    private function smartCropSquare($src, int $srcW, int $srcH, int $size) {
        $ratio  = max($size / $srcW, $size / $srcH);
        $scaledW = (int) round($srcW * $ratio);
        $scaledH = (int) round($srcH * $ratio);

        $tmp = imagecreatetruecolor($scaledW, $scaledH);
        $this->preserveAlpha($tmp);
        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $scaledW, $scaledH, $srcW, $srcH);

        $x   = (int) (($scaledW - $size) / 2);
        $y   = (int) (($scaledH - $size) / 2);
        $dst = imagecreatetruecolor($size, $size);
        $this->preserveAlpha($dst);
        imagecopy($dst, $tmp, 0, 0, $x, $y, $size, $size);
        imagedestroy($tmp);

        return $dst;
    }

    private function preserveAlpha($img): void {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefilledrectangle(
            $img, 0, 0, imagesx($img), imagesy($img),
            imagecolorallocatealpha($img, 255, 255, 255, 127)
        );
    }

    private function gdLoad(string $path, string $mime) {
        return match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/gif'  => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default      => false,
        };
    }

    private function mimeExt(string $mime): string {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
    }

    private function phpUploadError(int $code): string {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the server size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was submitted.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server has no temp directory configured.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the file to disk.',
            default               => "Upload failed (error code {$code}).",
        };
    }

    /**
     * Build a public URL for a stored filename.
     *
     * @param string $filename  Value stored in media.file_path
     * @param string $variant   'thumbs' | 'display' | 'original'
     */
    public static function url(string $filename, string $variant = 'display'): string {
        return SITE_URL . '/uploads/' . $variant . '/' . htmlspecialchars($filename);
    }

    /**
     * System health helper: find files in /uploads/ not referenced by the database.
     * Returns an array of file paths.
     */
    public function orphanedFiles(): array {
        // Fetch all known file_path values from the database
        $known = $this->db->query("SELECT file_path FROM media")->fetchAll(\PDO::FETCH_COLUMN);
        $knownSet = array_flip($known);

        $orphans = [];
        foreach (['thumbs', 'display', 'original'] as $dir) {
            $folder = $this->uploadRoot . $dir . DIRECTORY_SEPARATOR;
            foreach (glob($folder . '*.*') ?: [] as $file) {
                $basename = basename($file);
                if (!isset($knownSet[$basename])) {
                    $orphans[] = $dir . '/' . $basename;
                }
            }
        }
        return $orphans;
    }
}
