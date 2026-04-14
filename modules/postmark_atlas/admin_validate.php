<?php
// modules/postmark_atlas/admin_validate.php
// Coordinate Validator – cross-checks stored lat/lng against Google Geocoding API.
// No data is loaded until the user selects a State and clicks Validate.

if (!defined('SITE_URL')) exit;

global $pdo;

// ── API Key ───────────────────────────────────────────────────────────────────
$stmtKey = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'route_planner_google_maps_key'");
$gmapsKey = $stmtKey->fetchColumn() ?: '';

// ── Distinct States from DB ───────────────────────────────────────────────────
$stateStmt = $pdo->query("
    SELECT DISTINCT state, COUNT(*) as cnt
    FROM postmark_locations
    WHERE state IS NOT NULL AND state != ''
    GROUP BY state
    ORDER BY state ASC
");
$statesWithCounts = $stateStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Total for info bar ────────────────────────────────────────────────────────
$totalLocations = $pdo->query("SELECT COUNT(*) FROM postmark_locations WHERE latitude != 0 AND longitude != 0")->fetchColumn();
?>

<style>
/* ── Page chrome ─────────────────────────────────────────────────────────── */
.vld-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0f2942 100%);
    border-radius: 16px;
    padding: 28px 32px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.07);
}
.vld-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(56,189,248,0.08) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(99,102,241,0.06) 0%, transparent 60%);
    pointer-events: none;
}
.vld-hero h2 {
    font-size: 1.6rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: -0.02em;
    margin-bottom: 6px;
}
.vld-hero p {
    font-size: 0.9rem;
    color: #94a3b8;
    max-width: 640px;
    line-height: 1.6;
}

