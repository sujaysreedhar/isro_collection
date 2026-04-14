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
            <div class="flex flex-wrap justify-between items-end gap-3">
                <div>
                    <h1 class="text-4xl tracking-tight font-extrabold text-white">Postmark Atlas</h1>
                    <p class="mt-2 text-lg text-gray-400">Explore the geographical footprint of our active collection.</p>
                </div>
                <button id="btn-locate" onclick="locateMe()" title="Show my current location on the map"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-full border transition-colors bg-transparent text-blue-400 border-blue-500 hover:bg-blue-500/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v2m0 16v2M2 12h2m16 0h2"/></svg>
                    Locate Me
                </button>
            </div>
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

        // ── Current Location ─────────────────────────────────────────────────
        var userLocationMarker = null;
        var userAccuracyCircle = null;

        (function () {
            var style = document.createElement('style');
            style.textContent = [
                '@keyframes atlasLocPulse {',
                '  0%   { transform:scale(1);   opacity:1; }',
                '  70%  { transform:scale(2.8); opacity:0; }',
                '  100% { transform:scale(1);   opacity:0; }',
                '}',
                '.atlas-loc-dot { width:14px; height:14px; border-radius:50%; background:#3b82f6; border:2.5px solid #1f2937; box-shadow:0 0 0 2px #3b82f655; position:relative; }',
                '.atlas-loc-dot::after { content:""; position:absolute; inset:-3px; border-radius:50%; background:#3b82f644; animation:atlasLocPulse 2s ease-out infinite; }'
            ].join('\n');
            document.head.appendChild(style);
        }());

        var blueDotIcon = L.divIcon({
            className: '',
            html: '<div class="atlas-loc-dot"></div>',
            iconSize: [14, 14], iconAnchor: [7, 7], popupAnchor: [0, -10]
        });

        window.locateMe = function () {
            var btn = document.getElementById('btn-locate');
            if (!navigator.geolocation) { alert('Geolocation is not supported by your browser.'); return; }
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="30 60"/></svg> Locating…';
            btn.disabled = true;
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    var lat = pos.coords.latitude, lng = pos.coords.longitude, acc = pos.coords.accuracy;
                    if (userLocationMarker) map.removeLayer(userLocationMarker);
                    if (userAccuracyCircle) map.removeLayer(userAccuracyCircle);
                    userAccuracyCircle = L.circle([lat, lng], { radius: acc, color: '#3b82f6', fillColor: '#3b82f6', fillOpacity: 0.08, weight: 1.5 }).addTo(map);
                    userLocationMarker = L.marker([lat, lng], { icon: blueDotIcon, zIndexOffset: 1000 })
                        .addTo(map)
                        .bindPopup(
                            '<div style="font-family:Inter,sans-serif;text-align:center;background:#1f2937;color:#f9fafb;border-radius:8px;padding:4px;">'
                            + '<strong style="font-size:13px;">&#x1F4CD; You are here</strong>'
                            + '<br><span style="font-size:11px;color:#9ca3af;">' + lat.toFixed(5) + ', ' + lng.toFixed(5) + '</span>'
                            + '<br><span style="font-size:10px;color:#6b7280;">Accuracy &plusmn;' + Math.round(acc) + ' m</span></div>',
                            { maxWidth: 210 }
                        ).openPopup();
                    map.flyTo([lat, lng], Math.max(map.getZoom(), 12), { duration: 1.2 });
                    btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v2m0 16v2M2 12h2m16 0h2"/></svg> Locate Me';
                    btn.disabled = false;
                },
                function (err) {
                    var msgs = { 1: 'Location access denied.', 2: 'Location unavailable.', 3: 'Request timed out.' };
                    alert(msgs[err.code] || 'Could not get location: ' + err.message);
                    btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v2m0 16v2M2 12h2m16 0h2"/></svg> Locate Me';
                    btn.disabled = false;
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        };
    });
    </script>
