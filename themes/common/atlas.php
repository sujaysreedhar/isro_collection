<?php
// Shared public Atlas; each theme keeps its own header, footer, and palette.
$pageTitle = 'Postmark Atlas - ' . SITE_TITLE;
$currentMenu = 'atlas';
$atlasDark = in_array(ThemeManager::getActiveTheme(), ['dark', 'glass'], true);
ob_start();
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="<?= htmlspecialchars(SITE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/css/atlas_public.css">
<?php
$additionalHead = ob_get_clean();
require_once ThemeManager::getHeader();
// Default/custom headers dispatch this themselves; other bundled themes do not.
if (class_exists('HookRegistry') && in_array(ThemeManager::getActiveTheme(), ['dark', 'glass', 'modern_blue'], true)) {
    HookRegistry::doAction('frontend_header');
}
?>
<main id="archive-main" class="public-atlas<?= $atlasDark ? ' atlas-dark' : '' ?>">
    <div class="atlas-heading">
        <div><p class="atlas-eyebrow">Explore the collection</p><h1>Postmark Atlas</h1>
        <p>Discover pictorial cancellations, find post offices, and explore the places behind the collection.</p></div>
        <a class="atlas-button" href="<?= htmlspecialchars(SITE_URL, ENT_QUOTES, 'UTF-8') ?>/route-planner.php">Plan a route &rarr;</a>
    </div>
    <section class="atlas-toolbar" aria-label="Find postmarks">
        <div id="atlas-search-wrapper" class="atlas-search">
            <label for="atlas-search-input">Search locations or items</label>
            <div class="atlas-search-field">
                <input id="atlas-search-input" type="search" placeholder="Post office, PPC, PIN, district, item..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="atlas-search-dropdown" aria-expanded="false">
                <button type="button" id="atlas-search-clear" aria-label="Clear search" hidden>&times;</button>
            </div>
            <div id="atlas-search-dropdown" role="listbox" aria-label="Matching locations" hidden></div>
        </div>
        <div class="atlas-field"><label for="atlas-status">Collection status</label><select id="atlas-status">
            <option value="all">All locations</option><option value="collected">Collected</option><option value="seeking">Seeking</option>
        </select></div>
        <div class="atlas-field"><label for="atlas-state">State</label><select id="atlas-state"><option value="">All states</option></select></div>
        <button type="button" id="atlas-show-all" class="atlas-button">Show all locations</button>
        <button type="button" id="btn-locate" class="atlas-button" aria-pressed="false">Locate me</button>
    </section>
    <div class="atlas-map-meta">
        <p id="atlas-summary" role="status" aria-live="polite">Loading locations&hellip;</p>
        <div class="atlas-legend"><span><i class="atlas-legend-dot collected"></i>Collected</span><span><i class="atlas-legend-dot seeking"></i>Seeking</span></div>
    </div>
    <p id="atlas-notice" class="atlas-notice" role="status" hidden></p>
    <div id="frontendAtlasMap" role="region" aria-label="Postmark locations map"></div>
    <p class="atlas-map-hint">Select a numbered group to zoom in. Select a marker to see its postmark and collection details.</p>
    <noscript><p>Enable JavaScript to explore the interactive map.</p></noscript>
</main>
<script type="application/json" id="atlas-data"><?= json_encode(['locations' => $locations, 'siteUrl' => SITE_URL], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="<?= htmlspecialchars(SITE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/atlas_text.js"></script>
<script src="<?= htmlspecialchars(SITE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/atlas_public.js"></script>
<?php require_once ThemeManager::getFooter(); ?>
