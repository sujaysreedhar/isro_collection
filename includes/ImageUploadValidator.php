<?php
declare(strict_types=1);

final class ImageUploadValidator {
    private const TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    public static function inspect(string $path, bool $requireUpload = true): array {
        if ($requireUpload && !is_uploaded_file($path)) throw new DomainException('Invalid uploaded file.');
        if (!is_file($path) || ($bytes = filesize($path)) === false || $bytes > 20 * 1024 * 1024 || $bytes < 1) {
            throw new DomainException('Image must be 20 MiB or smaller.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $dimensions = @getimagesize($path);
        if (!in_array($mime, self::TYPES, true) || !is_array($dimensions) || ($dimensions['mime'] ?? null) !== $mime) {
            throw new DomainException('Upload a valid JPEG, PNG, GIF, or WebP image.');
        }
        [$width, $height] = $dimensions;
        if ($width < 1 || $height < 1 || $width > 10000 || $height > 10000 || $width * $height > 20000000) {
            throw new DomainException('Image dimensions exceed the safety limit.');
        }
        $configuredLimit = trim((string)ini_get('memory_limit'));
        if ($configuredLimit !== '-1' && preg_match('/^(\d+)([KMG]?)$/i', $configuredLimit, $memory)) {
            $multiplier = match (strtoupper($memory[2])) { 'G' => 1073741824, 'M' => 1048576, 'K' => 1024, default => 1 };
            $limitBytes = (int)$memory[1] * $multiplier;
            if (memory_get_usage(true) + $bytes + ($width * $height * 8) > $limitBytes) {
                throw new DomainException('Image is too large to process safely.');
            }
        }
        $decoder = match ($mime) {
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/gif' => 'imagecreatefromgif',
            'image/webp' => 'imagecreatefromwebp',
        };
        $image = @$decoder($path);
        if (!$image) throw new DomainException('Image could not be decoded.');
        imagedestroy($image);
        return ['mime' => $mime, 'width' => $width, 'height' => $height, 'bytes' => $bytes];
    }

    public static function uploaded(array $file): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)) {
            throw new DomainException('Image upload failed.');
        }
        return self::inspect($file['tmp_name']);
    }
}
