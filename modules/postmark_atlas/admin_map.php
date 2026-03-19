<?php
// modules/postmark_atlas/admin_map.php
if (!defined('SITE_URL')) exit;

global $pdo;

// Fetch Locations for map
$stmt = $pdo->query("SELECT * FROM postmark_locations");
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output as JSON for Leaflet
$jsonLocations = json_encode($locations);
?>

<div class="mb-6 flex justify-between items-end">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Postmark Atlas</h2>
        <p class="text-sm text-gray-500 mt-1">A geographic visualization of your collection.</p>
    </div>
</div>

<!-- Map Container -->
<div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden p-2">
    <div id="atlasMap" style="height: 600px; width: 100%; border-radius: 6px;"></div>
</div>

<!-- Leaflet CSS and JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map
    // Centered roughly on India, as requested by the provided pin/screenshot targets
    var map = L.map('atlasMap').setView([20.5937, 78.9629], 5);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // Custom Icons (Acquired vs Unacquired)
    var redIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
    });

    var greyIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-grey.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
    });

    // Parse Data
    var rawData = <?= $jsonLocations ?>;
    
    // Plot markers
    rawData.forEach(function(loc) {
        if (loc.latitude && loc.longitude) {
            var isAcquired = parseInt(loc.is_acquired, 10) === 1;
            var markerTitle = loc.post_office + ' (' + loc.pin_code + ') - ' + loc.district + ', ' + loc.state;
            
            var popupHtml = '<b>' + loc.post_office + '</b><br>' +
                            'PIN: ' + loc.pin_code + '<br>' +
                            'District: ' + loc.district + '<br>' +
                            'State: ' + loc.state + '<br>' +
                            'Status: <b>' + (isAcquired ? '<span style="color: green;">Acquired</span>' : '<span style="color: red;">Not Acquired</span>') + '</b>';

            var iconToUse = isAcquired ? redIcon : greyIcon;
            
            L.marker([parseFloat(loc.latitude), parseFloat(loc.longitude)], {icon: iconToUse})
             .bindPopup(popupHtml)
             .bindTooltip(markerTitle)
             .addTo(map);
        }
    });
});
</script>
