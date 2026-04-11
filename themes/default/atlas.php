<?php
// themes/default/atlas.php

$pageTitle = 'Postmark Atlas - ' . SITE_TITLE;
$currentMenu = 'atlas';

ob_start();
?>
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_head'); } ?>
<?php
$additionalHead = ob_get_clean();

require_once ThemeManager::getHeader();

// If we need frontend_header hook, we can do it here or inside header.php
if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_header'); }
?>

    <!-- Main Map View -->
    <main class="flex-grow flex flex-col items-center justify-center p-4">
        <div class="max-w-7xl mx-auto w-full mb-4">
            <div class="flex flex-wrap justify-between items-end gap-3">
                <div>
                    <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 serif">Postmark Atlas</h1>
                    <p class="mt-1 text-lg text-gray-600">Explore the geographical footprint of our active collection.</p>
                </div>
                <!-- Filter buttons -->
                <div class="flex items-center gap-2">
                    <button id="btn-all" onclick="setFilter('all')"
                            class="atlas-filter-btn inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-full border transition-colors bg-gray-900 text-white border-gray-900">
                        All
                    </button>
                    <button id="btn-collected" onclick="setFilter('collected')"
                            class="atlas-filter-btn inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-full border transition-colors bg-white text-green-700 border-green-200 hover:bg-green-50">
                        <span class="w-2.5 h-2.5 bg-green-500 rounded-full"></span> Collected
                    </button>
                    <button id="btn-seeking" onclick="setFilter('seeking')"
                            class="atlas-filter-btn inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-full border transition-colors bg-white text-gray-500 border-gray-200 hover:bg-gray-50">
                        <span class="w-2.5 h-2.5 bg-gray-400 rounded-full"></span> Seeking
                    </button>
                    <span class="hidden sm:inline text-gray-200 mx-1">|</span>
                    <a href="<?= SITE_URL ?>/route-planner.php"
                       class="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-full border transition-colors bg-white text-indigo-600 border-indigo-200 hover:bg-indigo-50"
                       title="Plan a collection route between two locations">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        Plan a Route
                    </a>
                </div>
            </div>
        </div>

        <div class="max-w-7xl w-full bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden flex-grow relative" style="min-height: 60vh;">
            <div id="frontendAtlasMap" class="absolute inset-0"></div>
        </div>
    </main>

<?php require_once ThemeManager::getFooter(); ?>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var map = L.map('frontendAtlasMap').setView([20.5937, 78.9629], 5);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            maxZoom: 19
        }).addTo(map);

        var greenIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [13, 21], iconAnchor: [6, 21], popupAnchor: [0, -21], shadowSize: [21, 21]
        });

        var greyIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-grey.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [13, 21], iconAnchor: [6, 21], popupAnchor: [0, -21], shadowSize: [21, 21]
        });

        // Two layer groups for filtering
        var collectedGroup = L.layerGroup().addTo(map);
        var seekingGroup   = L.layerGroup().addTo(map);

        var rawData = <?= $jsonLocations ?>;
        
        rawData.forEach(function(loc) {
            if (loc.latitude && loc.longitude) {
                var isAcquired = parseInt(loc.is_acquired, 10) === 1;
                var markerTitle = (loc.ppc_name || loc.post_office) + ' - ' + loc.district;

                var statusHtml = isAcquired ?
                    '<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-weight:700;font-size:11px;">In Collection</span>' :
                    '<span style="background:#f3f4f6;color:#4b5563;padding:2px 8px;border-radius:4px;font-weight:700;font-size:11px;">Not Acquired</span>';

                var ppcLine = loc.ppc_name
                    ? '<p style="margin:0 0 2px 0;font-size:13px;font-weight:600;color:#374151;">' + loc.ppc_name + '</p>'
                    : '';

                var itemHtml = loc.linked_item_title
                    ? '<div style="margin:6px 0;padding:5px 7px;background:#eff6ff;border-radius:5px;">'
                      + '<a href="' + '<?= SITE_URL ?>' + '/item/' + loc.linked_item_id
                      + '" style="font-size:12px;color:#1d4ed8;text-decoration:none;font-weight:600;">🖼 ' + loc.linked_item_title + '</a>'
                      + '</div>'
                    : '';

                var popupHtml = '<div style="font-family: Inter, sans-serif;min-width:200px;">' +
                                '<h3 style="font-size:14px;font-weight:bold;margin:0 0 2px 0;">' + loc.post_office + '</h3>' +
                                ppcLine +
                                '<p style="margin:0 0 6px 0;color:#6b7280;font-size:12px;">' + (loc.district||'') + ', ' + (loc.state||'') + ' · ' + loc.pin_code + '</p>' +
                                statusHtml +
                                itemHtml +
                                '</div>';

                var iconToUse = isAcquired ? greenIcon : greyIcon;
                var marker = L.marker([parseFloat(loc.latitude), parseFloat(loc.longitude)], {icon: iconToUse})
                    .bindPopup(popupHtml, {maxWidth: 260})
                    .bindTooltip(markerTitle);

                (isAcquired ? collectedGroup : seekingGroup).addLayer(marker);
            }
        });

        // Filter toggle
        window.setFilter = function(f) {
            if (f === 'all') {
                if (!map.hasLayer(collectedGroup)) map.addLayer(collectedGroup);
                if (!map.hasLayer(seekingGroup))   map.addLayer(seekingGroup);
            } else if (f === 'collected') {
                if (!map.hasLayer(collectedGroup)) map.addLayer(collectedGroup);
                if (map.hasLayer(seekingGroup))    map.removeLayer(seekingGroup);
            } else {
                if (map.hasLayer(collectedGroup))  map.removeLayer(collectedGroup);
                if (!map.hasLayer(seekingGroup))   map.addLayer(seekingGroup);
            }
            document.querySelectorAll('.atlas-filter-btn').forEach(function(btn) {
                btn.style.background = ''; btn.style.color = ''; btn.style.borderColor = '';
            });
            var active = document.getElementById('btn-' + f);
            if (active) { active.style.background = '#111827'; active.style.color = '#fff'; active.style.borderColor = '#111827'; }
        };
    });
    </script>
