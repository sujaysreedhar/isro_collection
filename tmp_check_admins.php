<?php
require_once 'config/config.php';
$stmt = $pdo->query('SELECT username FROM admins');
while ($row = $stmt->fetch()) {
    echo $row['username'] . PHP_EOL;
}
