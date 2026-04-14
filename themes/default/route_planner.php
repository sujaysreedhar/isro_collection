<?php
// themes/default/route_planner.php
// Road-Based Route Planner Template

$pageTitle    = 'Route Planner - ' . SITE_TITLE;
$currentMenu  = '';
$ogDescription = 'Plan your collection route using real roads and discover post offices along the way.';

// Fetch Google Maps API Key
$stmtKey = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'route_planner_google_maps_key'");
$gmapsKey = $stmtKey->fetchColumn() ?: '';

ob_start();
?>
<!-- External Styles -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* ── UI Styles ────────────────────────────────── */
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

    .rp-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
        position: relative;
        /* NOTE: Do NOT use overflow:hidden here — it clips the Google Places
           pac-container autocomplete dropdown which is appended to <body>
           but positioned relative to the input context. */
    }
    .rp-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(ellipse at 30% 20%, rgba(56,189,248,0.08) 0%, transparent 60%),
                    radial-gradient(ellipse at 70% 80%, rgba(168,85,247,0.06) 0%, transparent 60%);
        pointer-events: none;
        overflow: hidden;
    }

    .rp-glass {
        background: rgba(255,255,255,0.06);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 16px;
        /* backdrop-filter creates a stacking context; keep z-index low so that
           the body-appended .pac-container (z-index:99999) always paints on top */
        position: relative;
        z-index: 0;
    }

    .rp-search-box {
        position: relative;
        width: 100%;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .rp-search-box input {
        width: 100%;
        padding: 12px 16px 12px 44px;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 12px;
        color: #f1f5f9;
        font-size: 14px;
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

    .rp-search-box .remove-stop {
        color: #ef4444;
        background: rgba(239, 68, 68, 0.1);
        border: none;
        padding: 8px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .rp-search-box .remove-stop:hover {
        background: rgba(239, 68, 68, 0.2);
    }

    .add-stop-btn {
        background: rgba(255, 255, 255, 0.1);
        color: #f1f5f9;
        border: 1px dashed rgba(255, 255, 255, 0.3);
        padding: 10px 16px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
        margin-top: 8px;
    }
    .add-stop-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        border-color: #38bdf8;
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
        border: none;
        cursor: pointer;
    }
    .rp-btn-primary:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(14,165,233,0.4);
    }
    .rp-btn-primary:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Checkbox toggle */
    .toggle-container {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #e2e8f0;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        user-select: none;
    }
    .toggle-checkbox {
        appearance: none;
        width: 40px;
        height: 20px;
        background: rgba(255,255,255,0.2);
        border-radius: 20px;
        position: relative;
        cursor: pointer;
        outline: none;
        transition: background 0.3s;
    }
    .toggle-checkbox:checked {
        background: #38bdf8;
    }
    .toggle-checkbox::after {
        content: '';
        position: absolute;
        top: 2px;
        left: 2px;
        width: 16px;
        height: 16px;
        background: white;
        border-radius: 50%;
        transition: transform 0.3s;
    }
    .toggle-checkbox:checked::after {
        transform: translateX(20px);
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

    /* ── Results Toolbar ──────────────────────────────── */
    .rp-toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 10px 14px;
        margin-bottom: 16px;
    }
    .rp-toolbar label {
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
    }
    .rp-sort-select {
        flex: 1;
        min-width: 150px;
        padding: 7px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        background: #f8fafc;
        cursor: pointer;
        outline: none;
        transition: border-color 0.2s;
    }
    .rp-sort-select:focus { border-color: #38bdf8; }

    .rp-hide-toggle {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 12px;
        font-weight: 700;
        color: #475569;
        cursor: pointer;
        white-space: nowrap;
        user-select: none;
        margin-left: auto;
    }
    .rp-hide-toggle input[type=checkbox] {
        appearance: none;
        width: 34px;
        height: 18px;
        background: #cbd5e1;
        border-radius: 18px;
        position: relative;
        cursor: pointer;
        transition: background 0.25s;
        flex-shrink: 0;
    }
    .rp-hide-toggle input[type=checkbox]:checked {
        background: #22c55e;
    }
    .rp-hide-toggle input[type=checkbox]::after {
        content: '';
        position: absolute;
        top: 2px; left: 2px;
        width: 14px; height: 14px;
        background: #fff;
        border-radius: 50%;
        transition: transform 0.25s;
    }
    .rp-hide-toggle input[type=checkbox]:checked::after {
        transform: translateX(16px);
    }
    .rp-card[data-hidden] { display: none; }
    
    /* Missing Key Alert */
    .missing-key-alert {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #fca5a5;
        padding: 12px 16px;
        border-radius: 12px;
        margin-bottom: 20px;
        font-size: 14px;
        text-align: left;
    }
    /* ── Google Places Autocomplete Dropdown ─────── */
    .pac-container {
        background-color: #1e293b !important;
        border-radius: 10px !important;
        border: 1px solid rgba(255,255,255,0.15) !important;
        font-family: 'Inter', sans-serif !important;
        box-shadow: 0 10px 40px rgba(0,0,0,0.5) !important;
        z-index: 99999 !important;   /* must be above everything */
        margin-top: 4px !important;
    }
    .pac-container:after {
        display: none !important;   /* hide the Google logo if desired */
    }
    .pac-item {
        padding: 12px 16px !important;
        color: #e2e8f0 !important;
        font-size: 13px !important;
        cursor: pointer !important;
        border-bottom: 1px solid rgba(255,255,255,0.05) !important;
        transition: background 0.15s;
    }
    .pac-item:hover,
    .pac-item.pac-item-selected {
        background: rgba(56,189,248,0.15) !important;
    }
    .pac-item-query {
        font-size: 14px !important;
        color: #fff !important;
    }
    .pac-icon { display: none !important; }
    .pac-matched { color: #38bdf8 !important; font-weight: 700; }
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
            <?php if (empty($gmapsKey)): ?>
            <div class="missing-key-alert">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i><strong>Configuration Required:</strong> Google Maps API key is missing. Please add <code>route_planner_google_maps_key</code> to the database settings. Without it, the map and routing service will not function correctly.
            </div>
            <?php endif; ?>

            <div id="rp-loading"><div class="text-center"><div class="spinner mx-auto mb-3"></div><p class="text-sky-300 font-bold">Calculating Route...</p></div></div>
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left Column: Waypoints -->
                <div class="lg:col-span-5 flex flex-col h-full">
                    <label class="block text-xs font-bold text-sky-400 uppercase mb-3 tracking-widest">Route Stops (<span id="stop-count">2</span>)</label>
                    <div id="waypoints-container" class="space-y-2 flex-grow">
                        <!-- Initial two stops -->
                        <div class="rp-search-box waypoint-input" data-index="0">
                            <span class="rp-icon"><i class="fa-solid fa-location-dot"></i></span>
                            <input type="text" placeholder="Start Location (e.g. Bangalore)" autocomplete="off">
                            <button type="button" class="remove-stop" disabled style="opacity:0.3; cursor:not-allowed;"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <div class="rp-search-box waypoint-input" data-index="1">
                            <span class="rp-icon"><i class="fa-solid fa-flag-checkered"></i></span>
                            <input type="text" placeholder="End Location (e.g. Chennai)" autocomplete="off">
                            <button type="button" class="remove-stop" disabled style="opacity:0.3; cursor:not-allowed;"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </div>
                    <div>
                        <button id="add-stop-btn" class="add-stop-btn">
                            <i class="fa-solid fa-plus"></i> Add Another Stop
                        </button>
                    </div>
                </div>

                <!-- Right Column: Settings and Action -->
                <div class="lg:col-span-7 flex flex-col justify-between h-full bg-slate-800/40 border border-slate-700/50 p-6 rounded-xl">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-3 tracking-widest">
                                Corridor Radius: <span id="radius-val" class="text-sky-300 font-black text-sm">50 km</span>
                            </label>
                            <input type="range" min="10" max="150" step="5" value="50" class="rp-radius-slider" id="radius-slider">
                        </div>

                        <div>
                            <label class="toggle-container">
                                <input type="checkbox" id="optimize-route" class="toggle-checkbox"> 
                                Optimize Route Order?
                                <span class="text-xs font-normal text-slate-400 block ml-auto">Automatically reorganizes your stops for the shortest trip. Note: Start and End points remain fixed.</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-700/50 flex justify-end">
                        <button id="calculate-btn" class="rp-btn-primary w-full md:w-auto">
                            Calculate Best Route <i class="fa-solid fa-arrow-right ml-2"></i>
                        </button>
                    </div>
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
            <!-- Stats Header -->
            <div id="stats-header" class="hidden mb-4">
                <div class="flex justify-between items-end mb-3">
                    <div>
                        <h2 class="text-2xl font-black text-slate-800 tracking-tight">On Your Route</h2>
                        <p id="stops-found" class="text-slate-500 font-medium text-sm mt-0.5"></p>
                    </div>
                    <div id="stat-pills" class="flex gap-2">
                        <!-- Dynamic Pills -->
                    </div>
                </div>

                <!-- Toolbar: Sort + Hide Collected -->
                <div class="rp-toolbar">
                    <label for="rp-sort-select"><i class="fa-solid fa-arrow-up-wide-short mr-1"></i>Sort:</label>
                    <select id="rp-sort-select" class="rp-sort-select">
                        <option value="route">Route Order</option>
                        <option value="name">Name (A → Z)</option>
                        <option value="state">State</option>
                        <option value="seeking">Seeking First</option>
                    </select>
                    <label class="rp-hide-toggle" for="rp-hide-collected">
                        <input type="checkbox" id="rp-hide-collected">
                        Hide Collected
                    </label>
                </div>
            </div>

            <div id="route-results-list" class="space-y-3">
                <div class="text-center py-20 opacity-50">
                    <i class="fa-solid fa-route text-6xl text-slate-300 mb-4 block"></i>
                    <p class="font-bold text-slate-400">Search for a route above to see collection stops along the road.</p>
                </div>
            </div>
        </div>

        <!-- Map Panel -->
        <div class="xl:col-span-7 order-1 xl:order-2">
            <div id="rp-map" style="width: 100%; height: 75vh; position: sticky; top: 100px;" class="overflow-hidden border border-slate-200 bg-slate-200 flex items-center justify-center">
                <?php if (empty($gmapsKey)): ?>
                    <div class="text-slate-400 font-medium p-6 text-center">
                        <i class="fa-solid fa-map text-4xl mb-3 block"></i>
                        Google Maps API Key required to render map.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<?php require_once ThemeManager::getFooter(); ?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SCRIPTS
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php if (!empty($gmapsKey)): ?>
<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($gmapsKey) ?>&libraries=places,geometry&callback=initMap"></script>
<?php endif; ?>

<script>
    const PO_DATA = <?= $jsonAllLocations ?>;
    const SITE_URL = '<?= SITE_URL ?>';

    // ── State ────────────────────────────────────────────
    let map;
    let directionsService;
    let directionsRenderer;
    let autocompleteInstances = [];
    let state = {
        radiusKm: 50,
        activeMarkers: [],        // parallel array to state.foundStops
        currentRoutePolyline: null,
        foundStops: [],           // all stops found on last route calculation
        routeOrder: [],           // index mapping: routeOrder[i] = original foundStops index sorted by proximity along polyline
        hideCollected: false,
        sortMode: 'route'
    };

    function initMap() {
        // Initialize Map
        map = new google.maps.Map(document.getElementById('rp-map'), {
            center: { lat: 20.5937, lng: 78.9629 }, // Default to India
            zoom: 5,
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            mapTypeControl: false,
            streetViewControl: false,
            styles: [
                {
                    "featureType": "all",
                    "elementType": "labels.text.fill",
                    "stylers": [{ "color": "#7c93a3" }, { "lightness": "-10" }]
                },
                {
                    "featureType": "administrative.country",
                    "elementType": "geometry",
                    "stylers": [{ "visibility": "on" }]
                },
                {
                    "featureType": "administrative.country",
                    "elementType": "geometry.stroke",
                    "stylers": [{ "color": "#a0aab4" }]
                },
                {
                    "featureType": "poi",
                    "stylers": [{ "visibility": "simplified" }]
                },
                {
                    "featureType": "road.highway",
                    "elementType": "geometry.fill",
                    "stylers": [{ "color": "#cbd4dc" }]
                },
                {
                    "featureType": "road.arterial",
                    "elementType": "geometry.fill",
                    "stylers": [{ "color": "#cbd4dc" }]
                },
                {
                    "featureType": "road.local",
                    "elementType": "geometry.fill",
                    "stylers": [{ "color": "#cbd4dc" }]
                },
                {
                    "featureType": "water",
                    "elementType": "geometry.fill",
                    "stylers": [{ "color": "#d9e3ea" }]
                }
            ]
        });

        directionsService = new google.maps.DirectionsService();
        directionsRenderer = new google.maps.DirectionsRenderer({
            map: map,
            suppressMarkers: false,
            polylineOptions: {
                strokeColor: '#6366f1',
                strokeOpacity: 0.8,
                strokeWeight: 6
            }
        });

        // Initialize Places Autocomplete for existing inputs
        document.querySelectorAll('.waypoint-input input').forEach(input => {
            initAutocomplete(input);
        });
    }

    // Bind Places Autocomplete to an input element
    function initAutocomplete(inputElement) {
        if (!window.google || !window.google.maps || !window.google.maps.places) return;

        const autocomplete = new google.maps.places.Autocomplete(inputElement, {
            // Bias towards India
            componentRestrictions: {},  // no country restriction — adjust if needed
            fields: ['place_id', 'geometry', 'name', 'formatted_address']
        });

        // When a suggestion is selected from the dropdown, set the input value
        // explicitly and prevent the browser's native autocomplete from interfering.
        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();
            if (place && place.formatted_address) {
                inputElement.value = place.formatted_address;
            } else if (place && place.name) {
                inputElement.value = place.name;
            }
            // Dispatch a native 'input' event so any other listeners fire
            inputElement.dispatchEvent(new Event('input', { bubbles: true }));
        });

        // Stop the Google autocomplete from being blocked by parent click listeners
        google.maps.event.addDomListener(inputElement, 'keydown', (e) => {
            // Allow Enter to select highlighted pac-item without submitting any form
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        autocompleteInstances.push(autocomplete);
        return autocomplete;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('waypoints-container');
        const addBtn = document.getElementById('add-stop-btn');
        const calculateBtn = document.getElementById('calculate-btn');
        const radiusSlider = document.getElementById('radius-slider');
        const radiusLabel = document.getElementById('radius-val');
        const stopCount = document.getElementById('stop-count');

        // Radius Update
        radiusSlider.addEventListener('input', (e) => {
            state.radiusKm = parseInt(e.target.value);
            radiusLabel.textContent = `${state.radiusKm} km`;
            if (state.currentRoutePolyline) filterPointsOnRoute();
        });

        // Add dynamically
        addBtn.addEventListener('click', () => {
            const inputs = container.querySelectorAll('.waypoint-input');
            const newIndex = inputs.length;
            
            const div = document.createElement('div');
            div.className = 'rp-search-box waypoint-input';
            div.dataset.index = newIndex;
            div.innerHTML = `
                <span class="rp-icon"><i class="fa-solid fa-location-crosshairs"></i></span>
                <input type="text" placeholder="Enter Stop (e.g. Pune)" autocomplete="off">
                <button type="button" class="remove-stop"><i class="fa-solid fa-xmark"></i></button>
            `;
            container.appendChild(div);
            
            // init places
            const inputField = div.querySelector('input');
            initAutocomplete(inputField);

            stopCount.textContent = (newIndex + 1);
            
            // Enable all remove buttons if > 2 items
            updateRemoveButtons();
        });

        // Remove dynamically
        container.addEventListener('click', (e) => {
            const btn = e.target.closest('.remove-stop');
            if (!btn) return;
            if (btn.disabled) return;
            
            const row = btn.closest('.waypoint-input');
            row.remove();
            
            // Update stop count
            const inputs = container.querySelectorAll('.waypoint-input');
            stopCount.textContent = inputs.length;
            updateRemoveButtons();
        });

        function updateRemoveButtons() {
            const inputs = container.querySelectorAll('.waypoint-input');
            const removeBtns = container.querySelectorAll('.remove-stop');
            if (inputs.length <= 2) {
                removeBtns.forEach(btn => {
                    btn.disabled = true;
                    btn.style.opacity = '0.3';
                    btn.style.cursor = 'not-allowed';
                });
            } else {
                removeBtns.forEach(btn => {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                });
            }
        }

        // Calculate Route
        calculateBtn.addEventListener('click', () => {
            if (!window.google) {
                alert("Google Maps API has not loaded yet or is missing a valid API key.");
                return;
            }

            const inputs = container.querySelectorAll('input');
            const locations = Array.from(inputs).map(input => input.value.trim()).filter(val => val !== '');

            if (locations.length < 2) {
                alert("Please provide at least a Start and End location.");
                return;
            }

            document.getElementById('rp-loading').style.display = 'flex';

            const origin = locations[0];
            const destination = locations[locations.length - 1];
            const waypoints = [];
            
            for (let i = 1; i < locations.length - 1; i++) {
                waypoints.push({
                    location: locations[i],
                    stopover: true
                });
            }

            const isOptimized = document.getElementById('optimize-route').checked;

            const request = {
                origin: origin,
                destination: destination,
                waypoints: waypoints,
                optimizeWaypoints: isOptimized,
                travelMode: google.maps.TravelMode.DRIVING
            };

            directionsService.route(request, (response, status) => {
                document.getElementById('rp-loading').style.display = 'none';

                if (status === google.maps.DirectionsStatus.OK) {
                    directionsRenderer.setDirections(response);
                    
                    // The overview_path gives an interpolated point line approximating the route
                    state.currentRoutePolyline = response.routes[0].overview_path;
                    
                    filterPointsOnRoute();
                } else {
                    alert('Directions request failed due to ' + status);
                    document.getElementById('route-results-list').innerHTML = `
                        <div class="text-center py-20 opacity-50">
                            <i class="fa-solid fa-circle-exclamation text-4xl text-slate-300 mb-4 block"></i>
                            <p class="font-bold text-slate-400">Could not calculate a route between these destinations.</p>
                        </div>`;
                    document.getElementById('stats-header').classList.add('hidden');
                }
            });
        });
    });

    // ── Point Filtering using Google Spherical Geometry ──────────────────────
    // Finds which PO_DATA entries are within the radius of the current route
    // polyline, calculates their closest polyline index for route-order sorting,
    // stores them in state.foundStops, then delegates rendering.
    function filterPointsOnRoute() {
        const resultsList = document.getElementById('route-results-list');
        const countEl     = document.getElementById('stops-found');
        const header      = document.getElementById('stats-header');

        header.classList.remove('hidden');
        resultsList.innerHTML = '';

        // Remove existing markers
        state.activeMarkers.forEach(m => m.setMap(null));
        state.activeMarkers = [];
        state.foundStops    = [];

        const radiusMeters = state.radiusKm * 1000;
        if (!state.currentRoutePolyline || state.currentRoutePolyline.length === 0) return;

        // Compute per-stop: min distance to polyline AND closest polyline index
        PO_DATA.forEach(po => {
            const point = new google.maps.LatLng(parseFloat(po.latitude), parseFloat(po.longitude));
            let minDist  = Infinity;
            let minIdx   = 0;

            for (let i = 0; i < state.currentRoutePolyline.length; i += 2) {
                const stepPoint = state.currentRoutePolyline[i];
                const dist = google.maps.geometry.spherical.computeDistanceBetween(point, stepPoint);
                if (dist < minDist) {
                    minDist = dist;
                    minIdx  = i;
                }
                if (minDist <= radiusMeters) break;
            }

            if (minDist <= radiusMeters) {
                // Attach the closest polyline index for route-order sorting
                state.foundStops.push({ ...po, _routeIdx: minIdx });
            }
        });

        if (state.foundStops.length === 0) {
            resultsList.innerHTML = '<div class="text-center py-10 text-slate-400">No collection targets found along this road path. Try increasing the radius.</div>';
            countEl.textContent = '0 collection targets found';
            return;
        }

        // Build markers once (parallel array to state.foundStops)
        state.foundStops.forEach(s => {
            const isAcq    = parseInt(s.is_acquired);
            const iconUrl  = isAcq
                ? 'https://maps.google.com/mapfiles/ms/icons/green-dot.png'
                : 'https://maps.google.com/mapfiles/ms/icons/orange-dot.png';

            const marker = new google.maps.Marker({
                position: { lat: parseFloat(s.latitude), lng: parseFloat(s.longitude) },
                map: map,
                icon: iconUrl,
                title: s.post_office
            });

            const infoWindow = new google.maps.InfoWindow({
                content: `
                <div style="font-family:Inter,sans-serif; padding:4px;">
                    <strong style="display:block;font-size:13px; margin-bottom:2px;">${s.post_office}</strong>
                    <span style="font-size:11px;color:#64748b;">${s.district}, ${s.state}</span>
                    <hr style="margin:8px 0; border:0; border-top:1px solid #eee;">
                    <span class="rp-badge" style="background:${isAcq ? '#dcfce7' : '#fef3c7'}; color:${isAcq ? '#166534' : '#92400e'}">${isAcq ? 'Collected' : 'To Collect'}</span>
                </div>`
            });

            marker.addListener('click', () => {
                infoWindow.open({ anchor: marker, map, shouldFocus: false });
            });

            // Store infoWindow reference for focus-btn
            marker._infoWindow = infoWindow;
            state.activeMarkers.push(marker);
        });

        renderResults();
    }

    // ── Render / Re-render cards from state ──────────────────────────────────
    // Called after filterPointsOnRoute() and also by the sort/hide controls.
    function renderResults() {
        const resultsList = document.getElementById('route-results-list');
        const countEl     = document.getElementById('stops-found');

        // --- Sort a working copy ---
        let sorted = [...state.foundStops];

        if (state.sortMode === 'route') {
            sorted.sort((a, b) => a._routeIdx - b._routeIdx);
        } else if (state.sortMode === 'name') {
            sorted.sort((a, b) => a.post_office.localeCompare(b.post_office));
        } else if (state.sortMode === 'state') {
            sorted.sort((a, b) => a.state.localeCompare(b.state) || a.district.localeCompare(b.district));
        } else if (state.sortMode === 'seeking') {
            // Seeking (0) first, then acquired (1)
            sorted.sort((a, b) => parseInt(a.is_acquired) - parseInt(b.is_acquired));
        }

        // --- Marker visibility: sync all markers first ---
        state.foundStops.forEach((s, i) => {
            const isAcq    = parseInt(s.is_acquired);
            const visible  = !(state.hideCollected && isAcq);
            state.activeMarkers[i].setMap(visible ? map : null);
        });

        // --- Pills (based on total foundStops, not filtered) ---
        const totalAcq   = state.foundStops.filter(s => parseInt(s.is_acquired)).length;
        const totalSeek  = state.foundStops.length - totalAcq;
        document.getElementById('stat-pills').innerHTML = `
            <span class="rp-badge bg-green-100 text-green-700">${totalAcq} Collected</span>
            <span class="rp-badge bg-amber-100 text-amber-700">${totalSeek} Seeking</span>
        `;

        // Visible count
        const visibleCount = state.hideCollected
            ? sorted.filter(s => !parseInt(s.is_acquired)).length
            : sorted.length;
        countEl.textContent = `${visibleCount} of ${state.foundStops.length} targets shown`;

        // --- Render cards ---
        resultsList.innerHTML = '';
        let visibleIdx = 0;

        sorted.forEach(s => {
            const isAcq    = parseInt(s.is_acquired);
            const hidden   = state.hideCollected && isAcq;

            // Find the marker for this stop (look up by original index in foundStops)
            const stopIdx  = state.foundStops.indexOf(s);
            const marker   = state.activeMarkers[stopIdx];

            if (!hidden) visibleIdx++;

            const card = document.createElement('div');
            card.className = `rp-card animate-fade-up ${isAcq ? 'acquired' : 'seeking'}`;
            card.style.animationDelay = `${visibleIdx * 0.04}s`;
            if (hidden) card.setAttribute('data-hidden', '1');

            card.innerHTML = `
                <div class="flex-1">
                    <div class="text-[10px] font-black ${isAcq ? 'text-green-500' : 'text-amber-500'} mb-1">
                        ${isAcq
                            ? '<i class="fa-solid fa-circle-check mr-1"></i>Collected'
                            : `<i class="fa-solid fa-circle-dot mr-1"></i>#${visibleIdx}`}
                    </div>
                    <h3 class="font-bold text-slate-800 text-sm leading-tight">${s.post_office}</h3>
                    <div class="text-[11px] text-slate-500 font-medium mt-0.5">${s.district}, ${s.state} &middot; ${s.pin_code}</div>
                </div>
                <div class="flex flex-col items-end gap-1">
                    ${s.linked_item_title ? `<a href="${SITE_URL}/item/${s.item_link_id}" class="text-[10px] font-bold text-sky-600 hover:underline">VIEW ITEM</a>` : ''}
                    <button class="focus-btn p-2 rounded-full bg-slate-100 text-slate-400 hover:text-sky-500 transition-colors" title="Focus on map">
                        <i class="fa-solid fa-crosshairs"></i>
                    </button>
                </div>
            `;

            card.querySelector('.focus-btn').onclick = () => {
                map.setZoom(12);
                map.panTo(marker.getPosition());
                marker._infoWindow.open({ anchor: marker, map, shouldFocus: false });
            };

            resultsList.appendChild(card);
        });

        if (visibleCount === 0 && state.hideCollected) {
            resultsList.innerHTML += `<div class="text-center py-8 text-slate-400 text-sm"><i class="fa-solid fa-eye-slash mb-2 block text-2xl"></i>All stops on this route have been collected!</div>`;
        }
    }

    // ── Wire up sort + hide controls (runs after DOM ready) ─────────────────
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('rp-sort-select').addEventListener('change', (e) => {
            state.sortMode = e.target.value;
            if (state.foundStops.length) renderResults();
        });

        document.getElementById('rp-hide-collected').addEventListener('change', (e) => {
            state.hideCollected = e.target.checked;
            if (state.foundStops.length) renderResults();
        });
    });
</script>
