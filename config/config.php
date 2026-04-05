<?php
// config/config.php — Bootstrap Orchestrator
//
// This file defines static constants and then delegates each
// bootstrap concern to a named stage file in bootstrap/.
// Each stage is independently readable, testable, and debuggable.
//
// Boot order:
//   1. constants  — SITE_URL_DEFAULT, SITE_TITLE_DEFAULT, CACHE_DIR
//   2. autoloader — loads all includes/ classes via spl_autoload
//   3. database   — creates $pdo (SafePDO)
//   4. settings   — loads $appSettings, debug mode, SITE_URL, SITE_TITLE
//   5. storage    — resolves $storage (local or S3)
//   6. modules    — boots ModuleManager + active module hooks
//   7. hooks      — registers core application-level hooks

// ── Static Constants ─────────────────────────────────────────────────────────
// Hard-coded fallback values; DB settings override these at Stage 4.
define('SITE_URL_DEFAULT',   'http://localhost/collection');
define('SITE_TITLE_DEFAULT', 'Pictorial Cancellation Collection');

// Single canonical cache directory — all classes reference this constant.
define('CACHE_DIR', __DIR__ . '/../includes/cache');
define('ABSPATH',   realpath(__DIR__ . '/..'));

// Composer autoloader (required for AWS SDK and any Composer packages)
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// ── Autoloader ───────────────────────────────────────────────────────────────
// Registers spl_autoload for includes/ — loads SafePDO, AppConfig, etc.
require_once __DIR__ . '/../includes/Autoloader.php';

// ── Bootstrap Stages ─────────────────────────────────────────────────────────
require_once __DIR__ . '/../bootstrap/database.php'; // Stage 1: $pdo
require_once __DIR__ . '/../bootstrap/settings.php'; // Stage 2: $appSettings, constants, debug
require_once __DIR__ . '/../bootstrap/storage.php';  // Stage 3: $storage
require_once __DIR__ . '/../bootstrap/modules.php';  // Stage 4: $moduleManager + boot()
require_once __DIR__ . '/../bootstrap/hooks.php';    // Stage 5: core hook registrations