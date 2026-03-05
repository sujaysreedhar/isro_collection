<?php
// config.php

// Composer autoloader (required for AWS SDK)
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Application settings
define('SITE_URL', 'http://localhost/collection'); // Set to your base URL without trailing slash
define('SITE_TITLE', 'Pictorial Cancellation Collection');
define('HOME_PAGE_TITLE', 'Home - Pictorial  Cancellation Collection');

$host = 'localhost';
$db   = 'eish'; // Change to your actual database name
$user = 'root';      // Default XAMPP user
$pass = '';          // Default XAMPP password is empty
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// ── Storage Backend ─────────────────────────────────────────────────────────
require_once __DIR__ . '/../LocalStorage.php';
require_once __DIR__ . '/../S3Storage.php';

/**
 * Load all settings into an associative array.
 */
function loadSettings(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (\PDOException $e) {
        // settings table might not exist yet — return defaults
        return ['storage_driver' => 'local'];
    }
}

$appSettings = loadSettings($pdo);
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
?>