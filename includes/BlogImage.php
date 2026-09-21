<?php
declare(strict_types=1);

final class BlogImage {
    public static function path(string $storedPath, int $width): string {
        if (in_array($width, [200, 640, 1200], true)
            && preg_match('#^thumbnails/blog_[a-f0-9]{32}_1200\.webp$#', $storedPath)) {
            return substr($storedPath, 0, -10) . '_' . $width . '.webp';
        }
        return $storedPath;
    }

    public static function url(string $storedPath, int $width, ?StorageInterface $storage = null): string {
        $path = self::path($storedPath, $width);
        return $storage ? $storage->url($path) : SITE_URL . '/uploads/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
