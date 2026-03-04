<?php
/**
 * MediaProcessor — Image / PDF / YouTube media pipeline.
 *
 * Images  → 3 WebP sizes via GD: thumbs (200px), display (1200px), original
 * PDFs    → stored in /uploads/pdfs/ — up to 20 MB
 * YouTube → URL stored in the database, no file needed
 */
class MediaProcessor {

    private PDO    $db;
    private string $uploadRoot;

    const MAX_IMG_BYTES = 5  * 1024 * 1024;   // 5 MB for images
    const MAX_PDF_BYTES = 20 * 1024 * 1024;   // 20 MB for PDFs
    const ALLOWED_IMG   = ['image/jpeg','image/png','image/gif','image/webp'];

    public function __construct(PDO $db) {
        $this->db         = $db;
        $this->uploadRoot = realpath(__DIR__ . '/uploads') . DIRECTORY_SEPARATOR;

        foreach (['thumbs','display','original','pdfs'] as $dir) {
            $path = $this->uploadRoot . $dir;
            if (!is_dir($path)) mkdir($path, 0755, true);
        }
    }

    // ── Public: Image ────────────────────────────────────────────────────────

    public function process(
        array  $file,
        int    $itemId,
        string $caption   = '',
        string $license   = 'Public Domain',
        bool   $isPrimary = false
    ): array {
        if ($file['error'] !== UPLOAD_ERR_OK)
            return ['success' => false, 'message' => $this->uploadError($file['error'])];
        if ($file['size'] > self::MAX_IMG_BYTES)
            return ['success' => false, 'message' => 'File exceeds the 5 MB maximum.'];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_IMG, true))
            return ['success' => false, 'message' => 'Unsupported type. Upload JPG, PNG, GIF, or WebP.'];

        $src = $this->gdLoad($file['tmp_name'], $mime);
        if (!$src) return ['success' => false, 'message' => 'Could not decode image — file may be corrupt.'];

        $origW    = imagesx($src);
        $origH    = imagesy($src);
        $baseName = sprintf('img_%d_%s', $itemId, bin2hex(random_bytes(8)));

        // Save untouched original
        $origPath = $this->uploadRoot . 'original' . DIRECTORY_SEPARATOR . $baseName . '.' . $this->mimeExt($mime);
        move_uploaded_file($file['tmp_name'], $origPath);

        // WebP variants
        $webpName = $baseName . '.webp';
        $this->saveVariant($src, $origW, $origH, 'thumbs',  $webpName, 200,  true);
        $this->saveVariant($src, $origW, $origH, 'display', $webpName, 1200, false);
        imagedestroy($src);

        try {
            if ($isPrimary)
                $this->db->prepare('UPDATE media SET is_primary = 0 WHERE item_id = ?')->execute([$itemId]);

            $this->db->prepare("
                INSERT INTO media (item_id, file_path, caption, license_type, is_primary,
                                   file_size, mime_type, dimensions, media_type, upload_date)
                VALUES (?, ?, ?, ?, ?, ?, 'image/webp', ?, 'image', NOW())
            ")->execute([$itemId, $webpName, $caption, $license, $isPrimary ? 1 : 0, $file['size'], "{$origW}x{$origH}"]);

            return ['success' => true, 'message' => 'Image saved in three sizes (thumbnail, display, original).', 'file' => $webpName];
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => 'DB error: ' . $e->getMessage()];
        }
    }

    // ── Public: PDF ──────────────────────────────────────────────────────────

    public function processPdf(
        array  $file,
        int    $itemId,
        string $caption   = '',
        bool   $isPrimary = false
    ): array {
        if ($file['error'] !== UPLOAD_ERR_OK)
            return ['success' => false, 'message' => $this->uploadError($file['error'])];
        if ($file['size'] > self::MAX_PDF_BYTES)
            return ['success' => false, 'message' => 'PDF exceeds the 20 MB maximum.'];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if ($finfo->file($file['tmp_name']) !== 'application/pdf')
            return ['success' => false, 'message' => 'Only PDF files are accepted here.'];

        $filename = sprintf('pdf_%d_%s.pdf', $itemId, bin2hex(random_bytes(6)));
        $dest     = $this->uploadRoot . 'pdfs' . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest))
            return ['success' => false, 'message' => 'Failed to write PDF to disk.'];

        try {
            $this->db->prepare("
                INSERT INTO media (item_id, file_path, caption, is_primary, file_size,
                                   mime_type, media_type, upload_date)
                VALUES (?, ?, ?, ?, ?, 'application/pdf', 'pdf', NOW())
            ")->execute([$itemId, $filename, $caption, $isPrimary ? 1 : 0, $file['size']]);

            return ['success' => true, 'message' => 'PDF uploaded successfully.', 'file' => $filename];
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => 'DB error: ' . $e->getMessage()];
        }
    }

    // ── Public: YouTube ──────────────────────────────────────────────────────

    public function processYoutube(string $youtubeUrl, int $itemId, string $caption = ''): array {
        $videoId = self::extractYoutubeId($youtubeUrl);
        if (!$videoId)
            return ['success' => false, 'message' => 'Could not parse a valid YouTube video ID from that URL.'];

        try {
            $this->db->prepare("
                INSERT INTO media (item_id, file_path, caption, is_primary, mime_type,
                                   media_type, youtube_url, upload_date)
                VALUES (?, ?, ?, 0, 'video/youtube', 'youtube', ?, NOW())
            ")->execute([$itemId, $videoId, $caption, $youtubeUrl]);

            return [
                'success'   => true,
                'message'   => 'YouTube video linked successfully.',
                'video_id'  => $videoId,
                'embed_url' => "https://www.youtube.com/embed/{$videoId}",
            ];
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => 'DB error: ' . $e->getMessage()];
        }
    }

    // ── Static helpers ───────────────────────────────────────────────────────

    /**
     * Extract an 11-char YouTube video ID from any standard YouTube URL format.
     */
    public static function extractYoutubeId(string $url): ?string {
        preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|v\/))([a-zA-Z0-9_-]{11})/', $url, $m);
        return $m[1] ?? null;
    }

    /**
     * Build public URL for a media row.
     *   image   → /uploads/{variant}/{file_path}
     *   pdf     → /uploads/pdfs/{file_path}
     *   youtube → https://www.youtube.com/embed/{file_path (videoId)}
     */
    public static function url(string $filename, string $variant = 'display', string $mediaType = 'image'): string {
        return match ($mediaType) {
            'pdf'     => SITE_URL . '/uploads/pdfs/'   . htmlspecialchars($filename),
            'youtube' => 'https://www.youtube.com/embed/' . htmlspecialchars($filename),
            default   => SITE_URL . '/uploads/' . $variant . '/' . htmlspecialchars($filename),
        };
    }

    /**
     * Return files in /uploads/ subdirectories that are not referenced in the database.
     */
    public function orphanedFiles(): array {
        $known    = $this->db->query("SELECT file_path FROM media")->fetchAll(\PDO::FETCH_COLUMN);
        $knownSet = array_flip($known);
        $orphans  = [];
        foreach (['thumbs','display','original','pdfs'] as $dir) {
            foreach (glob($this->uploadRoot . $dir . DIRECTORY_SEPARATOR . '*.*') ?: [] as $f) {
                $base = basename($f);
                if (!isset($knownSet[$base])) $orphans[] = "$dir/$base";
            }
        }
        return $orphans;
    }

    // ── Private GD helpers ───────────────────────────────────────────────────

    private function saveVariant($src, int $srcW, int $srcH, string $folder, string $fn, int $targetW, bool $cropSquare): void {
        $path = $this->uploadRoot . $folder . DIRECTORY_SEPARATOR . $fn;
        if ($cropSquare) {
            $img = $this->smartCropSquare($src, $srcW, $srcH, $targetW);
        } else {
            $ratio = min(1, $targetW / $srcW);
            $newW  = (int) round($srcW * $ratio);
            $newH  = (int) round($srcH * $ratio);
            $img   = imagecreatetruecolor($newW, $newH);
            $this->preserveAlpha($img);
            imagecopyresampled($img, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        }
        imagewebp($img, $path, 85);
        imagedestroy($img);
    }

    private function smartCropSquare($src, int $srcW, int $srcH, int $size) {
        $ratio   = max($size / $srcW, $size / $srcH);
        $scaledW = (int) round($srcW * $ratio);
        $scaledH = (int) round($srcH * $ratio);
        $tmp     = imagecreatetruecolor($scaledW, $scaledH);
        $this->preserveAlpha($tmp);
        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $scaledW, $scaledH, $srcW, $srcH);
        $x   = (int)(($scaledW - $size) / 2);
        $y   = (int)(($scaledH - $size) / 2);
        $dst = imagecreatetruecolor($size, $size);
        $this->preserveAlpha($dst);
        imagecopy($dst, $tmp, 0, 0, $x, $y, $size, $size);
        imagedestroy($tmp);
        return $dst;
    }

    private function preserveAlpha($img): void {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefilledrectangle($img, 0, 0, imagesx($img), imagesy($img), imagecolorallocatealpha($img, 255, 255, 255, 127));
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

    private function uploadError(int $code): string {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the server size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was submitted.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server has no temp directory.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write to disk.',
            default               => "Upload failed (error code {$code}).",
        };
    }
}
