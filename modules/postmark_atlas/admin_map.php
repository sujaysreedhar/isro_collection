<?php
// modules/postmark_atlas/admin_map.php
if (!defined('SITE_URL'))
    exit;

global $pdo;

// Fetch locations with linked item title
$stmt = $pdo->query("
    SELECT pl.*, i.title AS linked_item_title, i.id AS linked_item_id
    FROM postmark_locations pl
    LEFT JOIN items i ON i.id = pl.linked_item_id
");
$locations     = $stmt->fetchAll(PDO::FETCH_ASSOC);
$jsonLocations = json_encode($locations);
$csrfToken     = htmlspecialchars(ensureCsrfToken());

?>

<div class="mb-4 flex flex-wrap justify-between items-center gap-3">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Postmark Atlas</h2>
        <p class="text-sm text-gray-500 mt-0.5">Click a marker to view details or toggle acquired status.</p>
    </div>
    <!-- Filter buttons + Locate Me -->
    <div class="flex items-center gap-2 flex-wrap">
        <button id="btn-all" onclick="setFilter('all')"
                class="map-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors bg-gray-900 text-white border-gray-900">
            All
        </button>
        <button id="btn-collected" onclick="setFilter('collected')"
                class="map-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors bg-white text-green-700 border-green-200 hover:bg-green-50">
            <span class="w-2 h-2 bg-green-500 rounded-full"></span> Collected
        </button>
        <button id="btn-seeking" onclick="setFilter('seeking')"
                class="map-filter-btn inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors bg-white text-gray-500 border-gray-200 hover:bg-gray-50">
            <span class="w-2 h-2 bg-gray-400 rounded-full"></span> Seeking
        </button>
        <button id="btn-locate" onclick="locateMe()" title="Show my current location"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors bg-blue-600 text-white border-blue-600 hover:bg-blue-700">
            📍 Locate Me
        </button>
    </div>
</div>

<!-- Map -->
<div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden p-2">
    <div id="atlasMap" style="height:580px;width:100%;border-radius:6px;"></div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var map        = L.map('atlasMap').setView([20.5937, 78.9629], 5);
    var csrfToken  = '<?= $csrfToken ?>';
    var siteUrl    = '<?= SITE_URL ?>';

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '© OpenStreetMap'
    }).addTo(map);

    var greenIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [13,21], iconAnchor: [6,21], popupAnchor: [0,-21], shadowSize: [21,21]
    });
    var greyIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-grey.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [13,21], iconAnchor: [6,21], popupAnchor: [0,-21], shadowSize: [21,21]
    });

    // Two layer groups for filtering
    var collectedGroup = L.layerGroup().addTo(map);
    var seekingGroup   = L.layerGroup().addTo(map);
    var currentFilter  = 'all';

    var rawData = <?= $jsonLocations ?>;

    rawData.forEach(function (loc) {
        if (!loc.latitude || !loc.longitude) return;

        var isAcq = parseInt(loc.is_acquired, 10) === 1;
        var icon  = isAcq ? greenIcon : greyIcon;

        var marker = L.marker([parseFloat(loc.latitude), parseFloat(loc.longitude)], { icon: icon });

        marker._locData   = loc;
        marker._isAcquired = isAcq;

        marker.on('click', function () {
            var d   = this._locData;
            var acq = this._isAcquired;

            var statusBadge = acq
                ? '<span style="display:inline-block;padding:2px 8px;border-radius:5px;font-size:11px;font-weight:700;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;">✓ Collected</span>'
                : '<span style="display:inline-block;padding:2px 8px;border-radius:5px;font-size:11px;font-weight:700;background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;">Seeking</span>';

            var itemHtml = d.linked_item_title
                ? '<div style="margin:6px 0;"><a href="' + siteUrl + '/item/' + d.linked_item_id + '" target="_blank" '
                  + 'style="font-size:12px;color:#2563eb;text-decoration:underline;">🖼 ' + d.linked_item_title + '</a></div>'
                : '';

            var ppcLine = d.ppc_name
                ? '<p style="margin:0 0 4px 0;font-size:13px;font-weight:600;color:#374151;">' + d.ppc_name + '</p>'
                : '';

            var btnLabel  = acq ? 'Mark as Not Acquired' : 'Mark as Acquired';
            var btnColor  = acq ? '#dc2626' : '#16a34a';
            var btnBg     = acq ? '#fef2f2' : '#f0fdf4';
            var btnBorder = acq ? '#fecaca' : '#bbf7d0';

            var popupHtml =
                '<div style="font-family:Segoe UI,sans-serif;min-width:230px;">' +
                '<h3 style="font-size:14px;font-weight:800;margin:0 0 2px 0;color:#111827;">' + d.post_office + '</h3>' +
                ppcLine +
                '<p style="margin:0 0 6px 0;color:#9ca3af;font-size:12px;">PIN ' + d.pin_code + ' · ' + (d.district||'') + (d.state ? ', '+d.state : '') + '</p>' +
                statusBadge +
                itemHtml +
                '<hr style="border:none;border-top:1px solid #e5e7eb;margin:8px 0;">' +
                '<button onclick="toggleAcquired(' + d.id + ',' + (acq?0:1) + ')" ' +
                'style="width:100%;padding:7px 10px;border-radius:7px;cursor:pointer;font-size:12px;font-weight:700;' +
                'background:' + btnBg + ';color:' + btnColor + ';border:1px solid ' + btnBorder + ';">' +
                btnLabel + '</button>' +
                '</div>';

            this.unbindPopup().bindPopup(popupHtml, { maxWidth: 290 }).openPopup();
        });

        (isAcq ? collectedGroup : seekingGroup).addLayer(marker);
    });

    // Filter toggle
    window.setFilter = function (f) {
        currentFilter = f;

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

        // Update button styles
        document.querySelectorAll('.map-filter-btn').forEach(function (btn) {
            btn.style.background  = '';
            btn.style.color       = '';
            btn.style.borderColor = '';
        });
        var active = document.getElementById('btn-' + f);
        if (active) { active.style.background = '#111827'; active.style.color = '#fff'; active.style.borderColor = '#111827'; }
    };

    // Toggle acquired via AJAX
    window.toggleAcquired = function (id, newStatus) {
        var fd = new FormData();
        fd.append('id', id); fd.append('new_status', newStatus); fd.append('csrf_token', csrfToken);
        fetch(siteUrl + '/modules/postmark_atlas/ajax_toggle.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => { if (data.success) window.location.reload(); else alert('Error: ' + (data.error||'?')); })
            .catch(e  => alert('Network error: ' + e.message));
    };

    // ── Current Location ──────────────────────────────────────────────────────
    var userLocationMarker  = null;
    var userAccuracyCircle  = null;

    // Inject pulse animation CSS once
    (function () {
        var style = document.createElement('style');
        style.textContent = [
            '@keyframes atlasLocationPulse {',
            '  0%   { transform: scale(1);   opacity: 1; }',
            '  70%  { transform: scale(2.8); opacity: 0; }',
            '  100% { transform: scale(1);   opacity: 0; }',
            '}',
            '.atlas-location-dot {',
            '  width: 14px; height: 14px;',
            '  border-radius: 50%;',
            '  background: #2563eb;',
            '  border: 2.5px solid #fff;',
            '  box-shadow: 0 0 0 2px #2563eb55;',
            '  position: relative;',
            '}',
            '.atlas-location-dot::after {',
            '  content: "";',
            '  position: absolute;',
            '  inset: -3px;',
            '  border-radius: 50%;',
            '  background: #2563eb44;',
            '  animation: atlasLocationPulse 2s ease-out infinite;',
            '}'
        ].join('\n');
        document.head.appendChild(style);
    }());

    var blueDotIcon = L.divIcon({
        className: '',
        html: '<div class="atlas-location-dot"></div>',
        iconSize:   [14, 14],
        iconAnchor: [7, 7],
        popupAnchor:[0, -10]
    });

    window.locateMe = function () {
        var btn = document.getElementById('btn-locate');
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser.');
            return;
        }

        btn.textContent = '⏳ Locating…';
        btn.disabled    = true;

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                var lat = pos.coords.latitude;
                var lng = pos.coords.longitude;
                var acc = pos.coords.accuracy; // metres

                // Remove previous location layer if any
                if (userLocationMarker)  { map.removeLayer(userLocationMarker); }
                if (userAccuracyCircle)  { map.removeLayer(userAccuracyCircle); }

                // Accuracy ring
                userAccuracyCircle = L.circle([lat, lng], {
                    radius:      acc,
                    color:       '#2563eb',
                    fillColor:   '#2563eb',
                    fillOpacity: 0.08,
                    weight:      1.5
                }).addTo(map);

                // Blue dot marker
                userLocationMarker = L.marker([lat, lng], { icon: blueDotIcon, zIndexOffset: 1000 })
                    .addTo(map)
                    .bindPopup(
                        '<div style="font-family:Segoe UI,sans-serif;text-align:center;">'
                        + '<strong style="font-size:13px;">📍 You are here</strong>'
                        + '<br><span style="font-size:11px;color:#6b7280;">'
                        + lat.toFixed(5) + ', ' + lng.toFixed(5)
                        + '</span><br><span style="font-size:10px;color:#9ca3af;">Accuracy ±' + Math.round(acc) + ' m</span>'
                        + '</div>',
                        { maxWidth: 200 }
                    )
                    .openPopup();

                map.flyTo([lat, lng], Math.max(map.getZoom(), 12), { duration: 1.2 });

                btn.innerHTML = '📍 Locate Me';
                btn.disabled  = false;
            },
            function (err) {
                var msgs = {
                    1: 'Location access was denied. Please allow location in your browser settings.',
                    2: 'Location unavailable. Check your device\'s GPS or network.',
                    3: 'Location request timed out. Please try again.'
                };
                alert(msgs[err.code] || 'Could not get location: ' + err.message);
                btn.innerHTML = '📍 Locate Me';
                btn.disabled  = false;
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    };
});
</script>