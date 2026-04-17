<?php
/*
Module Name: Theme Studio
Description: Visual customizer for the Custom theme — configure colors, fonts, home page layout and hero image.
Version: 1.0
Author: System
*/

if (!class_exists('HookRegistry')) return;

// ── Default settings seeded on activation ────────────────────────────────────
$THEME_STUDIO_DEFAULTS = [
    'theme_studio_color_primary'    => '#111827',
    'theme_studio_color_accent'     => '#2563eb',
    'theme_studio_color_accent_dark'=> '#1d4ed8',
    'theme_studio_color_bg'         => '#f9fafb',
    'theme_studio_color_hero_bg'    => '#ffffff',
    'theme_studio_color_surface'    => '#ffffff',
    'theme_studio_color_border'     => '#e5e7eb',
    'theme_studio_color_text'       => '#374151',
    'theme_studio_color_text_muted' => '#6b7280',
    'theme_studio_color_footer_bg'  => '#111827',
    'theme_studio_color_footer_text'=> '#9ca3af',
    'theme_studio_font_body'        => 'Inter',
    'theme_studio_font_heading'     => 'Playfair Display',
    'theme_studio_border_radius'    => '0.5rem',
    'theme_studio_hero_style'       => 'split',
    'theme_studio_hero_title'       => '',
    'theme_studio_hero_text_color'  => '',
    'theme_studio_hero_tagline_color' => '',
    'theme_studio_hero_accent_color'  => '',
    'theme_studio_hero_overlay_color'   => '#ffffff',
    'theme_studio_hero_overlay_opacity' => '75',
    'theme_studio_grid_cols'        => '3',
    'theme_studio_show_search'      => '1',
    'theme_studio_show_stats'       => '0',
    'theme_studio_featured_count'   => '6',
    'theme_studio_hero_tagline'     => '',
    'theme_studio_hero_image'       => '',
    'theme_studio_footer_text'      => '',
];

// ── Activation: seed defaults + switch theme ──────────────────────────────────
HookRegistry::addAction('activate_theme_studio', function () use ($THEME_STUDIO_DEFAULTS) {
    global $pdo;
    if (!$pdo) return;

    $upsert = $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = IF(setting_value = '' OR setting_value IS NULL, :v2, setting_value)"
    );
    foreach ($THEME_STUDIO_DEFAULTS as $key => $val) {
        $upsert->execute([':k' => $key, ':v' => $val, ':v2' => $val]);
    }

    // Switch active theme to custom
    $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_value) VALUES ('active_theme','custom')
         ON DUPLICATE KEY UPDATE setting_value = 'custom'"
    )->execute();
});

// ── Deactivation: revert to default theme ────────────────────────────────────
HookRegistry::addAction('deactivate_theme_studio', function () {
    global $pdo;
    if (!$pdo) return;
    $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_value) VALUES ('active_theme','default')
         ON DUPLICATE KEY UPDATE setting_value = 'default'"
    )->execute();
});

// ── Admin menu ────────────────────────────────────────────────────────────────
HookRegistry::addFilter('admin_sidebar_links', function ($sections) {
    $sections['system']['links']['theme_studio'] = [
        'url'   => SITE_URL . '/admin/module_page.php?m=theme_studio',
        'label' => 'Theme Studio',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122l9.156-9.156a1.5 1.5 0 112.122 2.122l-9.156 9.156a1.5 1.5 0 11-2.122-2.122z" /><path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a1.5 1.5 0 10-2.122 2.122 1.5 1.5 0 002.122-2.122z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.674 11.729c.224-.224.53-.35.849-.35h2.122a1.5 1.5 0 110 3h-2.122a1.5 1.5 0 01-1.061-.439L12.333 12.5M15 15l-3-3M15 11l-3 3" />'
    ];
    return $sections;
});

// ── Admin page content ────────────────────────────────────────────────────────
HookRegistry::addAction('admin_page_theme_studio', function () {
    $dir = __DIR__;
    if (file_exists($dir . '/admin.php')) {
        include $dir . '/admin.php';
    }
});

