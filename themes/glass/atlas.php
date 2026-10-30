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
                <!-- Search Input with Autocomplete -->
                <div class="relative w-full sm:w-64 md:w-72" id="atlas-search-wrapper">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input type="text" id="atlas-search-input" placeholder="Search PO, PIN, district..." autocomplete="off"
                               class="w-full pl-9 pr-8 py-2 text-sm rounded-xl border border-white/15 focus:border-blue-400/80 focus:ring-1 focus:ring-blue-400/80 outline-none bg-white/10 backdrop-blur-xl text-white placeholder-slate-400 shadow-[0_4px_16px_rgba(0,0,0,0.2)] transition">
                        <button id="atlas-search-clear" type="button" class="hidden absolute inset-y-0 right-0 items-center pr-3 text-slate-400 hover:text-white" title="Clear search">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <!-- Suggestions Dropdown -->
                    <div id="atlas-search-dropdown" class="hidden absolute left-0 right-0 mt-2 max-h-72 overflow-y-auto bg-slate-900/95 backdrop-blur-2xl rounded-2xl shadow-[0_16px_32px_rgba(0,0,0,0.5)] border border-white/10 z-[1000] text-sm text-slate-200 divide-y divide-white/10"></div>
                </div>

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
    <script src="<?= SITE_URL ?>/assets/js/atlas_text.js"></script>
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

        var markersGroup = L.layerGroup().addTo(map);
        var rawData = <?= $jsonLocations ?>;
        var currentSearch = '';
        var selectedResultIndex = -1;
        
        rawData.forEach(function(loc) {
            if (loc.latitude && loc.longitude) {
                var isAcquired = parseInt(loc.is_acquired, 10) === 1;
                var markerTitle = AtlasText.escape(loc.post_office + ' - ' + loc.district);
                
                var statusHtml = isAcquired ? 
                    '<span style="background: rgba(34,197,94,0.2); color: #86efac; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; display: inline-block; margin-top: 4px; border: 1px solid rgba(34,197,94,0.3);">✓ Collected</span>' : 
                    '<span style="background: rgba(255,255,255,0.05); color: #94a3b8; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; display: inline-block; margin-top: 4px; border: 1px solid rgba(255,255,255,0.1);">Seeking</span>';

                var popupHtml = '<div style="font-family: \'Plus Jakarta Sans\', sans-serif; padding: 4px;">' +
                                '<h3 style="font-size:15px; font-weight:800; margin:0 0 4px 0; color: #f8fafc;">' + AtlasText.escape(loc.post_office) + '</h3>' +
                                '<p style="margin: 0 0 8px 0; color: #94a3b8; font-size:13px;">' + AtlasText.escape(loc.district) + ', ' + AtlasText.escape(loc.state) + ' - ' + AtlasText.escape(loc.pin_code) + '</p>' +
                                statusHtml +
                                '</div>';

                var iconToUse = isAcquired ? greenIcon : greyIcon;
                
                var marker = L.marker([parseFloat(loc.latitude), parseFloat(loc.longitude)], {icon: iconToUse})
                    .bindPopup(popupHtml, {className: 'glass-popup'})
                    .bindTooltip(markerTitle);

                loc._marker = marker;
                loc._isAcquired = isAcquired;
            }
        });

        function renderMarkers(shouldFitBounds) {
            markersGroup.clearLayers();
            var query = currentSearch.trim().toLowerCase();
            var bounds = [];

            rawData.forEach(function(loc) {
                if (!loc._marker) return;

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

                markersGroup.addLayer(loc._marker);
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

        // ── Search functionality ─────────────────────────────────────────────
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
                searchDropdown.innerHTML = '<div class="p-3 text-center text-slate-400 text-xs">No matching postmarks found</div>';
                searchDropdown.classList.remove('hidden');
                return;
            }

            var limit = 8;
            var topMatches = matches.slice(0, limit);
            var html = '<ul class="divide-y divide-white/10">';

            topMatches.forEach(function(loc, idx) {
                var isAcq = loc._isAcquired;
                var badge = isAcq
                    ? '<span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-bold bg-green-500/20 text-green-300 border border-green-500/30">Collected</span>'
                    : '<span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-bold bg-white/5 text-slate-400 border border-white/10">Seeking</span>';

                var ppcText = loc.ppc_name ? ' <span class="text-xs text-slate-400 font-normal">(' + AtlasText.escape(loc.ppc_name) + ')</span>' : '';

                html += '<li class="atlas-search-item px-4 py-2.5 cursor-pointer hover:bg-white/10 transition-colors flex items-center justify-between gap-3" data-idx="' + idx + '" onclick="selectLocation(' + loc.id + ')">'
                      + '<div class="min-w-0 flex-grow">'
                      +   '<div class="font-bold text-white text-xs truncate">' + AtlasText.escape(loc.post_office) + ppcText + '</div>'
                      +   '<div class="text-[11px] text-slate-400 truncate">' + AtlasText.escape(loc.district) + ', ' + AtlasText.escape(loc.state) + ' · ' + AtlasText.escape(loc.pin_code) + '</div>'
                      + '</div>'
                      + '<div class="flex-shrink-0">' + badge + '</div>'
                      + '</li>';
            });

            html += '</ul>';

            if (matches.length > limit) {
                html += '<div class="px-4 py-2 bg-white/5 text-[11px] text-slate-400 text-center border-t border-white/10">'
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

            if (!markersGroup.hasLayer(loc._marker)) {
                markersGroup.addLayer(loc._marker);
            }

            map.flyTo([parseFloat(loc.latitude), parseFloat(loc.longitude)], 14, { duration: 1.0 });
            setTimeout(function() {
                loc._marker.openPopup();
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
                        if (i === selectedResultIndex) el.classList.add('bg-white/10');
                        else el.classList.remove('bg-white/10');
                    });
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (items.length === 0) return;
                    selectedResultIndex = (selectedResultIndex - 1 + items.length) % items.length;
                    items.forEach(function(el, i) {
                        if (i === selectedResultIndex) el.classList.add('bg-white/10');
                        else el.classList.remove('bg-white/10');
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

        // ── Current Location ─────────────────────────────────────────────────
        var userLocationMarker = null;
        var userAccuracyCircle = null;
        var trackingInterval   = null;
        var isTracking         = false;
        var firstFix           = true;

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

        function doLocate() {
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
                        );
                    if (firstFix) {
                        map.flyTo([lat, lng], Math.max(map.getZoom(), 12), { duration: 1.2 });
                        userLocationMarker.openPopup();
                        firstFix = false;
                    } else {
                        map.setView([lat, lng], map.getZoom(), { animate: true, duration: 0.5 });
                    }
                },
                function (err) {
                    if (!isTracking) {
                        var msgs = { 1: 'Location access denied.', 2: 'Location unavailable.', 3: 'Request timed out.' };
                        alert(msgs[err.code] || 'Could not get location: ' + err.message);
                        stopTracking();
                    }
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        }

        var SVG_CROSSHAIR = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v2m0 16v2M2 12h2m16 0h2"/></svg>';

        function stopTracking() {
            isTracking = false; firstFix = true;
            clearInterval(trackingInterval); trackingInterval = null;
            if (userLocationMarker) { map.removeLayer(userLocationMarker); userLocationMarker = null; }
            if (userAccuracyCircle) { map.removeLayer(userAccuracyCircle); userAccuracyCircle = null; }
            var btn = document.getElementById('btn-locate');
            if (btn) { btn.innerHTML = SVG_CROSSHAIR + ' Locate Me'; btn.style.color = '#93c5fd'; btn.style.borderColor = 'rgba(96,165,250,0.4)'; btn.style.background = 'rgba(255,255,255,0.1)'; }
        }

        window.locateMe = function () {
            var btn = document.getElementById('btn-locate');
            if (!navigator.geolocation) { alert('Geolocation is not supported by your browser.'); return; }
            if (isTracking) { stopTracking(); return; }
            isTracking = true; firstFix = true;
            btn.innerHTML = '&#x23F9; Stop Tracking';
            btn.style.color = '#fca5a5'; btn.style.borderColor = 'rgba(239,68,68,0.4)'; btn.style.background = 'rgba(239,68,68,0.15)';
            doLocate();
            trackingInterval = setInterval(doLocate, 10000);
        };
    });
    </script>
