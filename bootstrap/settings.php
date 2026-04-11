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

// ── Schema Feature Detection (run once, used everywhere) ─────────────────────
// Replaces per-page SHOW COLUMNS queries that were running 5+ times per request.
$hasIsPrimary = false;
try {
    $columnStmt = $pdo->query("SHOW COLUMNS FROM media LIKE 'is_primary'");
    $hasIsPrimary = (bool) $columnStmt->fetch();
} catch (\PDOException $e) {}
AppConfig::set('media_has_is_primary', $hasIsPrimary ? '1' : '0');

// ── Cached Year Range (for search facets) ────────────────────────────────────
// These rarely change and were queried on every search request.
$enableCache = ($appSettings['enable_cache'] ?? '1') === '1';
$yearCacheFile = CACHE_DIR . '/year_range.json';
$yearRange = null;
if ($enableCache && file_exists($yearCacheFile)) {
    $yearRange = json_decode(file_get_contents($yearCacheFile), true);
}
if (!$yearRange) {
    try {
        $yearRange = [
            'min' => $pdo->query("SELECT MIN(year_start) FROM items WHERE year_start IS NOT NULL")->fetchColumn() ?: null,
            'max' => $pdo->query("SELECT MAX(year_end) FROM items WHERE year_end IS NOT NULL")->fetchColumn() ?: null,
        ];
        if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);
        file_put_contents($yearCacheFile, json_encode($yearRange));
    } catch (\PDOException $e) {
        $yearRange = ['min' => null, 'max' => null];
    }
}
AppConfig::set('year_range_min', $yearRange['min']);
AppConfig::set('year_range_max', $yearRange['max']);

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