/* ── Controls bar ────────────────────────────────────────────────────────── */
.vld-controls {
    display: flex;
    align-items: flex-end;
    gap: 16px;
    flex-wrap: wrap;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 18px 22px;
    margin-bottom: 24px;
    box-shadow: 0 1px 6px rgba(0,0,0,0.05);
}
.vld-field { display: flex; flex-direction: column; gap: 6px; }
.vld-field label {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
}
.vld-select {
    padding: 10px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    background: #f8fafc;
    min-width: 260px;
    outline: none;
    cursor: pointer;
    transition: border-color 0.2s;
}
.vld-select:focus { border-color: #6366f1; }

.vld-threshold {
    padding: 10px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    background: #f8fafc;
    width: 110px;
    outline: none;
    transition: border-color 0.2s;
}
.vld-threshold:focus { border-color: #6366f1; }

.vld-btn-validate {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 26px;
    background: linear-gradient(135deg, #6366f1, #0ea5e9);
    color: #fff;
    font-size: 14px;
    font-weight: 800;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
    white-space: nowrap;
}
.vld-btn-validate:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(99,102,241,0.4);
}
.vld-btn-validate:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    transform: none;
}

/* ── Summary pills ───────────────────────────────────────────────────────── */
#vld-summary {
    display: none;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.vld-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 16px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: -0.01em;
}
.pill-total  { background: #f1f5f9; color: #475569; }
.pill-ok     { background: #dcfce7; color: #166534; }
.pill-warn   { background: #fef9c3; color: #854d0e; }
.pill-bad    { background: #fee2e2; color: #991b1b; }
.pill-skip   { background: #f3f4f6; color: #6b7280; }

/* ── Progress bar ────────────────────────────────────────────────────────── */
#vld-progress-wrap {
    display: none;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 20px;
}
.vld-progress-label {
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
}
.vld-bar-track {
    height: 8px;
    background: #f1f5f9;
    border-radius: 8px;
    overflow: hidden;
}
.vld-bar-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #6366f1, #0ea5e9);
    border-radius: 8px;
    transition: width 0.3s ease;
}

/* ── Results table ───────────────────────────────────────────────────────── */
#vld-results-wrap {
    display: none;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.vld-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.vld-table thead th {
    padding: 12px 14px;
    background: #f8fafc;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748b;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.vld-table tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.12s;
}
.vld-table tbody tr:hover { background: #f8fafc; }
.vld-table td {
    padding: 10px 14px;
    vertical-align: middle;
}

/* Status cell */
.vld-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}
.vld-status.ok    { background: #dcfce7; color: #166534; }
.vld-status.warn  { background: #fef9c3; color: #854d0e; }
.vld-status.bad   { background: #fee2e2; color: #991b1b; }
.vld-status.skip    { background: #f1f5f9; color: #6b7280; }
.vld-status.nocoords { background: #fff7ed; color: #c2410c; }
.vld-status.pending { background: #ede9fe; color: #5b21b6; }
.vld-status.updated { background: #dbeafe; color: #1e40af; }

/* Update button */
.vld-btn-update {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    background: linear-gradient(135deg, #7c3aed, #4f46e5);
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    transition: all 0.18s;
    white-space: nowrap;
    box-shadow: 0 2px 6px rgba(124,58,237,0.25);
}
.vld-btn-update:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(124,58,237,0.35);
}
.vld-btn-update:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    transform: none;
}
.vld-btn-update.done {
    background: linear-gradient(135deg, #059669, #10b981);
    box-shadow: 0 2px 6px rgba(5,150,105,0.25);
}

/* Row highlight after update */
tr.row-updated {
    background: #f0fdf4 !important;
    transition: background 0.4s;
}

/* Bulk update bar */
#vld-bulk-bar {
    display: none;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    border-top: 1px solid #e2e8f0;
    background: #fafafa;
    flex-wrap: wrap;
}
.vld-btn-bulk-update {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 18px;
    background: linear-gradient(135deg, #7c3aed, #0ea5e9);
    color: #fff;
    font-size: 13px;
    font-weight: 800;
    border: none;
    border-radius: 9px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 3px 10px rgba(124,58,237,0.3);
}
.vld-btn-bulk-update:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(124,58,237,0.4); }
.vld-btn-bulk-update:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }

/* Filter tabs */
.vld-filter-tabs {
    display: flex;
    gap: 6px;
    padding: 14px 16px;
    border-bottom: 1px solid #e2e8f0;
    flex-wrap: wrap;
}
.vld-tab {
    padding: 5px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    border: 1.5px solid transparent;
    transition: all 0.15s;
    background: #f1f5f9;
    color: #475569;
}
.vld-tab.active { background: #6366f1; color: #fff; border-color: #6366f1; }
.vld-tab:hover:not(.active) { border-color: #c7d2fe; }

.vld-table-footer {
    padding: 10px 16px;
    background: #f8fafc;
    font-size: 12px;
    color: #94a3b8;
    border-top: 1px solid #e2e8f0;
    font-weight: 600;
}

/* No-API warning */
.vld-no-api {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 12px;
    padding: 16px 20px;
    font-size: 14px;
    color: #92400e;
    margin-bottom: 20px;
}

/* Empty state */
.vld-empty {
    text-align: center;
    padding: 48px 20px;
    color: #94a3b8;
}
.vld-empty i { font-size: 3rem; margin-bottom: 12px; display: block; }
</style>

<!-- Link FontAwesome if not already loaded -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- ── Hero ─────────────────────────────────────────────────────────────────── -->
<div class="vld-hero">
    <h2><i class="fa-solid fa-satellite-dish mr-2 text-sky-400"></i>Coordinate Validator</h2>
    <p>
        Cross-check stored GPS coordinates against Google's Geocoding API — by state.
        Select a state, then click <strong style="color:#e2e8f0">Validate</strong> to start. No data is loaded until you do.
    </p>
    <div style="display:flex; gap:20px; margin-top:18px; flex-wrap:wrap;">
        <div style="font-size:13px; color:#94a3b8;">
            <span style="color:#38bdf8; font-weight:800; font-size:1.3rem;"><?= number_format($totalLocations) ?></span>
            &nbsp;locations with coordinates in DB
        </div>
        <div style="font-size:13px; color:#94a3b8;">
            <span style="color:#a78bfa; font-weight:800; font-size:1.3rem;"><?= count($statesWithCounts) ?></span>
            &nbsp;states / UTs
        </div>
    </div>
</div>

<?php if (empty($gmapsKey)): ?>
<div class="vld-no-api">
    <i class="fa-solid fa-triangle-exclamation mr-2"></i>
    <strong>Google Maps API Key Missing.</strong>
    The Geocoding API requires the same key used for the Route Planner.
    Add <code>route_planner_google_maps_key</code> to your database settings to enable validation.
</div>
<?php endif; ?>

<!-- ── Controls ──────────────────────────────────────────────────────────────── -->
<div class="vld-controls">
    <div class="vld-field">
        <label for="vld-state-select"><i class="fa-solid fa-map mr-1"></i>State / UT</label>
        <select id="vld-state-select" class="vld-select">
            <option value="">— Select a State or UT —</option>
            <?php foreach ($statesWithCounts as $row): ?>
            <option value="<?= htmlspecialchars($row['state']) ?>">
                <?= htmlspecialchars($row['state']) ?> (<?= number_format($row['cnt']) ?> locations)
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="vld-field">
        <label for="vld-threshold"><i class="fa-solid fa-bullseye mr-1"></i>Tolerance (km)</label>
        <input type="number" id="vld-threshold" class="vld-threshold" value="5" min="0.5" max="100" step="0.5" title="Locations within this distance of Google's result are marked OK">
    </div>

    <button id="vld-btn" class="vld-btn-validate" disabled <?= empty($gmapsKey) ? 'title="API key required"' : '' ?>>
        <i class="fa-solid fa-circle-check"></i> Validate Coordinates
    </button>

    <button id="vld-stop-btn" class="vld-btn-validate" style="display:none; background: linear-gradient(135deg,#dc2626,#f97316);">
        <i class="fa-solid fa-stop"></i> Stop
    </button>
</div>

<!-- ── Progress ─────────────────────────────────────────────────────────────── -->
<div id="vld-progress-wrap">
    <div class="vld-progress-label">
        <span id="vld-progress-text">Validating…</span>
        <span id="vld-progress-pct">0%</span>
    </div>
    <div class="vld-bar-track">
        <div id="vld-bar-fill" class="vld-bar-fill"></div>
    </div>
</div>

<!-- ── Summary pills ─────────────────────────────────────────────────────────── -->
<div id="vld-summary" style="display:none;">
    <!-- populated by JS -->
</div>

<!-- ── Results table ─────────────────────────────────────────────────────────── -->
<div id="vld-results-wrap" style="display:none;">
    <div class="vld-filter-tabs" id="vld-tabs">
        <button class="vld-tab active" data-filter="all">All</button>
        <button class="vld-tab" data-filter="ok">✅ OK</button>
        <button class="vld-tab" data-filter="warn">⚠️ Warning</button>
        <button class="vld-tab" data-filter="bad">❌ Far Off</button>
        <button class="vld-tab" data-filter="nocoords">📍 No Coords</button>
        <button class="vld-tab" data-filter="skip">⬜ Skipped</button>
    </div>
    <!-- Bulk update bar — shown after validation completes -->
    <div id="vld-bulk-bar" style="display:none;">
        <span id="vld-bulk-label" style="font-size:13px; font-weight:700; color:#475569;"></span>
        <button id="vld-bulk-update-btn" class="vld-btn-bulk-update" onclick="bulkUpdateFlagged()">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Update All Flagged
        </button>
        <span id="vld-bulk-status" style="font-size:12px; color:#6b7280;"></span>
    </div>
    <div style="overflow-x:auto;">
        <table class="vld-table" id="vld-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Post Office</th>
                    <th>District</th>
                    <th>PIN</th>
                    <th>Stored Coords</th>
                    <th>Google Coords</th>
                    <th>Deviation</th>
                    <th>Status</th>
                    <th>Google Result</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="vld-tbody"></tbody>
        </table>
    </div>
    <div class="vld-table-footer" id="vld-footer">—</div>
</div>

<!-- ── Google Maps + Geocoding JS ─────────────────────────────────────────── -->
<?php if (!empty($gmapsKey)): ?>
<script>
/* ── Constants ── */
const GMAPS_KEY = '<?= htmlspecialchars($gmapsKey) ?>';
const AJAX_URL  = '<?= SITE_URL ?>/admin/ajax.php';
const CSRF_TOKEN = '<?= htmlspecialchars(ensureCsrfToken()) ?>';

/* ── Geocoding via REST (no Maps JS SDK needed for geocoding) ── */
async function geocodeAddress(address) {
    const url = `https://maps.googleapis.com/maps/api/geocode/json?address=${encodeURIComponent(address)}&key=${GMAPS_KEY}`;
    const resp = await fetch(url);
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

/* ── Haversine distance in km ── */
function haversineKm(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2)**2 +
              Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

/* ── State ── */
let locations  = [];
let results    = [];   // { loc, status, deviation, googleLat, googleLng, googleLabel }
let stopped    = false;
let activeFilter = 'all';

/* ── DOM refs ── */
const stateSelect   = document.getElementById('vld-state-select');
const thresholdInput= document.getElementById('vld-threshold');
const validateBtn   = document.getElementById('vld-btn');
const stopBtn       = document.getElementById('vld-stop-btn');
const progressWrap  = document.getElementById('vld-progress-wrap');
const progressText  = document.getElementById('vld-progress-text');
const progressPct   = document.getElementById('vld-progress-pct');
const barFill       = document.getElementById('vld-bar-fill');
const summaryEl     = document.getElementById('vld-summary');
const resultsWrap   = document.getElementById('vld-results-wrap');
const tbody         = document.getElementById('vld-tbody');
const footerEl      = document.getElementById('vld-footer');

/* ── Enable Validate button when a state is selected ── */
stateSelect.addEventListener('change', () => {
    validateBtn.disabled = !stateSelect.value;
});

/* ── Filter tabs ── */
document.getElementById('vld-tabs').addEventListener('click', (e) => {
    const tab = e.target.closest('.vld-tab');
    if (!tab) return;
    document.querySelectorAll('.vld-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    activeFilter = tab.dataset.filter;
    renderTable();
});

/* ── Stop button ── */
stopBtn.addEventListener('click', () => { stopped = true; });

/* ── Main Validate handler ── */
validateBtn.addEventListener('click', async () => {
    const state = stateSelect.value;
    if (!state) return;

    const threshold = parseFloat(thresholdInput.value) || 5;

    // Reset
    stopped = false;
    results = [];
    locations = [];
    tbody.innerHTML = '';
    summaryEl.style.display = 'none';
    resultsWrap.style.display = 'none';

    // Show progress
    progressWrap.style.display = 'block';
    progressText.textContent = `Loading locations for ${state}…`;
    barFill.style.width = '0%';
    progressPct.textContent = '0%';
    validateBtn.disabled = true;
    stopBtn.style.display = 'inline-flex';

    /* ── Load locations via admin AJAX ── */
    let locs;
    try {
        const fd = new FormData();
        fd.append('action', 'get_locations_by_state');
        fd.append('state', state);
        fd.append('csrf_token', CSRF_TOKEN);

        const r = await fetch(AJAX_URL, { method: 'POST', body: fd });
        const data = await r.json();
        if (!data.success) throw new Error(data.error || 'Failed to load locations.');
        locs = data.locations;
    } catch (err) {
        progressText.textContent = `❌ Error: ${err.message}`;
        stopBtn.style.display = 'none';
        validateBtn.disabled = false;
        return;
    }

    if (!locs.length) {
        progressText.textContent = `No locations with coordinates found for "${state}".`;
        stopBtn.style.display = 'none';
        validateBtn.disabled = false;
        return;
    }

    locations = locs;
    progressText.textContent = `Validating 0 / ${locs.length} for ${state}…`;
    resultsWrap.style.display = 'block';

    /* ── Validate each location ── */
    for (let i = 0; i < locs.length; i++) {
        if (stopped) { progressText.textContent = `⛔ Stopped at ${i} of ${locs.length}.`; break; }

        const loc = locs[i];
        const pct = Math.round((i / locs.length) * 100);
        barFill.style.width = `${pct}%`;
        progressPct.textContent = `${pct}%`;
        progressText.textContent = `Checking ${i + 1} / ${locs.length}: ${loc.post_office}…`;

        // Detect whether this location has stored coordinates
        const storedLat = parseFloat(loc.latitude);
        const storedLng = parseFloat(loc.longitude);
        const hasStoredCoords = loc.latitude && loc.longitude
            && !isNaN(storedLat) && !isNaN(storedLng)
            && !(storedLat === 0 && storedLng === 0);

        // Build geocoding query
        const query = `${loc.post_office} Post Office, ${loc.district}, ${loc.state}, India ${loc.pin_code}`;

        let result = { loc, status: 'skip', deviation: null, googleLat: null, googleLng: null, googleLabel: '—' };

        try {
            const geo = await geocodeAddress(query);

            if (geo.status === 'OK' && geo.results.length > 0) {
                const top = geo.results[0];
                const gLat = top.geometry.location.lat;
                const gLng = top.geometry.location.lng;

                result.googleLat   = gLat;
                result.googleLng   = gLng;
                result.googleLabel = top.formatted_address;

                if (!hasStoredCoords) {
                    // No stored coords — can offer to set them
                    result.status    = 'nocoords';
                    result.deviation = null;
                } else {
                    const dist = haversineKm(storedLat, storedLng, gLat, gLng);
                    result.deviation = dist;

                    if (dist <= threshold) {
                        result.status = 'ok';
                    } else if (dist <= threshold * 5) {
                        result.status = 'warn';
                    } else {
                        result.status = 'bad';
                    }
                }
            } else if (geo.status === 'ZERO_RESULTS') {
                result.status      = !hasStoredCoords ? 'nocoords' : 'skip';
                result.googleLabel = 'No result from Google';
            } else {
                result.status      = 'skip';
                result.googleLabel = geo.status;
            }
        } catch (e) {
            result.status      = 'skip';
            result.googleLabel = e.message;
        }

        results.push(result);

        // Append row immediately
        appendRow(result, i);
        updateSummary(threshold);

        // Rate-limit: ~200ms delay to stay within Geocoding API quota
        await new Promise(res => setTimeout(res, 210));
    }

    /* ── Done ── */
    if (!stopped) {
        barFill.style.width = '100%';
        progressPct.textContent = '100%';
        progressText.textContent = `✅ Done — validated ${results.length} location(s) in "${state}".`;
    }

    stopBtn.style.display = 'none';
    validateBtn.disabled = false;
});

/* ── Append a single row to table and respect active filter ── */
function appendRow(result, idx) {
    const { loc, status, deviation, googleLat, googleLng, googleLabel } = result;

    const storedLat = (loc.latitude  && parseFloat(loc.latitude)  !== 0) ? parseFloat(loc.latitude).toFixed(6)  : null;
    const storedLng = (loc.longitude && parseFloat(loc.longitude) !== 0) ? parseFloat(loc.longitude).toFixed(6) : null;
    const storedDisplay = (storedLat && storedLng)
        ? `${storedLat}<br>${storedLng}`
        : '<span style="color:#f97316; font-weight:700; font-size:11px;">⚠ No coordinates</span>';

    const gLat = googleLat !== null ? googleLat.toFixed(6) : '—';
    const gLng = googleLng !== null ? googleLng.toFixed(6) : '—';
    const distStr = deviation !== null ? deviation.toFixed(2) + ' km' : (status === 'nocoords' ? '—' : '—');

    const statusLabels = {
        ok:       '✅ OK',
        warn:     '⚠️ Warning',
        bad:      '❌ Far Off',
        skip:     '⬜ Skipped',
        nocoords: '📍 No Coords'
    };
    const statusLabel = statusLabels[status] ?? status;

    let devColour = '';
    if (status === 'ok')       devColour = 'color:#16a34a; font-weight:700;';
    if (status === 'warn')     devColour = 'color:#d97706; font-weight:700;';
    if (status === 'bad')      devColour = 'color:#dc2626; font-weight:700;';
    if (status === 'nocoords') devColour = 'color:#ea580c; font-weight:700;';

    // Show Update button for any row Google returned coords for
    const canUpdate = (googleLat !== null && status !== 'skip');
    const updateLabel = status === 'nocoords' ? 'Set Coords' : 'Update';
    const updateBtn = canUpdate
        ? `<button class="vld-btn-update" data-id="${loc.id}" data-lat="${googleLat}" data-lng="${googleLng}"
               onclick="updateCoords(this, ${loc.id}, ${googleLat}, ${googleLng})">
               <i class="fa-solid fa-location-crosshairs"></i> ${updateLabel}
           </button>`
        : `<span style="font-size:11px; color:#cbd5e1;">—</span>`;

    const tr = document.createElement('tr');
    tr.dataset.status = status;
    tr.dataset.id = loc.id;
    tr.innerHTML = `
        <td style="color:#94a3b8; font-weight:700; font-size:11px;">${idx + 1}</td>
        <td>
            <div style="font-weight:700; color:#1e293b; font-size:13px;">${escHtml(loc.post_office)}</div>
            ${loc.ppc_name ? `<div style="font-size:11px; color:#64748b;">${escHtml(loc.ppc_name)}</div>` : ''}
        </td>
        <td style="font-size:12px; color:#475569;">${escHtml(loc.district || '—')}</td>
        <td><code style="font-size:11px; background:#f1f5f9; padding:2px 6px; border-radius:4px;">${escHtml(loc.pin_code)}</code></td>
        <td class="stored-coords-cell" style="font-size:11px; font-family:monospace; color:#475569;">
            ${storedDisplay}
        </td>
        <td style="font-size:11px; font-family:monospace; color:#475569;">
            ${googleLat !== null ? `${gLat}<br>${gLng}` : '—'}
        </td>
        <td style="${devColour} font-size:13px;">${distStr}</td>
        <td class="status-cell"><span class="vld-status ${status}">${statusLabel}</span></td>
        <td style="font-size:11px; color:#64748b; max-width:180px; word-break:break-word;">${escHtml(googleLabel)}</td>
        <td>${updateBtn}</td>
    `;

    if (activeFilter !== 'all' && activeFilter !== status) {
        tr.style.display = 'none';
    }

    tbody.appendChild(tr);
    updateFooter();
}

/* ── Update a single row's coordinates ── */
async function updateCoords(btn, id, newLat, newLng) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

    try {
        const fd = new FormData();
        fd.append('action',     'update_postmark_coords');
        fd.append('csrf_token', CSRF_TOKEN);
        fd.append('id',         id);
        fd.append('latitude',   newLat);
        fd.append('longitude',  newLng);

        const r    = await fetch(AJAX_URL, { method: 'POST', body: fd });
        const data = await r.json();

        if (!data.success) throw new Error(data.error || 'Update failed.');

        // Visual feedback on the row
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Updated';
        btn.classList.add('done');
        btn.disabled = true;

        const tr = btn.closest('tr');
        tr.classList.add('row-updated');
        tr.dataset.status = 'updated';

        // Refresh the stored coords cell
        const coordsCell = tr.querySelector('.stored-coords-cell');
        if (coordsCell) {
            coordsCell.innerHTML = `<span style="color:#059669;">${parseFloat(newLat).toFixed(6)}<br>${parseFloat(newLng).toFixed(6)}</span>`;
        }

        // Update status badge
        const statusCell = tr.querySelector('.status-cell');
        if (statusCell) {
            statusCell.innerHTML = '<span class="vld-status updated">✏️ Updated</span>';
        }

        // Also patch the matching result object so summary stays consistent
        const match = results.find(r => String(r.loc.id) === String(id));
        if (match) { match.status = 'updated'; }

    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Update';
        alert('Error: ' + err.message);
    }
}

/* ── Bulk-update all warn + bad + nocoords rows ── */
async function bulkUpdateFlagged() {
    const flagged = results.filter(r =>
        (r.status === 'warn' || r.status === 'bad' || r.status === 'nocoords') && r.googleLat !== null
    );
    if (!flagged.length) { alert('No flagged rows to update.'); return; }
    if (!confirm(`Update coordinates for ${flagged.length} flagged location(s) from Google's results?\n\nThis overwrites stored lat/lng. Locked locations will be skipped by the server.`)) return;

    const bulkBtn = document.getElementById('vld-bulk-update-btn');
    const bulkStatus = document.getElementById('vld-bulk-status');
    bulkBtn.disabled = true;
    bulkStatus.textContent = `Updating 0 / ${flagged.length}…`;

    let done = 0;
    for (const result of flagged) {
        // Find the Update button in the table row and click it programmatically
        const tr = document.querySelector(`#vld-tbody tr[data-id="${result.loc.id}"]`);
        if (!tr) { done++; continue; }
        const btn = tr.querySelector('.vld-btn-update');
        if (!btn || btn.disabled) { done++; continue; }  // already updated

        await updateCoords(btn, result.loc.id, result.googleLat, result.googleLng);
        done++;
        bulkStatus.textContent = `Updated ${done} / ${flagged.length}…`;
        await new Promise(res => setTimeout(res, 80));  // small delay between DB writes
    }

    bulkStatus.textContent = `✅ Done — ${done} location(s) updated.`;
    bulkBtn.disabled = false;
}

/* ── Re-render visible rows based on filter ── */
function renderTable() {
    document.querySelectorAll('#vld-tbody tr').forEach(tr => {
        const match = activeFilter === 'all' || tr.dataset.status === activeFilter;
        tr.style.display = match ? '' : 'none';
    });
    updateFooter();
}

/* ── Footer count ── */
function updateFooter() {
    const visible = document.querySelectorAll('#vld-tbody tr:not([style*="display: none"])').length;
    const total   = results.length;
    footerEl.textContent = `Showing ${visible} of ${total} results`;
}

/* ── Summary pills + bulk-update bar ── */
function updateSummary(threshold) {
    const ok       = results.filter(r => r.status === 'ok').length;
    const warn     = results.filter(r => r.status === 'warn').length;
    const bad      = results.filter(r => r.status === 'bad').length;
    const skip     = results.filter(r => r.status === 'skip').length;
    const nocoords = results.filter(r => r.status === 'nocoords').length;
    const updated  = results.filter(r => r.status === 'updated').length;
    const total    = results.length;
    const flagged  = warn + bad + nocoords;

    summaryEl.style.display = 'flex';
    summaryEl.innerHTML = `
        <span class="vld-pill pill-total"><i class="fa-solid fa-database"></i> ${total} Checked</span>
        <span class="vld-pill pill-ok"><i class="fa-solid fa-circle-check"></i> ${ok} OK (≤${threshold} km)</span>
        <span class="vld-pill pill-warn"><i class="fa-solid fa-triangle-exclamation"></i> ${warn} Warning</span>
        <span class="vld-pill pill-bad"><i class="fa-solid fa-circle-xmark"></i> ${bad} Far Off</span>
        ${nocoords > 0 ? `<span class="vld-pill" style="background:#fff7ed;color:#c2410c;"><i class="fa-solid fa-location-dot"></i> ${nocoords} No Coords</span>` : ''}
        <span class="vld-pill pill-skip"><i class="fa-solid fa-minus"></i> ${skip} Skipped</span>
        ${updated > 0 ? `<span class="vld-pill" style="background:#dbeafe;color:#1e40af;"><i class="fa-solid fa-pen-to-square"></i> ${updated} Updated</span>` : ''}
    `;

    // Bulk-update bar: include nocoords rows that have Google results
    const bulkBar   = document.getElementById('vld-bulk-bar');
    const bulkLabel = document.getElementById('vld-bulk-label');
    if (flagged > 0) {
        bulkBar.style.display = 'flex';
        bulkLabel.textContent = `${flagged} location${flagged !== 1 ? 's' : ''} need attention (${warn} warning, ${bad} far off, ${nocoords} missing coords).`;
    } else {
        bulkBar.style.display = 'none';
    }
}

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>
<?php else: ?>
<script>
// No API key — keep button disabled
document.getElementById('vld-btn').title = 'Google Maps API key is required';
document.getElementById('vld-state-select').addEventListener('change', function() {
    document.getElementById('vld-btn').disabled = true;
});
</script>
<?php endif; ?>