// ── Frontend: inject CSS custom properties ────────────────────────────────────
HookRegistry::addAction('frontend_head', function () {
    global $appSettings;
    if (($appSettings['active_theme'] ?? 'default') !== 'custom') return;

    $s = static fn(string $k, string $d) => htmlspecialchars($appSettings[$k] ?? $d, ENT_QUOTES, 'UTF-8');

    $bodyFont    = $s('theme_studio_font_body',    'Inter');
    $headingFont = $s('theme_studio_font_heading', 'Playfair Display');

    // Build Google Fonts URL from chosen fonts
    $fontsToLoad = array_unique([$bodyFont, $headingFont]);
    $fontParam   = implode('&family=', array_map(fn($f) => urlencode($f) . ':wght@300;400;500;600;700', $fontsToLoad));
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= $fontParam ?>&display=swap">
    <style>
    :root {
        --color-primary:     <?= $s('theme_studio_color_primary',    '#111827') ?>;
        --color-accent:      <?= $s('theme_studio_color_accent',      '#2563eb') ?>;
        --color-accent-dark: <?= $s('theme_studio_color_accent_dark', '#1d4ed8') ?>;
        --color-bg:          <?= $s('theme_studio_color_bg',          '#f9fafb') ?>;
        --color-hero-bg:     <?= $s('theme_studio_color_hero_bg',     '#ffffff') ?>;
        --color-surface:     <?= $s('theme_studio_color_surface',     '#ffffff') ?>;
        --color-border:      <?= $s('theme_studio_color_border',      '#e5e7eb') ?>;
        --color-text:        <?= $s('theme_studio_color_text',        '#374151') ?>;
        --color-text-muted:  <?= $s('theme_studio_color_text_muted',  '#6b7280') ?>;
        --color-footer-bg:   <?= $s('theme_studio_color_footer_bg',   '#111827') ?>;
        --color-footer-text: <?= $s('theme_studio_color_footer_text', '#9ca3af') ?>;
        --font-body:         '<?= $bodyFont ?>', sans-serif;
        --font-heading:      '<?= $headingFont ?>', serif;
        --border-radius:     <?= $s('theme_studio_border_radius', '0.5rem') ?>;
    }
    body { background-color: var(--color-bg); }
    </style>
    <?php
});

// ── AJAX Handlers ────────────────────────────────────────────────────────────
HookRegistry::addFilter('admin_ajax_theme_studio_save', function ($handled) {
    global $pdo;
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
        return true;
    }
    $allowed = [
        'theme_studio_color_primary', 'theme_studio_color_accent', 'theme_studio_color_accent_dark',
        'theme_studio_color_bg', 'theme_studio_color_hero_bg', 'theme_studio_color_surface',
        'theme_studio_color_border', 'theme_studio_color_text', 'theme_studio_color_text_muted',
        'theme_studio_color_footer_bg', 'theme_studio_color_footer_text', 'theme_studio_font_body',
        'theme_studio_font_heading', 'theme_studio_border_radius', 'theme_studio_hero_style',
        'theme_studio_hero_title', 'theme_studio_hero_text_color', 'theme_studio_hero_tagline_color',
        'theme_studio_hero_accent_color', 'theme_studio_hero_overlay_color', 'theme_studio_hero_overlay_opacity',
        'theme_studio_grid_cols', 'theme_studio_show_search', 'theme_studio_show_stats',
        'theme_studio_featured_count', 'theme_studio_hero_tagline', 'theme_studio_hero_image',
        'theme_studio_footer_text', 'route_planner_google_maps_key',
    ];
    $settings = $_POST['settings'] ?? [];
    if (!is_array($settings)) {
        echo json_encode(['success' => false, 'error' => 'Bad payload']);
        return true;
    }
    $upsert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
    foreach ($settings as $key => $val) {
        if (!in_array($key, $allowed, true)) continue;
        $val = trim((string) $val);
        $upsert->execute([$key, $val, $val]);
    }
    echo json_encode(['success' => true]);
    return true;
});

HookRegistry::addFilter('admin_ajax_theme_studio_upload_hero', function ($handled) {
    global $pdo;
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
        return true;
    }
    $brandingDir = dirname(__DIR__, 2) . '/uploads/branding';
    if (!is_dir($brandingDir)) mkdir($brandingDir, 0755, true);

    if (!isset($_FILES['hero_image_file']) || $_FILES['hero_image_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No file or upload error.']);
        return true;
    }
    $tmp = $_FILES['hero_image_file']['tmp_name'];
    $orig = $_FILES['hero_image_file']['name'];
    $mime = mime_content_type($tmp);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'], true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type.']);
        return true;
    }
    if ($_FILES['hero_image_file']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'File too large (max 5 MB).']);
        return true;
    }
    $current = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='theme_studio_hero_image'")->fetchColumn();
    if ($current && file_exists($brandingDir . '/' . $current)) @unlink($brandingDir . '/' . $current);

    $newName = 'hero_' . time() . '.webp';
    if (!MediaProcessor::optimizeImage($tmp, $brandingDir . '/' . $newName, 2000, 2000, 80)) {
        echo json_encode(['success' => false, 'error' => 'Failed to optimize hero image.']);
        return true;
    }
    $pdo->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('theme_studio_hero_image',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$newName, $newName]);
    echo json_encode(['success' => true, 'filename' => $newName, 'url' => SITE_URL . '/uploads/branding/' . rawurlencode($newName)]);
    return true;
});

HookRegistry::addFilter('admin_ajax_theme_studio_remove_hero', function ($handled) {
    global $pdo;
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
        return true;
    }
    $brandingDir = dirname(__DIR__, 2) . '/uploads/branding';
    $current = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='theme_studio_hero_image'")->fetchColumn();
    if ($current && file_exists($brandingDir . '/' . $current)) @unlink($brandingDir . '/' . $current);

    $pdo->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('theme_studio_hero_image','') ON DUPLICATE KEY UPDATE setting_value=''")->execute();
    echo json_encode(['success' => true]);
    return true;
});
