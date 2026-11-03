<?php
/** Shared validation and image paths for the biographies module. */
final class PeopleSupport
{
    public static function validate(array $input): array
    {
        $values = [];
        $errors = [];
        foreach (['name' => 255, 'slug' => 255, 'birth_date' => 100, 'death_date' => 100, 'short_description' => 500, 'biography' => 200000] as $key => $limit) {
            $values[$key] = is_string($input[$key] ?? null) ? trim($input[$key]) : '';
            $length = function_exists('mb_strlen') ? mb_strlen($values[$key]) : strlen($values[$key]);
            if ($length > $limit) $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' must be ' . $limit . ' characters or fewer.';
        }
        if ($values['name'] === '') $errors[] = 'Enter the person\'s name.';
        if (!preg_match('/^[a-zA-Z0-9]+(?:[-_][a-zA-Z0-9]+)*$/D', $values['slug'])) $errors[] = 'Use letters, numbers, hyphens or underscores for the URL slug.';
        $values['is_public'] = isset($input['is_public']) ? 1 : 0;
        $infobox = [];
        $keys = is_array($input['info_key'] ?? null) ? $input['info_key'] : [];
        $vals = is_array($input['info_val'] ?? null) ? $input['info_val'] : [];
        if (count($keys) > 50) $errors[] = 'Use no more than 50 infobox facts.';
        foreach (array_slice($keys, 0, 50, true) as $i => $key) {
            $key = is_string($key) ? trim($key) : '';
            $value = is_string($vals[$i] ?? null) ? trim($vals[$i]) : '';
            if (strlen($key) > 200 || strlen($value) > 2000) $errors[] = 'Infobox labels must be at most 200 bytes and values at most 2,000 bytes.';
            if ($key !== '') $infobox[] = ['label' => $key, 'value' => $value];
        }
        $values['infobox_data'] = json_encode($infobox, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return [$values, array_values(array_unique($errors))];
    }

    public static function imageUrl(?string $filename, bool $original = false): string
    {
        if (!$filename) return '';
        if (preg_match('#^original/(person_[a-f0-9]+)\.(jpg|png|gif|webp)$#D', $filename, $matches)) {
            $path = $original ? $filename : 'thumbnails/' . $matches[1] . '.webp';
        } elseif (preg_match('/^[a-zA-Z0-9_.-]+$/D', $filename) && !str_contains($filename, '..')) {
            // Existing profile photos remain in their historical storage location.
            $path = 'display/' . $filename;
        } else {
            return '';
        }
        $storage = $GLOBALS['storage'] ?? null;
        return $storage instanceof StorageInterface ? $storage->url($path) : SITE_URL . '/uploads/' . $path;
    }

    public static function storeImage(array $file): string
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) throw new RuntimeException('Profile photos require GD with WebP support on the server.');
        require_once __DIR__ . '/../../includes/ImageUploadValidator.php';
        require_once __DIR__ . '/../../includes/MediaProcessor.php';
        require_once __DIR__ . '/../../includes/LocalStorage.php';
        $info = ImageUploadValidator::uploaded($file);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$info['mime']];
        $stem = 'person_' . bin2hex(random_bytes(12));
        $originalPath = 'original/' . $stem . '.' . $ext;
        $previewPath = 'thumbnails/' . $stem . '.webp';
        $preview = tempnam(sys_get_temp_dir(), 'people_');
        if ($preview === false) throw new RuntimeException('Unable to prepare the profile photo.');
        $storage = $GLOBALS['storage'] ?? new LocalStorage(__DIR__ . '/../../uploads', SITE_URL . '/uploads');
        try {
            if (!MediaProcessor::optimizeImage($file['tmp_name'], $preview, 1000, 1000, 90, false)) throw new RuntimeException('The profile photo could not be processed. Check that GD with WebP support is enabled.');
            if (!$storage->put($originalPath, $file['tmp_name'], $info['mime']) || !$storage->put($previewPath, $preview, 'image/webp')) {
                $storage->delete($originalPath);
                $storage->delete($previewPath);
                throw new RuntimeException('The profile photo could not be saved. Please try again.');
            }
            return $originalPath;
        } finally {
            @unlink($preview);
        }
    }

    public static function discardImage(?string $filename): void
    {
        if (!$filename || !preg_match('#^original/(person_[a-f0-9]+)\.(jpg|png|gif|webp)$#D', $filename, $matches)) return;
        require_once __DIR__ . '/../../includes/LocalStorage.php';
        $storage = $GLOBALS['storage'] ?? new LocalStorage(__DIR__ . '/../../uploads', SITE_URL . '/uploads');
        $storage->delete($filename);
        $storage->delete('thumbnails/' . $matches[1] . '.webp');
    }
}
