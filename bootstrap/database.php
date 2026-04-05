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
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
