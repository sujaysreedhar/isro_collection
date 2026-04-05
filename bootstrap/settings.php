<?php
// bootstrap/settings.php
// ── Stage 2: Application Settings ────────────────────────────────────────────
// Loads all key-value settings from the DB into $appSettings,
// registers them in AppConfig, configures debug mode, and defines
// the SITE_URL / SITE_TITLE constants.
// Prerequisites: $pdo must already exist (bootstrap/database.php).

function loadSettings(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (\PDOException $e) {
        // settings table might not exist yet (fresh install) — return safe defaults
        return ['storage_driver' => 'local'];
    }
}

$appSettings = loadSettings($pdo);

// Make settings available to classes without global injection
AppConfig::load($appSettings);

// ── Debug Mode ───────────────────────────────────────────────────────────────
if (($appSettings['debug_mode'] ?? '0') === '1') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// DB values override the hardcoded defaults declared in config.php
define('SITE_URL',   rtrim($appSettings['site_url']   ?? SITE_URL_DEFAULT, '/'));
define('SITE_TITLE', $appSettings['site_title'] ?? SITE_TITLE_DEFAULT);
