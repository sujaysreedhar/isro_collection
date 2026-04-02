<?php
// themes/modern_blue/atlas.php
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

    <main class="flex-grow flex flex-col p-4 sm:p-6 lg:p-8 bg-slate-50">
        <div class="max-w-[1600px] mx-auto w-full mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
            <div>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">Geo Atlas</h1>
                <p class="mt-2 text-lg text-slate-500">Visualize our curated geographic collection footprint.</p>
            </div>
            <div class="flex gap-4 text-xs font-semibold bg-white p-3 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center gap-2">
                    <img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png" class="h-6" alt="Green Marker">
                    <span class="text-slate-700">Acquired</span>
                </div>
                <div class="flex items-center gap-2 pl-3 border-l border-slate-200">
                    <img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-grey.png" class="h-6" alt="Grey Marker">
                    <span class="text-slate-400">Seeking</span>
                </div>
            </div>
        </div>
        
        <div class="max-w-[1600px] mx-auto w-full bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200 overflow-hidden flex-grow relative" style="min-height: 70vh;">
            <div id="frontendAtlasMap" class="absolute inset-0 z-0"></div>
        </div>
    </main>

<?php require_once ThemeManager::getFooter(); ?>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var map = L.map('frontendAtlasMap').setView([20.5937, 78.9629], 5);

        // Modern CartoDB Positron base map matching the theme
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
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
                    '<span style="background: #f0fdf4; color: #166534; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; display: inline-block; margin-top: 4px; border: 1px solid #bbf7d0;">✓ In Collection</span>' : 
                    '<span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; display: inline-block; margin-top: 4px;">Seeking</span>';

                var popupHtml = '<div style="font-family: \'Plus Jakarta Sans\', sans-serif; padding: 4px;">' +
                                '<h3 style="font-size:15px; font-weight:800; margin:0 0 4px 0; color: #0f172a;">' + loc.post_office + '</h3>' +
                                '<p style="margin: 0 0 8px 0; color: #64748b; font-size:13px;">' + loc.district + ', ' + loc.state + ' - ' + loc.pin_code + '</p>' +
                                statusHtml +
                                '</div>';

                var iconToUse = isAcquired ? greenIcon : greyIcon;
                
                L.marker([parseFloat(loc.latitude), parseFloat(loc.longitude)], {icon: iconToUse})
                 .bindPopup(popupHtml, {className: 'modern-popup'})
                 .bindTooltip(markerTitle)
                 .addTo(map);
            }
        });
    });
    
    // Add custom popup styling dynamically
    const style = document.createElement('style');
    style.innerHTML = `
        .modern-popup .leaflet-popup-content-wrapper { border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
        .modern-popup .leaflet-popup-tip { box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    `;
    document.head.appendChild(style);
    </script>
