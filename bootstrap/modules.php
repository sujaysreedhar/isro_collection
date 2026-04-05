<?php
// bootstrap/modules.php
// ── Stage 4: Module System ────────────────────────────────────────────────────
// Boots the ModuleManager: discovers active module slugs from settings, loads
// each active module class, and calls boot() so hooks are registered for this
// request.
// Prerequisites: $pdo, $appSettings, SITE_URL, and all includes/ classes loaded.

require_once __DIR__ . '/../includes/frontend.php';

$activeModulesJson  = $appSettings['active_modules'] ?? '[]';
$activeModulesSlugs = json_decode($activeModulesJson, true);
if (!is_array($activeModulesSlugs)) $activeModulesSlugs = [];

$moduleManager = new ModuleManager($pdo, __DIR__ . '/../modules', $activeModulesSlugs);
$moduleManager->bootActiveModules();
