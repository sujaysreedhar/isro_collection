<?php
/**
 * PPC Import Script for Postmark Atlas
 * 
 * Scrapes the HTML table from praneethsaichunduru.github.io,
 * geocodes addresses via Nominatim, and inserts into postmark_locations.
 * 
 * Usage: Admin Panel → Postmark Atlas → Import KML
 */

if (!defined('SITE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}

global $pdo;

// ─── Configuration ───────────────────────────────────────────
$sourceUrl = 'https://praneethsaichunduru.github.io/ppcsofindia.github.io/list-pincode.html';
$geocodeDelay = 1100; // ms between Nominatim requests (rate limit: 1/sec)

// ─── Download HTML ───────────────────────────────────────────
set_time_limit(0);

$opts = [
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: PostmarkAtlasImporter/1.0\r\n",
        'timeout' => 30,
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
];
$ctx = stream_context_create($opts);
$htmlContent = @file_get_contents($sourceUrl, false, $ctx);
if (!$htmlContent) {
    die("<p style='color:red;font-weight:bold;'>Failed to download HTML from source. Check URL or connectivity.</p>");
}

// ─── Parse HTML Table ────────────────────────────────────────
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));
$xpath = new DOMXPath($dom);

// Find the table rows (skip header)
$rows = $xpath->query('//table[@id="ppcTable"]/tbody/tr');
if (!$rows || $rows->length === 0) {
    // Fallback: try any table
    $rows = $xpath->query('//table/tbody/tr');
}
if (!$rows || $rows->length === 0) {
    $rows = $xpath->query('//table//tr');
}

$totalRows = $rows ? $rows->length : 0;

if ($totalRows === 0) {
    die("<p style='color:red;font-weight:bold;'>No table rows found in the HTML source.</p>");
}

// ─── Parse rows into structured data ─────────────────────────
$entries = [];
foreach ($rows as $row) {
    $cells = $row->getElementsByTagName('td');
    if ($cells->length < 7) continue; // Skip header or malformed rows
    
    // Columns: 0=S.No, 1=Name of PPC, 2=Date, 3=Post Office and Rank, 4=District, 5=State, 6=Pincode, 7=Image
    $entries[] = [
        'ppc_name'    => trim($cells->item(1)->textContent),
        'date_intro'  => trim($cells->item(2)->textContent),
        'post_office' => trim($cells->item(3)->textContent),
        'district'    => trim($cells->item(4)->textContent),
        'state'       => trim($cells->item(5)->textContent),
        'pin_code'    => trim($cells->item(6)->textContent),
    ];
}

$totalEntries = count($entries);

if ($totalEntries === 0) {
    die("<p style='color:red;font-weight:bold;'>Parsed 0 entries from {$totalRows} table rows. Format may have changed.</p>");
}

// ─── Geocode helper (Nominatim) ──────────────────────────────
function geocodeAddress($address) {
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $address,
        'format' => 'json',
        'limit' => 1,
        'countrycodes' => 'in'
    ]);
    
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: PostmarkAtlasImporter/1.0\r\n",
            'timeout' => 10,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ];
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($url, false, $ctx);
    
    if ($response) {
        $data = json_decode($response, true);
        if (!empty($data) && isset($data[0]['lat'], $data[0]['lon'])) {
            return [
                'lat' => (float)$data[0]['lat'],
                'lng' => (float)$data[0]['lon']
            ];
        }
    }
    return null;
}

// ─── Geocode via PIN code using India Post API ───────────────
function geocodeByPin($pinCode) {
    $url = "https://api.postalpincode.in/pincode/{$pinCode}";
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: PostmarkAtlasImporter/1.0\r\n",
            'timeout' => 10,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ];
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($url, false, $ctx);
    
    if ($response) {
        $data = json_decode($response, true);
        if (!empty($data[0]['PostOffice'][0])) {
            // This API doesn't return lat/lng, but gives us district/state info
            // We'll use Nominatim with the returned name
            return null;
        }
    }
    return null;
}

// ─── Get existing entries to skip duplicates ─────────────────
$existingStmt = $pdo->query("SELECT pin_code, post_office FROM postmark_locations");
$existingMap = [];
while ($row = $existingStmt->fetch(PDO::FETCH_ASSOC)) {
    $key = strtolower(trim($row['pin_code']) . '|' . trim($row['post_office']));
    $existingMap[$key] = true;
}

// ─── Geocode cache (same PIN = same coords) ─────────────────
$pinCache = [];

// ─── Process & Insert ────────────────────────────────────────
$insertStmt = $pdo->prepare("INSERT INTO postmark_locations (pin_code, post_office, district, state, latitude, longitude, is_acquired) VALUES (?, ?, ?, ?, ?, ?, 0)");

$inserted = 0;
$skipped = 0;
$failed = 0;
$geocoded = 0;

