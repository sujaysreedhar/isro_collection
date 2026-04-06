<?php
// bootstrap/database.php
// ── Stage 1: Database Connection ─────────────────────────────────────────────
// Creates the $pdo (SafePDO) instance.
// Prerequisites: Autoloader.php already required, DB credentials defined above.

$host    = 'localhost';
$db      = 'eish';      // Change to your actual database name
$user    = 'root';      // Default XAMPP user
$pass    = '';          // Default XAMPP password is empty
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new SafePDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    $errorMessage = $e->getMessage();
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
              (strpos($_SERVER['REQUEST_URI'], 'ajax.php') !== false) || 
              (strpos($_SERVER['REQUEST_URI'], 'ajax_search.php') !== false) ||
              (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(503); // Service Unavailable
        echo json_encode([
            'success' => false,
            'error' => 'Database connection unavailable.',
            'debug' => $errorMessage
        ]);
        exit;
    }

    // Show pretty error page
    http_response_code(503);
    // Note: DEBUG_MODE might not be defined yet since settings.php runs after this.
    // We can check if a local override exists or just pass the message.
    include __DIR__ . '/../includes/pages/db_error.php';
    exit;
}
