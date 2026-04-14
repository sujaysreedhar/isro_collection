<?php
// themes/glass/atlas.php
$pageTitle = 'Postmark Atlas - ' . SITE_TITLE;
$currentMenu = 'atlas';

ob_start();
?>
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<?php
$additionalHead = ob_get_clean();

require_once ThemeManager::getHeader();

if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_header'); }
?>

    <main class="flex-grow flex flex-col p-4 sm:p-6 lg:p-8 relative z-10">
        <div class="max-w-[1600px] mx-auto w-full mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
            <div>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight drop-shadow-md">Geo Atlas</h1>
                <p class="mt-2 text-lg text-slate-300">Visualize our curated geographic collection footprint.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex gap-4 text-xs font-semibold bg-white/5 backdrop-blur-xl p-3 rounded-xl border border-white/10 shadow-[0_4px_16px_rgba(0,0,0,0.2)]">
                    <div class="flex items-center gap-2">
                        <img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png" class="h-6" alt="Green Marker">
                        <span class="text-slate-200">Collected</span>
                    </div>
                    <div class="flex items-center gap-2 pl-3 border-l border-white/10">
                        <img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-grey.png" class="h-6" alt="Grey Marker">
                        <span class="text-slate-400">Seeking</span>
                    </div>
                </div>
                <button id="btn-locate" onclick="locateMe()" title="Show my current location on the map"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl border transition-all bg-white/10 backdrop-blur-xl text-blue-300 border-blue-400/40 hover:bg-blue-500/20 shadow-[0_4px_16px_rgba(0,0,0,0.2)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v2m0 16v2M2 12h2m16 0h2"/></svg>
                    Locate Me
                </button>
            </div>
        </div>
        
        <div class="max-w-[1600px] mx-auto w-full bg-white/5 backdrop-blur-md rounded-3xl shadow-[0_8px_32px_rgba(0,0,0,0.3)] border border-white/10 overflow-hidden flex-grow relative" style="min-height: 70vh;">
            <div id="frontendAtlasMap" class="absolute inset-0 z-0"></div>
        </div>
    </main>

<?php require_once ThemeManager::getFooter(); ?>

    <!-- Leaflet JS -->
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
                    '<span style="background: rgba(34,197,94,0.2); color: #86efac; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; display: inline-block; margin-top: 4px; border: 1px solid rgba(34,197,94,0.3);">✓ Collected</span>' : 
                    '<span style="background: rgba(255,255,255,0.05); color: #94a3b8; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; display: inline-block; margin-top: 4px; border: 1px solid rgba(255,255,255,0.1);">Seeking</span>';

                var popupHtml = '<div style="font-family: \'Plus Jakarta Sans\', sans-serif; padding: 4px;">' +
                                '<h3 style="font-size:15px; font-weight:800; margin:0 0 4px 0; color: #f8fafc;">' + loc.post_office + '</h3>' +
                                '<p style="margin: 0 0 8px 0; color: #94a3b8; font-size:13px;">' + loc.district + ', ' + loc.state + ' - ' + loc.pin_code + '</p>' +
                                statusHtml +
                                '</div>';

                var iconToUse = isAcquired ? greenIcon : greyIcon;
                
                L.marker([parseFloat(loc.latitude), parseFloat(loc.longitude)], {icon: iconToUse})
                 .bindPopup(popupHtml, {className: 'glass-popup'})
                 .bindTooltip(markerTitle)
                 .addTo(map);
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
                '.atlas-loc-dot { width:14px; height:14px; border-radius:50%; background:#60a5fa; border:2.5px solid rgba(15,23,42,0.8); box-shadow:0 0 0 2px #60a5fa55; position:relative; }',
                '.atlas-loc-dot::after { content:""; position:absolute; inset:-3px; border-radius:50%; background:#60a5fa44; animation:atlasLocPulse 2s ease-out infinite; }',
                '.glass-popup .leaflet-popup-content-wrapper { border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.5); border:1px solid rgba(255,255,255,0.1); background:rgba(15,23,42,0.85); backdrop-filter:blur(12px); color:#f8fafc; }',
                '.glass-popup .leaflet-popup-tip { background:rgba(15,23,42,0.85); box-shadow:0 10px 15px rgba(0,0,0,0.3); }'
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
                    userAccuracyCircle = L.circle([lat, lng], { radius: acc, color: '#60a5fa', fillColor: '#60a5fa', fillOpacity: 0.08, weight: 1.5 }).addTo(map);
                    userLocationMarker = L.marker([lat, lng], { icon: blueDotIcon, zIndexOffset: 1000 })
                        .addTo(map)
                        .bindPopup(
                            '<div style="font-family:\'Plus Jakarta Sans\',sans-serif;text-align:center;padding:4px;">'
                            + '<strong style="font-size:13px;">&#x1F4CD; You are here</strong>'
                            + '<br><span style="font-size:11px;color:#94a3b8;">' + lat.toFixed(5) + ', ' + lng.toFixed(5) + '</span>'
                            + '<br><span style="font-size:10px;color:#64748b;">Accuracy &plusmn;' + Math.round(acc) + ' m</span></div>',
                            { maxWidth: 210, className: 'glass-popup' }
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
