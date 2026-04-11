<?php
// themes/default/route_planner.php
// Road-Based Route Planner Template

$pageTitle    = 'Route Planner - ' . SITE_TITLE;
$currentMenu  = '';
$ogDescription = 'Plan your collection route using real roads and discover post offices along the way.';

ob_start();
?>
<!-- External Styles -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />

<style>
    /* ── UI Styles ────────────────────────────────── */
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

    .rp-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
        position: relative;
        overflow: hidden;
    }
    .rp-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(ellipse at 30% 20%, rgba(56,189,248,0.08) 0%, transparent 60%),
                    radial-gradient(ellipse at 70% 80%, rgba(168,85,247,0.06) 0%, transparent 60%);
        pointer-events: none;
    }

    .rp-glass {
        background: rgba(255,255,255,0.06);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 16px;
    }

    /* Override LRM UI to hide the default sidebar and make it look premium */
    .leaflet-routing-container {
        display: none !important; /* We use our own UI results */
    }

    .rp-search-box {
        position: relative;
        width: 100%;
    }
    .rp-search-box input {
        width: 100%;
        padding: 14px 16px 14px 44px;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 12px;
        color: #f1f5f9;
        font-size: 15px;
        font-weight: 500;
        outline: none;
        transition: all 0.2s;
    }
    .rp-search-box input:focus {
        border-color: #38bdf8;
        background: rgba(255,255,255,0.12);
        box-shadow: 0 0 0 3px rgba(56,189,248,0.15);
    }
    .rp-search-box .rp-icon {
        position: absolute;
        left: 14px; top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }

    .rp-radius-slider {
        -webkit-appearance: none;
        width: 100%; height: 6px;
        background: rgba(255,255,255,0.15);
        border-radius: 3px;
        outline: none;
    }
    .rp-radius-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 20px; height: 20px;
        border-radius: 50%;
        background: #38bdf8;
        cursor: pointer;
        box-shadow: 0 0 10px rgba(56,189,248,0.4);
    }

    .rp-btn-primary {
        background: linear-gradient(135deg, #0ea5e9, #6366f1);
        color: white;
        padding: 14px 32px;
        border-radius: 12px;
        font-weight: 700;
        transition: all 0.25s;
        box-shadow: 0 4px 12px rgba(14,165,233,0.3);
    }
    .rp-btn-primary:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(14,165,233,0.4);
    }
    .rp-btn-primary:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Map and Results */
    #rp-map {
        border-radius: 20px;
        z-index: 1;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    }

    .rp-card {
        background: white;
        border-radius: 14px;
        padding: 18px;
        border: 1px solid #e2e8f0;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .rp-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    .rp-card.acquired { border-left: 5px solid #22c55e; }
    .rp-card.seeking { border-left: 5px solid #f59e0b; }

    .rp-badge {
        font-size: 11px;
        font-weight: 800;
        padding: 3px 10px;
        border-radius: 999px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* Autocomplete dropdowns for Nominatim */
    .rp-autocomplete-results {
        position: absolute;
        top: 100%; left: 0; right: 0;
        background: #1e293b;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 10px;
        margin-top: 6px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        display: none;
    }
    .rp-autocomplete-results.active { display: block; }
    .rp-autocomplete-item {
        padding: 12px 16px;
        color: #e2e8f0;
        font-size: 13px;
        cursor: pointer;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .rp-autocomplete-item:hover { background: rgba(56,189,248,0.1); }
    .rp-autocomplete-item:last-child { border-bottom: none; }

    /* Loading state overlay */
    #rp-loading {
        position: absolute;
        inset: 0;
        background: rgba(15,23,42,0.6);
        backdrop-filter: blur(4px);
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
    }
    .spinner {
        width: 40px; height: 40px;
        border: 4px solid rgba(255,255,255,0.1);
        border-top-color: #38bdf8;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-up { animation: fadeInUp 0.4s ease-out forwards; }
</style>
<?php
$additionalHead = ob_get_clean();
require_once ThemeManager::getHeader();
?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     HERO: INPUTS
     ═══════════════════════════════════════════════════════════════════════════ -->
<section class="rp-hero py-12 md:py-20">
    <div class="max-w-6xl mx-auto px-4 relative z-10">
        <div class="text-center mb-10">
            <h1 class="text-4xl md:text-5xl font-black text-white tracking-tight leading-tight">Road Route Planner</h1>
            <p class="text-gray-400 mt-3 text-lg max-w-2xl mx-auto italic">Search for cities to build your route and discover collection targets along the road.</p>
        </div>

        <div class="rp-glass p-6 md:p-10 relative">
            <div id="rp-loading"><div class="text-center"><div class="spinner mx-auto mb-3"></div><p class="text-sky-300 font-bold">Calculating Route...</p></div></div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                <!-- Start -->
                <div class="rp-search-box">
                    <label class="block text-xs font-bold text-sky-400 uppercase mb-2 tracking-widest">Start City / Place</label>
                    <span class="rp-icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="12" r="3"/></svg></span>
                    <input type="text" id="rp-start-input" placeholder="e.g. Bangalore" autocomplete="off">
                    <div id="rp-start-list" class="rp-autocomplete-results"></div>
                </div>

                <!-- End -->
                <div class="rp-search-box">
                    <label class="block text-xs font-bold text-purple-400 uppercase mb-2 tracking-widest">End City / Place</label>
                    <span class="rp-icon"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="12" r="3"/></svg></span>
                    <input type="text" id="rp-end-input" placeholder="e.g. Chennai" autocomplete="off">
                    <div id="rp-end-list" class="rp-autocomplete-results"></div>
                </div>

                <!-- Radius Slider -->
                <div class="md:col-span-1">
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-3 tracking-widest">
                        Corridor Radius: <span id="radius-val" class="text-sky-300">50 km</span>
                    </label>
                    <input type="range" min="10" max="150" step="5" value="50" class="rp-radius-slider" id="radius-slider">
                </div>

                <div class="md:col-span-1 flex items-center justify-end">
                    <button id="calculate-btn" class="rp-btn-primary w-full md:w-auto">
                        Map Collection Route
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     RESULTS PANEL
     ═══════════════════════════════════════════════════════════════════════════ -->
<section class="bg-slate-50 min-h-screen py-10">
    <div class="max-w-[1400px] mx-auto px-4 grid grid-cols-1 xl:grid-cols-12 gap-10">
        
        <!-- List Panel -->
        <div class="xl:col-span-5 order-2 xl:order-1">
            <div id="stats-header" class="hidden mb-6 flex justify-between items-end">
                <div>
                    <h2 class="text-2xl font-black text-slate-800 tracking-tight">On Your Route</h2>
                    <p id="stops-found" class="text-slate-500 font-medium"></p>
                </div>
                <div id="stat-pills" class="flex gap-2">
                    <!-- Dynamic Pills -->
                </div>
            </div>

            <div id="route-results-list" class="space-y-4">
                <!-- Dynamic Results -->
                <div class="text-center py-20 opacity-50">
                    <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    <p class="font-bold text-slate-400">Search for a route above to see collection stops along the road.</p>
                </div>
            </div>
        </div>

        <!-- Map Panel -->
        <div class="xl:col-span-7 order-1 xl:order-2">
            <div id="rp-map" style="width: 100%; height: 75vh; position: sticky; top: 100px;" class="overflow-hidden border border-slate-200"></div>
        </div>

    </div>
</section>

<?php require_once ThemeManager::getFooter(); ?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SCRIPTS
     ═══════════════════════════════════════════════════════════════════════════ -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>

<script>
(function() {
    const PO_DATA = <?= $jsonAllLocations ?>;
    const SITE_URL = '<?= SITE_URL ?>';
    
    // State
    const state = {
        start: null, // {lat, lng, name}
        end: null,
        radiusKm: 50,
        routeCoords: [], // array of [lat, lng]
        activeMarkers: []
    };

    // Initialize Map
    const map = L.map('rp-map', { zoomControl: false }).setView([20.5937, 78.9629], 5);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://carto.com/">CARTO</a>'
    }).addTo(map);
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    // Initial Routing Control (dummy, will update later)
    let routingControl = null;

    // ── Geocoding Autocomplete ────────────────────────────────────────────────
    const setupInput = (id, listId, type) => {
        const input = document.getElementById(id);
        const list = document.getElementById(listId);
        let debounce = null;

        const handleSearch = (q) => {
            if (q.length < 3) {
                list.classList.remove('active');
                return;
            }
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=5`)
                .then(r => r.json())
                .then(data => {
                    list.innerHTML = data.map(item => `
                        <div class="rp-autocomplete-item" data-lat="${item.lat}" data-lng="${item.lon}" data-name="${item.display_name}">
                            ${item.display_name}
                        </div>
                    `).join('');
                    list.classList.add('active');
                    
                    list.querySelectorAll('.rp-autocomplete-item').forEach(el => {
                        el.addEventListener('click', () => {
                            state[type] = {
                                lat: parseFloat(el.dataset.lat),
                                lng: parseFloat(el.dataset.lng),
                                name: el.dataset.name
                            };
                            input.value = el.dataset.name;
                            list.classList.remove('active');
                        });
                    });
                });
        };

        input.addEventListener('input', (e) => {
            clearTimeout(debounce);
            debounce = setTimeout(() => handleSearch(e.target.value), 400);
        });
        
        document.addEventListener('click', (e) => {
            if (e.target !== input) list.classList.remove('active');
        });
    };

    setupInput('rp-start-input', 'rp-start-list', 'start');
    setupInput('rp-end-input', 'rp-end-list', 'end');

    // ── Routing Logic ─────────────────────────────────────────────────────────
    const calculateBtn = document.getElementById('calculate-btn');
    const loadingOverlay = document.getElementById('rp-loading');
    const radiusSlider = document.getElementById('radius-slider');
    const radiusLabel = document.getElementById('radius-val');

    radiusSlider.addEventListener('input', (e) => {
        state.radiusKm = parseInt(e.target.value);
        radiusLabel.textContent = `${state.radiusKm} km`;
        if (state.routeCoords.length) filterPointsOnRoute();
    });

    calculateBtn.addEventListener('click', () => {
        if (!state.start || !state.end) {
            alert('Please select both a start and end location from the search results.');
            return;
        }

        loadingOverlay.style.display = 'flex';
        
        if (routingControl) map.removeControl(routingControl);
        
        routingControl = L.Routing.control({
            waypoints: [
                L.latLng(state.start.lat, state.start.lng),
                L.latLng(state.end.lat, state.end.lng)
            ],
            lineOptions: {
                styles: [{ color: '#6366f1', opacity: 0.8, weight: 6 }]
            },
            createMarker: () => null, // We'll manage markers manually
            addWaypoints: false,
            draggableWaypoints: false,
            router: L.Routing.osrmv1({
                serviceUrl: 'https://router.project-osrm.org/route/v1/'
            })
        }).on('routesfound', function(e) {
            loadingOverlay.style.display = 'none';
            state.routeCoords = e.routes[0].coordinates;
            filterPointsOnRoute();
        }).on('routingerror', function(err) {
            loadingOverlay.style.display = 'none';
            alert('Could not find a road route between these locations. They might be separated by ocean or unmapped areas.');
        }).addTo(map);
    });

    // ── Point Filtering ───────────────────────────────────────────────────────
    function filterPointsOnRoute() {
        const resultsList = document.getElementById('route-results-list');
        const countEl = document.getElementById('stops-found');
        const header = document.getElementById('stats-header');
        
        header.classList.remove('hidden');
        resultsList.innerHTML = '';
        
        // Clean existing markers
        state.activeMarkers.forEach(m => map.removeLayer(m));
        state.activeMarkers = [];

        // Haversine distance helper (m)
        const getDist = (lat1, lon1, lat2, lon2) => {
            const R = 6371e3; 
            const φ1 = lat1 * Math.PI/180;
            const φ2 = lat2 * Math.PI/180;
            const Δφ = (lat2-lat1) * Math.PI/180;
            const Δλ = (lon2-lon1) * Math.PI/180;
            const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                      Math.cos(φ1) * Math.cos(φ2) *
                      Math.sin(Δλ/2) * Math.sin(Δλ/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c; 
        };

        const foundStops = [];
        const radiusMeters = state.radiusKm * 1000;

        // Optimization: Rough bounding box of the whole route first
        let minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
        state.routeCoords.forEach(c => {
            minLat = Math.min(minLat, c.lat); maxLat = Math.max(maxLat, c.lat);
            minLng = Math.min(minLng, c.lng); maxLng = Math.max(maxLng, c.lng);
        });
        
        // Pad bounding box by radius (~0.01 degree per km)
        const pad = (state.radiusKm / 111.0);
        minLat -= pad; maxLat += pad; minLng -= pad; maxLng += pad;

        PO_DATA.forEach(po => {
            const flat = parseFloat(po.latitude);
            const flng = parseFloat(po.longitude);
            
            // BBox pre-check
            if (flat < minLat || flat > maxLat || flng < minLng || flng > maxLng) return;

            // Check distance to route segments
            // For efficiency, we check distance to route points. 
            // For better accuracy on very long segments, we'd check distance to line segment,
            // but road routes have high point density.
            let minRouteDist = Infinity;
            
            // Only check every Nth point for high-density routes to save CPU, 
            // then check precisely nearby.
            for (let i = 0; i < state.routeCoords.length; i += 5) {
                const d = getDist(flat, flng, state.routeCoords[i].lat, state.routeCoords[i].lng);
                if (d < minRouteDist) minRouteDist = d;
                if (d < radiusMeters) break; // found close enough
            }

            if (minRouteDist <= radiusMeters) {
                foundStops.push(po);
            }
        });

        if (foundStops.length === 0) {
            resultsList.innerHTML = '<div class="text-center py-10 text-slate-400">No collection targets found along this road path. Try increasing the radius.</div>';
            countEl.textContent = '0 collection targets found';
            return;
        }

        countEl.textContent = `${foundStops.length} collection targets found`;
        
        // Update Pills
        const acq = foundStops.filter(s => parseInt(s.is_acquired)).length;
        document.getElementById('stat-pills').innerHTML = `
            <span class="rp-badge bg-green-100 text-green-700">${acq} Collected</span>
            <span class="rp-badge bg-amber-100 text-amber-700">${foundStops.length - acq} Seeking</span>
        `;

        foundStops.forEach((s, idx) => {
            const isAcq = parseInt(s.is_acquired);
            const card = document.createElement('div');
            card.className = `rp-card animate-fade-up ${isAcq ? 'acquired' : 'seeking'}`;
            card.style.animationDelay = `${idx * 0.05}s`;
            card.innerHTML = `
                <div class="flex-1">
                    <div class="text-[10px] font-black text-slate-300 mb-1">#${idx + 1}</div>
                    <h3 class="font-bold text-slate-800 text-sm leading-tight">${s.post_office}</h3>
                    <div class="text-[11px] text-slate-500 font-medium">${s.district}, ${s.state} · ${s.pin_code}</div>
                </div>
                <div class="text-right">
                    ${s.linked_item_title ? `<a href="${SITE_URL}/item/${s.item_link_id}" class="block text-[10px] font-bold text-sky-600 mb-1 hover:underline">VIEW ITEM</a>` : ''}
                    <button class="focus-btn p-2 rounded-full bg-slate-100 text-slate-400 hover:text-sky-500 transition-colors" data-id="${s.id}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
            `;
            resultsList.appendChild(card);
            
            // Map Marker
            const markerColor = isAcq ? '#22c55e' : '#f59e0b';
            const marker = L.circleMarker([s.latitude, s.longitude], {
                radius: 8, fillColor: markerColor, color: '#fff', weight: 2, opacity: 1, fillOpacity: 0.9
            }).addTo(map);
            
            marker.bindPopup(`
                <div style="font-family:Inter,sans-serif;">
                    <strong style="display:block;font-size:13px;">${s.post_office}</strong>
                    <span style="font-size:11px;color:#64748b;">${s.district}, ${s.state}</span>
                    <hr style="margin:8px 0; border:0; border-top:1px solid #eee;">
                    <span class="rp-badge" style="background:${isAcq ? '#dcfce7' : '#fef3c7'}; color:${isAcq ? '#166534' : '#92400e'}">${isAcq ? 'Collected' : 'To Collect'}</span>
                </div>
            `);
            
            state.activeMarkers.push(marker);
            card.querySelector('.focus-btn').onclick = () => {
                map.setView([s.latitude, s.longitude], 12);
                marker.openPopup();
            };
        });

        // Zoom to fit all found points + route
        const bounds = L.latLngBounds(state.routeCoords);
        foundStops.forEach(s => bounds.extend([s.latitude, s.longitude]));
        map.fitBounds(bounds, { padding: [50, 50] });
    }

})();
</script>
