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
            <div class="flex gap-4 text-xs font-semibold bg-white/5 backdrop-blur-xl p-3 rounded-xl border border-white/10 shadow-[0_4px_16px_rgba(0,0,0,0.2)]">
                <div class="flex items-center gap-2">
                    <img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png" class="h-6" alt="Blue Marker">
                    <span class="text-slate-200">Acquired</span>
                </div>
                <div class="flex items-center gap-2 pl-3 border-l border-white/10">
                    <img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-grey.png" class="h-6" alt="Grey Marker">
                    <span class="text-slate-400">Seeking</span>
                </div>
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

        var blueIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
        });

        var greyIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-grey.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
        });

        var rawData = <?= $jsonLocations ?>;
        
        rawData.forEach(function(loc) {
            if (loc.latitude && loc.longitude) {
                var isAcquired = parseInt(loc.is_acquired, 10) === 1;
                var markerTitle = loc.post_office + ' - ' + loc.district;
                
                var statusHtml = isAcquired ? 
                    '<span style="background: rgba(139,92,246,0.2); color: #c4b5fd; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; display: inline-block; margin-top: 4px; border: 1px solid rgba(139,92,246,0.3);">In Collection</span>' : 
                    '<span style="background: rgba(255,255,255,0.05); color: #94a3b8; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; display: inline-block; margin-top: 4px; border: 1px solid rgba(255,255,255,0.1);">Seeking</span>';

                var popupHtml = '<div style="font-family: \'Plus Jakarta Sans\', sans-serif; padding: 4px;">' +
                                '<h3 style="font-size:15px; font-weight:800; margin:0 0 4px 0; color: #f8fafc;">' + loc.post_office + '</h3>' +
                                '<p style="margin: 0 0 8px 0; color: #94a3b8; font-size:13px;">' + loc.district + ', ' + loc.state + ' - ' + loc.pin_code + '</p>' +
                                statusHtml +
                                '</div>';

                var iconToUse = isAcquired ? blueIcon : greyIcon;
                
                L.marker([parseFloat(loc.latitude), parseFloat(loc.longitude)], {icon: iconToUse})
                 .bindPopup(popupHtml, {className: 'glass-popup'})
                 .bindTooltip(markerTitle)
                 .addTo(map);
            }
        });
    });
    
    const style = document.createElement('style');
    style.innerHTML = `
        .glass-popup .leaflet-popup-content-wrapper { border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.85); backdrop-filter: blur(12px); color: #f8fafc; }
        .glass-popup .leaflet-popup-tip { background: rgba(15,23,42,0.85); box-shadow: 0 10px 15px rgba(0,0,0,0.3); }
    `;
    document.head.appendChild(style);
    </script>
