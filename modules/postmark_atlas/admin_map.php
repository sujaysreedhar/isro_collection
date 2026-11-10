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
    <!-- Search + Filter buttons + Locate Me -->
    <div class="flex items-center gap-2 flex-wrap">
        <!-- Search Input with Autocomplete -->
        <div class="relative w-full sm:w-64 md:w-72" id="atlas-search-wrapper">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input type="text" id="atlas-search-input" placeholder="Search PO, PIN, district..." autocomplete="off"
                       class="w-full pl-8 pr-7 py-1.5 text-xs rounded-full border border-gray-300 focus:border-gray-900 focus:ring-1 focus:ring-gray-900 outline-none bg-white text-gray-900 shadow-sm transition">
                <button id="atlas-search-clear" type="button" class="hidden absolute inset-y-0 right-0 items-center pr-2.5 text-gray-400 hover:text-gray-600" title="Clear search">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <!-- Suggestions Dropdown -->
            <div id="atlas-search-dropdown" class="hidden absolute left-0 right-0 mt-1 max-h-72 overflow-y-auto bg-white rounded-xl shadow-2xl border border-gray-200 z-[1000] text-xs"></div>
        </div>

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
<script src="<?= SITE_URL ?>/assets/js/atlas_text.js"></script>

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
    var currentSearch  = '';
    var selectedResultIndex = -1;

    var rawData = <?= $jsonLocations ?>;

    rawData.forEach(function (loc) {
        if (!loc.latitude || !loc.longitude) return;

        var isAcq = parseInt(loc.is_acquired, 10) === 1;
        var icon  = isAcq ? greenIcon : greyIcon;

        var marker = L.marker([parseFloat(loc.latitude), parseFloat(loc.longitude)], { icon: icon });

        marker._locData    = loc;
        marker._isAcquired = isAcq;
        loc._marker        = marker;
        loc._isAcquired    = isAcq;

        marker.on('click', function () {
            var d   = this._locData;
            var acq = this._isAcquired;

            var statusBadge = acq
                ? '<span style="display:inline-block;padding:2px 8px;border-radius:5px;font-size:11px;font-weight:700;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;">✓ Collected</span>'
                : '<span style="display:inline-block;padding:2px 8px;border-radius:5px;font-size:11px;font-weight:700;background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;">Seeking</span>';

            var itemHtml = d.linked_item_title
                ? '<div style="margin:6px 0;"><a href="' + siteUrl + '/item/' + d.linked_item_id + '" target="_blank" '
                  + 'style="font-size:12px;color:#2563eb;text-decoration:underline;">🖼 ' + AtlasText.escape(d.linked_item_title) + '</a></div>'
                : '';

            var ppcLine = d.ppc_name
                ? '<p style="margin:0 0 4px 0;font-size:13px;font-weight:600;color:#374151;">' + AtlasText.escape(d.ppc_name) + '</p>'
                : '';

            var btnLabel  = acq ? 'Mark as Not Acquired' : 'Mark as Acquired';
            var btnColor  = acq ? '#dc2626' : '#16a34a';
            var btnBg     = acq ? '#fef2f2' : '#f0fdf4';
            var btnBorder = acq ? '#fecaca' : '#bbf7d0';

            var popupHtml =
                '<div style="font-family:Segoe UI,sans-serif;min-width:230px;">' +
                '<h3 style="font-size:14px;font-weight:800;margin:0 0 2px 0;color:#111827;">' + AtlasText.escape(d.post_office) + '</h3>' +
                ppcLine +
                '<p style="margin:0 0 6px 0;color:#9ca3af;font-size:12px;">PIN ' + AtlasText.escape(d.pin_code) + ' · ' + AtlasText.escape(d.district||'') + (d.state ? ', '+AtlasText.escape(d.state) : '') + '</p>' +
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
    });

    function renderMarkers(shouldFitBounds) {
        collectedGroup.clearLayers();
        seekingGroup.clearLayers();

        var query = currentSearch.trim().toLowerCase();
        var bounds = [];

        rawData.forEach(function (loc) {
            if (!loc._marker) return;

            if (currentFilter === 'collected' && !loc._isAcquired) return;
            if (currentFilter === 'seeking' && loc._isAcquired) return;

            if (query) {
                var po   = (loc.post_office || '').toLowerCase();
                var ppc  = (loc.ppc_name || '').toLowerCase();
                var dist = (loc.district || '').toLowerCase();
                var st   = (loc.state || '').toLowerCase();
                var pin  = (loc.pin_code || '').toLowerCase();
                if (!po.includes(query) && !ppc.includes(query) && !dist.includes(query) && !st.includes(query) && !pin.includes(query)) {
                    return;
                }
            }

            if (loc._isAcquired) {
                collectedGroup.addLayer(loc._marker);
            } else {
                seekingGroup.addLayer(loc._marker);
            }
            bounds.push([parseFloat(loc.latitude), parseFloat(loc.longitude)]);
        });

        if (shouldFitBounds && bounds.length > 0) {
            if (bounds.length === 1) {
                map.flyTo(bounds[0], 14, { duration: 1.0 });
            } else {
                map.fitBounds(L.latLngBounds(bounds).pad(0.12), { maxZoom: 14 });
            }
        }
    }

    renderMarkers(false);

    // Filter toggle
    window.setFilter = function (f) {
        currentFilter = f;
        renderMarkers(false);

        // Update button styles
        document.querySelectorAll('.map-filter-btn').forEach(function (btn) {
            btn.style.background  = '';
            btn.style.color       = '';
            btn.style.borderColor = '';
        });
        var active = document.getElementById('btn-' + f);
        if (active) { active.style.background = '#111827'; active.style.color = '#fff'; active.style.borderColor = '#111827'; }
    };

    // ── Search functionality ─────────────────────────────────────────────────
    var searchInput = document.getElementById('atlas-search-input');
    var searchClear = document.getElementById('atlas-search-clear');
    var searchDropdown = document.getElementById('atlas-search-dropdown');
    var searchDebounce = null;

    function getFilteredMatches(query) {
        if (!query) return [];
        var q = query.trim().toLowerCase();
        return rawData.filter(function(loc) {
            if (!loc.latitude || !loc.longitude) return false;
            var po   = (loc.post_office || '').toLowerCase();
            var ppc  = (loc.ppc_name || '').toLowerCase();
            var dist = (loc.district || '').toLowerCase();
            var st   = (loc.state || '').toLowerCase();
            var pin  = (loc.pin_code || '').toLowerCase();
            return po.includes(q) || ppc.includes(q) || dist.includes(q) || st.includes(q) || pin.includes(q);
        });
    }

    function updateDropdown(matches) {
        if (!searchDropdown) return;
        var q = (searchInput.value || '').trim();
        if (!q) {
            searchDropdown.classList.add('hidden');
            searchDropdown.innerHTML = '';
            return;
        }

        if (matches.length === 0) {
            searchDropdown.innerHTML = '<div class="p-3 text-center text-gray-500 text-xs">No matching postmarks found</div>';
            searchDropdown.classList.remove('hidden');
            return;
        }

        var limit = 8;
        var topMatches = matches.slice(0, limit);
        var html = '<ul class="divide-y divide-gray-100">';

        topMatches.forEach(function(loc, idx) {
            var isAcq = loc._isAcquired;
            var badge = isAcq
                ? '<span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-green-100 text-green-800">Collected</span>'
                : '<span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-600">Seeking</span>';

            var ppcText = loc.ppc_name ? ' <span class="text-xs text-gray-500 font-normal">(' + AtlasText.escape(loc.ppc_name) + ')</span>' : '';

            html += '<li class="atlas-search-item px-3 py-2 cursor-pointer hover:bg-gray-50 transition-colors flex items-center justify-between gap-2" data-idx="' + idx + '" onclick="selectLocation(' + loc.id + ')">'
                  + '<div class="min-w-0 flex-grow">'
                  +   '<div class="font-medium text-gray-900 text-xs truncate">' + AtlasText.escape(loc.post_office) + ppcText + '</div>'
                  +   '<div class="text-[11px] text-gray-500 truncate">' + AtlasText.escape(loc.district) + ', ' + AtlasText.escape(loc.state) + ' · ' + AtlasText.escape(loc.pin_code) + '</div>'
                  + '</div>'
                  + '<div class="flex-shrink-0">' + badge + '</div>'
                  + '</li>';
        });

        html += '</ul>';

        if (matches.length > limit) {
            html += '<div class="px-3 py-1.5 bg-gray-50 text-[11px] text-gray-500 text-center border-t border-gray-100">'
                  + 'Showing ' + limit + ' of ' + matches.length + ' matches · Press Enter to view all'
                  + '</div>';
        }

        searchDropdown.innerHTML = html;
        searchDropdown.classList.remove('hidden');
        selectedResultIndex = -1;
    }

    window.selectLocation = function(locId) {
        var loc = rawData.find(function(l) { return l.id == locId; });
        if (!loc || !loc._marker) return;

        if (loc._isAcquired && !map.hasLayer(collectedGroup)) map.addLayer(collectedGroup);
        if (!loc._isAcquired && !map.hasLayer(seekingGroup)) map.addLayer(seekingGroup);
        if (!(loc._isAcquired ? collectedGroup : seekingGroup).hasLayer(loc._marker)) {
            (loc._isAcquired ? collectedGroup : seekingGroup).addLayer(loc._marker);
        }

        map.flyTo([parseFloat(loc.latitude), parseFloat(loc.longitude)], 14, { duration: 1.0 });
        setTimeout(function() {
            loc._marker.fire('click');
        }, 600);

        if (searchInput) {
            searchInput.value = loc.post_office + (loc.ppc_name ? ' (' + loc.ppc_name + ')' : '');
            if (searchClear) searchClear.classList.remove('hidden');
        }
        if (searchDropdown) {
            searchDropdown.classList.add('hidden');
        }
    };

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var val = this.value;
            currentSearch = val;
            if (searchClear) {
                if (val.length > 0) searchClear.classList.remove('hidden');
                else searchClear.classList.add('hidden');
            }
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(function() {
                var matches = getFilteredMatches(val);
                updateDropdown(matches);
                renderMarkers(false);
            }, 150);
        });

        searchInput.addEventListener('keydown', function(e) {
            var items = searchDropdown ? searchDropdown.querySelectorAll('.atlas-search-item') : [];
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (items.length === 0) return;
                selectedResultIndex = (selectedResultIndex + 1) % items.length;
                items.forEach(function(el, i) {
                    if (i === selectedResultIndex) el.classList.add('bg-gray-100');
                    else el.classList.remove('bg-gray-100');
                });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (items.length === 0) return;
                selectedResultIndex = (selectedResultIndex - 1 + items.length) % items.length;
                items.forEach(function(el, i) {
                    if (i === selectedResultIndex) el.classList.add('bg-gray-100');
                    else el.classList.remove('bg-gray-100');
                });
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedResultIndex >= 0 && items[selectedResultIndex]) {
                    items[selectedResultIndex].click();
                } else {
                    renderMarkers(true);
                    if (searchDropdown) searchDropdown.classList.add('hidden');
                }
            } else if (e.key === 'Escape') {
                if (searchDropdown) searchDropdown.classList.add('hidden');
            }
        });

        searchInput.addEventListener('focus', function() {
            if (this.value.trim().length > 0) {
                updateDropdown(getFilteredMatches(this.value));
            }
        });
    }

    if (searchClear) {
        searchClear.addEventListener('click', function() {
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            currentSearch = '';
            searchClear.classList.add('hidden');
            if (searchDropdown) searchDropdown.classList.add('hidden');
            renderMarkers(false);
        });
    }

    document.addEventListener('click', function(e) {
        var wrapper = document.getElementById('atlas-search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchDropdown) {
            searchDropdown.classList.add('hidden');
        }
    });

    // Toggle acquired via AJAX
    window.toggleAcquired = function (id, newStatus) {
        var fd = new FormData();
        fd.append('id', id); fd.append('new_status', newStatus); fd.append('csrf_token', csrfToken);
        fetch('../modules/postmark_atlas/ajax_toggle.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => { if (data.success) window.location.reload(); else alert('Error: ' + (data.error||'?')); })
            .catch(e  => alert('Network error: ' + e.message));
    };

    // ── Current Location ──────────────────────────────────────────────────────
    var userLocationMarker  = null;
    var userAccuracyCircle  = null;
    var trackingInterval    = null;
    var isTracking          = false;
    var firstFix            = true;

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


    function doLocate() {
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                var lat = pos.coords.latitude;
                var lng = pos.coords.longitude;
                var acc = pos.coords.accuracy;

                if (userLocationMarker)  { map.removeLayer(userLocationMarker); }
                if (userAccuracyCircle)  { map.removeLayer(userAccuracyCircle); }

                userAccuracyCircle = L.circle([lat, lng], {
                    radius: acc, color: '#2563eb', fillColor: '#2563eb', fillOpacity: 0.08, weight: 1.5
                }).addTo(map);

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
                    );

                if (firstFix) {
                    map.flyTo([lat, lng], Math.max(map.getZoom(), 12), { duration: 1.2 });
                    userLocationMarker.openPopup();
                    firstFix = false;
                } else {
                    // Silent update — don't re-fly or reopen popup
                    map.setView([lat, lng], map.getZoom(), { animate: true, duration: 0.5 });
                }
            },
            function (err) {
                // On error during live tracking, just skip this cycle silently
                if (!isTracking) {
                    var msgs = {
                        1: 'Location access was denied.',
                        2: 'Location unavailable.',
                        3: 'Request timed out.'
                    };
                    alert(msgs[err.code] || 'Could not get location: ' + err.message);
                    stopTracking();
                }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    function stopTracking() {
        isTracking = false;
        firstFix   = true;
        clearInterval(trackingInterval);
        trackingInterval = null;
        if (userLocationMarker) { map.removeLayer(userLocationMarker); userLocationMarker = null; }
        if (userAccuracyCircle) { map.removeLayer(userAccuracyCircle); userAccuracyCircle = null; }
        var btn = document.getElementById('btn-locate');
        if (btn) { btn.innerHTML = '📍 Locate Me'; btn.classList.remove('bg-red-600','border-red-600'); btn.classList.add('bg-blue-600','border-blue-600'); }
    }

    window.locateMe = function () {
        var btn = document.getElementById('btn-locate');
        if (!navigator.geolocation) { alert('Geolocation is not supported by your browser.'); return; }

        if (isTracking) {
            stopTracking();
            return;
        }

        isTracking = true;
        firstFix   = true;
        btn.innerHTML = '⏹ Stop Tracking';
        btn.classList.remove('bg-blue-600','border-blue-600');
        btn.classList.add('bg-red-600','border-red-600');

        doLocate();
        trackingInterval = setInterval(doLocate, 10000);
    };
});
</script>