<?php
// bootstrap/storage.php
// ── Stage 3: Storage Backend ──────────────────────────────────────────────────
// Resolves the active storage driver (local filesystem or S3) and assigns $storage.
// All media read/write operations should go through $storage, never direct paths.
// Prerequisites: $appSettings and SITE_URL must already be defined.

require_once __DIR__ . '/../includes/LocalStorage.php';
require_once __DIR__ . '/../includes/S3Storage.php';

$storageDriver = $appSettings['storage_driver'] ?? 'local';

if ($storageDriver === 's3'
    && !empty($appSettings['s3_bucket'])
    && !empty($appSettings['s3_access_key'])
    && !empty($appSettings['s3_secret_key'])
) {
    $storage = new S3Storage([
        'bucket'      => $appSettings['s3_bucket'],
        'region'      => $appSettings['s3_region'] ?? 'us-east-1',
        'access_key'  => $appSettings['s3_access_key'],
        'secret_key'  => $appSettings['s3_secret_key'],
        'endpoint'    => $appSettings['s3_endpoint'] ?? '',
        'path_prefix' => $appSettings['s3_path_prefix'] ?? 'collection/uploads',
    ]);
} else {
    $uploadBase = realpath(__DIR__ . '/../uploads');
    if (!$uploadBase) {
        @mkdir(__DIR__ . '/../uploads', 0755, true);
        $uploadBase = realpath(__DIR__ . '/../uploads');
    }
    $storage = new LocalStorage($uploadBase, SITE_URL . '/uploads');
}
