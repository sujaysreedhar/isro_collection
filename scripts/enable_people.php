<?php
require_once __DIR__ . '/config/config.php';
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'active_modules'");
$val = $stmt->fetchColumn();
$active = json_decode($val ?: '[]', true);
if (!in_array('people', $active)) {
    $active[] = 'people';
    $newVal = json_encode($active);
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'active_modules'");
    $stmt->execute([$newVal]);
    echo "Module 'people' enabled.";
} else {
    echo "Module 'people' is already enabled.";
}
