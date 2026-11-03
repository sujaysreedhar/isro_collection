<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/PeoplePublic.php';
global $pdo, $activeModulesSlugs, $storage;
if (!in_array('people', (array)$activeModulesSlugs, true)) {
    http_response_code(404); require ThemeManager::getTemplatePath('404.php'); return;
}
$slug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
if ($slug === '') { header('Location: ' . SITE_URL . '/people.php'); return; }
$person = PeoplePublic::profile($pdo, $slug);
if (!$person) { http_response_code(404); require ThemeManager::getTemplatePath('404.php'); return; }
$relatedItems = PeoplePublic::records($pdo, (int)$person['id']);
$facts = PeoplePublic::facts($person);
$paragraphs = PeoplePublic::paragraphs((string)($person['biography'] ?? ''));
$pageTitle = $person['name'] . ' - Biography - ' . SITE_TITLE;
$ogTitle = $person['name'] . ' - Biography';
$ogDescription = trim((string)$person['short_description']) ?: 'Explore this biography and its related collection records.';
$ogUrl = SITE_URL . '/person/' . rawurlencode($person['slug']);
$ogImage = PeopleSupport::imageUrl($person['profile_image']);
$currentMenu = 'people';
$bodyClass = 'people-public-body' . (ThemeManager::getActiveTheme() === 'custom' ? ' archive-ui' : '');
PeoplePublic::assets();
require ThemeManager::getHeader();
require __DIR__ . '/views/profile.php';
require ThemeManager::getFooter();
