<?php
// modules/theme_studio/ajax_save.php — CSRF-checked settings save endpoint
if (!defined('SITE_URL')) exit;
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

global $pdo;

// Allowed setting keys — prevents arbitrary DB writes
$allowed = [
    'theme_studio_color_primary', 'theme_studio_color_accent', 'theme_studio_color_accent_dark',
    'theme_studio_color_bg', 'theme_studio_color_hero_bg',
    'theme_studio_color_surface', 'theme_studio_color_border',
    'theme_studio_color_text', 'theme_studio_color_text_muted',
    'theme_studio_color_footer_bg', 'theme_studio_color_footer_text',
    'theme_studio_font_body', 'theme_studio_font_heading',
    'theme_studio_border_radius',
    'theme_studio_hero_style', 'theme_studio_grid_cols',
    'theme_studio_show_search', 'theme_studio_show_stats',
    'theme_studio_featured_count', 'theme_studio_hero_tagline',
    'theme_studio_hero_image',
];

$action = $_POST['action'] ?? 'save_settings';

// ── Hero image upload ─────────────────────────────────────────────────────────
if ($action === 'upload_hero_image') {
    $brandingDir = __DIR__ . '/../../uploads/branding';
    if (!is_dir($brandingDir)) mkdir($brandingDir, 0755, true);

    if (!isset($_FILES['hero_image_file']) || $_FILES['hero_image_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
        exit;
    }

    $tmp  = $_FILES['hero_image_file']['tmp_name'];
    $orig = $_FILES['hero_image_file']['name'];
    $mime = mime_content_type($tmp);
    $allowed_mimes = ['image/jpeg','image/png','image/webp','image/gif','image/svg+xml'];

    if (!in_array($mime, $allowed_mimes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Use JPG, PNG, WebP or GIF.']);
        exit;
    }
    if ($_FILES['hero_image_file']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'File too large. Max 5 MB.']);
        exit;
    }

    // Remove old hero image
    $current = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='theme_studio_hero_image'")->fetchColumn();
    if ($current && file_exists($brandingDir . '/' . $current)) @unlink($brandingDir . '/' . $current);

    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION)) ?: 'jpg';
    $newName = 'hero_' . time() . '.' . $ext;

    if (!move_uploaded_file($tmp, $brandingDir . '/' . $newName)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save file. Check directory permissions.']);
        exit;
    }

    $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('theme_studio_hero_image',?)
                   ON DUPLICATE KEY UPDATE setting_value=?")->execute([$newName, $newName]);

    echo json_encode([
        'success'  => true,
        'filename' => $newName,
        'url'      => SITE_URL . '/uploads/branding/' . rawurlencode($newName),
    ]);
    exit;
}

// ── Remove hero image ─────────────────────────────────────────────────────────
if ($action === 'remove_hero_image') {
    $brandingDir = __DIR__ . '/../../uploads/branding';
    $current = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='theme_studio_hero_image'")->fetchColumn();
    if ($current && file_exists($brandingDir . '/' . $current)) @unlink($brandingDir . '/' . $current);
    $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('theme_studio_hero_image','')
                   ON DUPLICATE KEY UPDATE setting_value=''")->execute();
    echo json_encode(['success' => true]);
    exit;
}

// ── Save settings ─────────────────────────────────────────────────────────────
$settings = $_POST['settings'] ?? [];
if (!is_array($settings)) {
    echo json_encode(['success' => false, 'error' => 'Invalid settings payload']);
    exit;
}

$upsert = $pdo->prepare(
    "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE setting_value = ?"
);

foreach ($settings as $key => $val) {
    if (!in_array($key, $allowed)) continue;
    $val = trim((string)$val);
    $upsert->execute([$key, $val, $val]);
}

echo json_encode(['success' => true]);
