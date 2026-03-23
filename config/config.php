<?php
// config.php

// Composer autoloader (required for AWS SDK)
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Application settings
// Application settings (defaults — can be overridden in Admin → Site Settings)
define('SITE_URL_DEFAULT', 'http://localhost/collection');
define('SITE_TITLE_DEFAULT', 'Pictorial Cancellation Collection');

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

// ── Database Safety Extension ───────────────────────────────────────────────
require_once __DIR__ . '/../includes/SafePDO.php';
require_once __DIR__ . '/../includes/ModuleDB.php';

try {
     $pdo = new SafePDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// ── Storage Backend ─────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/LocalStorage.php';
require_once __DIR__ . '/../includes/S3Storage.php';

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

// ── Debug Mode ──────────────────────────────────────────────────────────────
if (($appSettings['debug_mode'] ?? '0') === '1') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Define SITE_URL and SITE_TITLE — DB overrides the hardcoded defaults
define('SITE_URL',  rtrim($appSettings['site_url']   ?? SITE_URL_DEFAULT, '/'));
define('SITE_TITLE', $appSettings['site_title'] ?? SITE_TITLE_DEFAULT);

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

// ── Hook Registry & Modules & Themes ─────────────────────────────────────────
require_once __DIR__ . '/../includes/HookRegistry.php';
require_once __DIR__ . '/../includes/ThemeManager.php';
require_once __DIR__ . '/../includes/BaseModule.php';
require_once __DIR__ . '/../includes/ModuleManager.php';

require_once __DIR__ . '/../includes/frontend.php';

$activeModulesJson = $appSettings['active_modules'] ?? '[]';
$activeModulesSlugs = json_decode($activeModulesJson, true);
if (!is_array($activeModulesSlugs)) $activeModulesSlugs = [];

$moduleManager = new ModuleManager($pdo, __DIR__ . '/../modules', $activeModulesSlugs);
$moduleManager->bootActiveModules();
?>