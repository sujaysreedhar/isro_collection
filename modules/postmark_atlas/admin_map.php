<?php
// modules/postmark_atlas/admin_map.php
if (!defined('SITE_URL'))
    exit;

global $pdo;

// Fetch Locations for map
$stmt = $pdo->query("SELECT * FROM postmark_locations");
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output as JSON for Leaflet
$jsonLocations = json_encode($locations);
$csrfToken = htmlspecialchars(ensureCsrfToken());
?>

<div class="mb-6 flex justify-between items-end">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Postmark Atlas</h2>
        <p class="text-sm text-gray-500 mt-1">A geographic visualization of your collection. Click a marker to toggle
            acquired status.</p>
    </div>
    <div class="flex gap-3 text-xs font-semibold">
        <span
            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-50 text-green-700 rounded-full border border-green-200">
            <span class="w-2.5 h-2.5 bg-green-500 rounded-full"></span> Collected
        </span>
        <span
            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 text-gray-500 rounded-full border border-gray-200">
            <span class="w-2.5 h-2.5 bg-gray-400 rounded-full"></span> Seeking
        </span>
    </div>
</div>

<!-- Map Container -->
<div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden p-2">
    <div id="atlasMap" style="height: 600px; width: 100%; border-radius: 6px;"></div>
</div>

<!-- Leaflet CSS and JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var map = L.map('atlasMap').setView([20.5937, 78.9629], 5);
        var csrfToken = '<?= $csrfToken ?>';

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
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

        rawData.forEach(function (loc) {
            if (loc.latitude && loc.longitude) {
                var isAcquired = parseInt(loc.is_acquired, 10) === 1;
                var markerTitle = loc.post_office + ' (' + loc.pin_code + ') - ' + loc.district;
                var iconToUse = isAcquired ? greenIcon : greyIcon;

                var marker = L.marker([parseFloat(loc.latitude), parseFloat(loc.longitude)], { icon: iconToUse })
                    .bindTooltip(markerTitle)
                    .addTo(map);

                // Store data on marker for toggle
                marker._locData = loc;
                marker._isAcquired = isAcquired;

                marker.on('click', function () {
                    var m = this;
                    var d = m._locData;
                    var acq = m._isAcquired;

                    var statusBadge = acq
                        ? '<span style="display:inline-block;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:700;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;">✓ Collected</span>'
                        : '<span style="display:inline-block;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:700;background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;">Seeking</span>';

                    var btnLabel = acq ? 'Mark as Not Acquired' : 'Mark as Acquired';
                    var btnColor = acq ? '#dc2626' : '#16a34a';
                    var btnBg = acq ? '#fef2f2' : '#f0fdf4';
                    var btnBorder = acq ? '#fecaca' : '#bbf7d0';

                    var popupHtml = '<div style="font-family:Segoe UI,sans-serif;min-width:220px;">' +
                        '<h3 style="font-size:15px;font-weight:800;margin:0 0 4px 0;color:#111827;">' + d.post_office + '</h3>' +
                        '<p style="margin:0 0 8px 0;color:#6b7280;font-size:13px;">PIN: ' + d.pin_code + ' · ' + d.district + (d.state ? ', ' + d.state : '') + '</p>' +
                        statusBadge +
                        '<hr style="border:none;border-top:1px solid #e5e7eb;margin:10px 0;">' +
                        '<button onclick="toggleAcquired(' + d.id + ',' + (acq ? 0 : 1) + ')" ' +
                        'style="width:100%;padding:8px 12px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700;' +
                        'background:' + btnBg + ';color:' + btnColor + ';border:1px solid ' + btnBorder + ';transition:all 0.15s;"' +
                        'onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1">' +
                        btnLabel + '</button>' +
                        '</div>';

                    m.unbindPopup();
                    m.bindPopup(popupHtml, { maxWidth: 280 }).openPopup();
                });
            }
        });

        // Toggle acquired via AJAX
        window.toggleAcquired = function (id, newStatus) {
            var formData = new FormData();
            formData.append('id', id);
            formData.append('new_status', newStatus);
            formData.append('csrf_token', csrfToken);

            fetch('<?= SITE_URL ?>/modules/postmark_atlas/ajax_toggle.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Reload page to refresh markers
                        window.location.reload();
                    } else {
                        alert('Failed to update: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => {
                    alert('Network error: ' + err.message);
                });
        };
    });
</script>