// Start output
echo "<!DOCTYPE html><html><head><title>PPC Import Progress</title>
<style>
body{font-family:'Segoe UI',sans-serif;max-width:960px;margin:40px auto;background:#0f172a;color:#e2e8f0;padding:20px;}
h1{color:#fff;font-size:1.5rem;margin-bottom:.5rem;}
.subtitle{color:#94a3b8;margin-bottom:2rem;}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;}
.stat{background:rgba(255,255,255,0.05);border-radius:12px;padding:1rem;border:1px solid rgba(255,255,255,0.1);text-align:center;}
.stat .num{font-size:2rem;font-weight:800;color:#c4b5fd;}
.stat .label{font-size:0.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:0.1em;}
.log{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:1rem;max-height:500px;overflow-y:auto;font-family:monospace;font-size:0.8rem;line-height:1.8;}
.ok{color:#6ee7b7;} .skip{color:#fbbf24;} .err{color:#f87171;} .info{color:#93c5fd;} .cache{color:#a78bfa;}
a.back{color:#c4b5fd;text-decoration:underline;}
</style></head><body>";
echo "<h1>📍 Importing PPC Locations from HTML Table</h1>";
echo "<p class='subtitle'>Source: <a href='{$sourceUrl}' target='_blank' style='color:#93c5fd;'>{$sourceUrl}</a></p>";
echo "<p>Found <strong>{$totalEntries}</strong> entries. Processing...</p>";
echo "<div class='log'>";

if (ob_get_level()) ob_end_flush();
flush();

foreach ($entries as $i => $entry) {
    $postOffice = $entry['post_office'];
    $pinCode = $entry['pin_code'];
    $district = $entry['district'];
    $state = ucwords(strtolower($entry['state'])); // "DELHI" → "Delhi"
    $ppcName = $entry['ppc_name'];
    
    // Skip if missing required data
    if (empty($postOffice) || empty($pinCode)) {
        echo "<div class='err'>[" . ($i+1) . "/{$totalEntries}] SKIP - Missing data: {$ppcName}</div>";
        $failed++;
        flush();
        continue;
    }
    
    // Skip duplicates (check by pin+post_office)
    $dupeKey = strtolower($pinCode . '|' . $postOffice);
    if (isset($existingMap[$dupeKey])) {
        echo "<div class='skip'>[" . ($i+1) . "/{$totalEntries}] DUPLICATE - {$postOffice} ({$pinCode})</div>";
        $skipped++;
        flush();
        continue;
    }
    
    // Get coordinates
    $lat = null;
    $lng = null;
    
    // Check PIN cache first
    if (isset($pinCache[$pinCode])) {
        $lat = $pinCache[$pinCode]['lat'];
        $lng = $pinCache[$pinCode]['lng'];
        echo "<div class='cache'>[" . ($i+1) . "/{$totalEntries}] CACHED coords for PIN {$pinCode}</div>";
    } else {
        // Geocode: try "PostOffice, District, State, India"
        $searchAddr = "{$postOffice}, {$district}, {$state}, India";
        $geo = geocodeAddress($searchAddr);
        
        if (!$geo) {
            // Fallback: try "District, State, India"
            $geo = geocodeAddress("{$district}, {$state}, India");
        }
        
        if (!$geo) {
            // Fallback: try just PIN code
            $geo = geocodeAddress("{$pinCode}, India");
        }
        
        if ($geo) {
            $lat = $geo['lat'];
            $lng = $geo['lng'];
            $geocoded++;
        }
        
        // Cache the result
        if ($lat && $lng) {
            $pinCache[$pinCode] = ['lat' => $lat, 'lng' => $lng];
        }
        
        // Rate limit for Nominatim
        usleep($geocodeDelay * 1000);
    }
    
    if (!$lat || !$lng) {
        echo "<div class='err'>[" . ($i+1) . "/{$totalEntries}] NO COORDS - {$postOffice} ({$pinCode}, {$district}, {$state})</div>";
        // Insert with 0,0 — can fix later via admin
        $lat = 0;
        $lng = 0;
    }
    
    // Insert
    try {
        $insertStmt->execute([$pinCode, $postOffice, $district, $state, $lat, $lng]);
        $existingMap[$dupeKey] = true;
        $inserted++;
        echo "<div class='ok'>[" . ($i+1) . "/{$totalEntries}] ✓ {$postOffice} ({$pinCode}) → {$lat}, {$lng}</div>";
    } catch (Exception $e) {
        $failed++;
        echo "<div class='err'>[" . ($i+1) . "/{$totalEntries}] DB ERROR - {$postOffice}: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    flush();
}

echo "</div>";

// Summary
echo "<div class='stats' style='margin-top:2rem;'>";
echo "<div class='stat'><div class='num'>{$totalEntries}</div><div class='label'>Total Found</div></div>";
echo "<div class='stat'><div class='num' style='color:#6ee7b7;'>{$inserted}</div><div class='label'>Inserted</div></div>";
echo "<div class='stat'><div class='num' style='color:#fbbf24;'>{$skipped}</div><div class='label'>Duplicates</div></div>";
echo "<div class='stat'><div class='num' style='color:#f87171;'>{$failed}</div><div class='label'>Failed</div></div>";
echo "</div>";

echo "<p style='margin-top:1rem;'><a class='back' href='" . SITE_URL . "/admin/module_page.php?m=postmark_atlas&page=locations'>← Back to Locations Tracker</a></p>";
echo "</body></html>";
