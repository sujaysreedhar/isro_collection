<?php
require_once 'config/config.php';
require_once 'includes/BaseModule.php';
require_once 'modules/watermarker/module.php';

// Mock Metadata
$meta = json_decode(file_get_contents('modules/watermarker/module.json'), true);

$module = new WatermarkerModule($pdo, 'watermarker', $meta);
$module->boot();

echo "Module Booted Successully.\n";

// Test Settings
echo "Default Enabled: " . $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'watermark_enabled'")->fetchColumn() . "\n";

// Simulate POST to save settings
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['save_watermark'] = '1';
$_POST['watermark_enabled'] = '1';
$_POST['watermark_text'] = 'Test Watermark';
$_POST['watermark_opacity'] = '30';
$_POST['csrf_token'] = ensureCsrfToken();

ob_start();
$module->renderSettingsPage();
$output = ob_get_clean();

if (strpos($output, 'Settings updated successfully!') !== false) {
    echo "Settings saved successfully via renderSettingsPage.\n";
} else {
    echo "Failed to save settings.\n";
    echo $output;
}

echo "New Enabled: " . $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'watermark_enabled'")->fetchColumn() . "\n";
echo "New Text: " . $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'watermark_text'")->fetchColumn() . "\n";
?>
