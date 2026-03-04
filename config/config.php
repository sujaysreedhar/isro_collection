<?php
// config.php

// Application settings
define('SITE_URL', 'http://localhost/collection'); // Set to your base URL without trailing slash
define('SITE_TITLE', 'Museum Collection');
define('HOME_PAGE_TITLE', 'Home - Museum Collection');

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
?>