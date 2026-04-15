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
