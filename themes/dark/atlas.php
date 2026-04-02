<?php
// themes/dark/atlas.php
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
if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_header'); }
?>

    <main class="flex-grow flex flex-col items-center justify-center p-4">
        <div class="max-w-7xl mx-auto w-full mb-4">
            <h1 class="text-4xl tracking-tight font-extrabold text-white">Postmark Atlas</h1>
            <p class="mt-2 text-lg text-gray-400">Explore the geographical footprint of our active collection.</p>
        </div>
        <div class="max-w-7xl w-full bg-gray-800 rounded-lg shadow-lg border border-gray-700 overflow-hidden flex-grow relative" style="min-height: 60vh;">
            <div id="frontendAtlasMap" class="absolute inset-0"></div>
        </div>
    </main>

<?php require_once ThemeManager::getFooter(); ?>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var map = L.map('frontendAtlasMap').setView([20.5937, 78.9629], 5);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
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

        var rawData = <?= $jsonLocations ?>;
        rawData.forEach(function(loc) {
            if (loc.latitude && loc.longitude) {
                var isAcquired = parseInt(loc.is_acquired, 10) === 1;
                var markerTitle = loc.post_office + ' - ' + loc.district;
                var statusHtml = isAcquired ?
                    '<span style="background: #064e3b; color: #6ee7b7; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 11px;">In Collection</span>' :
                    '<span style="background: #1f2937; color: #9ca3af; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 11px;">Not Acquired</span>';
                var popupHtml = '<div style="font-family: Inter, sans-serif;">' +
                    '<h3 style="font-size:14px; font-weight:bold; margin:0 0 4px 0;">' + loc.post_office + '</h3>' +
                    '<p style="margin: 0 0 6px 0; color: #6b7280; font-size:12px;">' + loc.district + ', ' + loc.state + ' - ' + loc.pin_code + '</p>' +
                    statusHtml + '</div>';
                var iconToUse = isAcquired ? greenIcon : greyIcon;
                L.marker([parseFloat(loc.latitude), parseFloat(loc.longitude)], {icon: iconToUse})
                    .bindPopup(popupHtml).bindTooltip(markerTitle).addTo(map);
            }
        });
    });
    </script>
