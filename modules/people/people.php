<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/PeoplePublic.php';
global $pdo, $activeModulesSlugs;
if (!in_array('people', (array)$activeModulesSlugs, true)) {
    http_response_code(404); require ThemeManager::getTemplatePath('404.php'); return;
}
$view = PeoplePublic::directory($pdo, $_GET);
$pageTitle = 'People & Biographies - ' . SITE_TITLE;
$ogDescription = 'Discover the lives connected to the stamps, covers, and records in this collection.';
$ogUrl = PeoplePublic::directoryUrl($view);
$currentMenu = 'people';
$bodyClass = 'people-public-body' . (ThemeManager::getActiveTheme() === 'custom' ? ' archive-ui' : '');
PeoplePublic::assets();
require ThemeManager::getHeader();
require __DIR__ . '/views/directory.php';
require ThemeManager::getFooter();
