<?php
// bootstrap/database.php
// ── Stage 1: Database Connection ─────────────────────────────────────────────
// Creates the $pdo (SafePDO) instance.
// Prerequisites: Autoloader.php already required, DB credentials defined above.

// Optional .env loader for custom environments (Docker, staging, production)
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            [$envKey, $envVal] = explode('=', $line, 2);
            $envKey = trim($envKey);
            $envVal = trim($envVal, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($envKey, $_SERVER) && !array_key_exists($envKey, $_ENV)) {
                putenv("{$envKey}={$envVal}");
                $_ENV[$envKey] = $envVal;
                $_SERVER[$envKey] = $envVal;
            }
        }
    }
}

// Support environment variables with local XAMPP fallbacks
$host    = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
$port    = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? null);
$db      = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'eish');
$user    = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
$pass    = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($_ENV['DB_PASS'] ?? '');
$charset = getenv('DB_CHARSET') ?: ($_ENV['DB_CHARSET'] ?? 'utf8mb4');

$portSuffix = (!empty($port)) ? ";port={$port}" : '';
$dsn = "mysql:host={$host}{$portSuffix};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new SafePDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    require_once __DIR__ . '/../includes/PublicError.php';
    $publicError = PublicError::payload($e);
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
              (strpos($_SERVER['REQUEST_URI'], 'ajax.php') !== false) || 
              (strpos($_SERVER['REQUEST_URI'], 'ajax_search.php') !== false) ||
              (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(503); // Service Unavailable
        echo json_encode($publicError);
        exit;
    }

    // Show pretty error page
    http_response_code(503);
    include __DIR__ . '/../includes/pages/db_error.php';
    exit;
}
