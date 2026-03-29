<?php
/**
 * Checklist Sync Script for Postmark Atlas
 * 
 * Fetches the checklist JSON from postcardcollection.sujaysreedhar.com
 * and marks matching locations as acquired based on checked items.
 * Does NOT insert new rows — only updates existing ones.
 * 
 * Usage: Admin Panel → Postmark Atlas → Import
 */

if (!defined('SITE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}

global $pdo;

// ─── Configuration ───────────────────────────────────────────
$jsonUrl = 'https://postcardcollection.sujaysreedhar.com/checklist/checklist-data.json';

// ─── Download JSON ───────────────────────────────────────────
set_time_limit(0);

$opts = [
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: PostmarkAtlasImporter/1.0\r\n",
        'timeout' => 30,
    ],
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
];
$ctx = stream_context_create($opts);
$jsonContent = @file_get_contents($jsonUrl, false, $ctx);
if (!$jsonContent) {
    die("<p style='color:red;font-weight:bold;'>Failed to download checklist JSON.</p>");
}

$items = json_decode($jsonContent, true);
if (!$items || !is_array($items)) {
    die("<p style='color:red;font-weight:bold;'>Failed to parse JSON data.</p>");
}

// ─── Filter only checked items ───────────────────────────────
$checkedItems = array_filter($items, fn($item) => !empty($item['collected']));
$totalChecked = count($checkedItems);
$totalItems = count($items);

// ─── Match & Update ──────────────────────────────────────────
$updateStmt = $pdo->prepare("UPDATE postmark_locations SET is_acquired = 1 WHERE pin_code = ? AND post_office LIKE ?");

$matched = 0;
$notFound = 0;
$results = [];

foreach ($checkedItems as $item) {
    $pinCode    = (string)($item['pincode'] ?? '');
    $postOffice = trim($item['post_office'] ?? '');
    $ppcName    = trim($item['name_of_ppc'] ?? '');
    
    if (empty($pinCode)) continue;
    
    // Try exact match first
    $updateStmt->execute([$pinCode, $postOffice]);
    $rows = $updateStmt->rowCount();
    
    if ($rows > 0) {
        $matched++;
        $results[] = ['status' => 'ok', 'msg' => "✓ {$postOffice} ({$pinCode}) — {$ppcName}"];
    } else {
        // Try fuzzy match: just by PIN code
        $fuzzyStmt = $pdo->prepare("UPDATE postmark_locations SET is_acquired = 1 WHERE pin_code = ?");
        $fuzzyStmt->execute([$pinCode]);
        $fuzzyRows = $fuzzyStmt->rowCount();
        
        if ($fuzzyRows > 0) {
            $matched++;
            $results[] = ['status' => 'fuzzy', 'msg' => "~ {$postOffice} ({$pinCode}) — matched by PIN code ({$fuzzyRows} row(s))"];
        } else {
            $notFound++;
            $results[] = ['status' => 'miss', 'msg' => "✗ {$postOffice} ({$pinCode}) — {$ppcName} — not found in database"];
        }
    }
}

// Get final counts
$totalLocations = $pdo->query("SELECT COUNT(*) FROM postmark_locations")->fetchColumn();
$totalAcquired = $pdo->query("SELECT COUNT(*) FROM postmark_locations WHERE is_acquired = 1")->fetchColumn();

// ─── Output ──────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html><head><title>Checklist Sync Results</title>
<style>
body{font-family:'Segoe UI',sans-serif;max-width:960px;margin:40px auto;background:#0f172a;color:#e2e8f0;padding:20px;}
h1{color:#fff;font-size:1.5rem;margin-bottom:.5rem;}
.subtitle{color:#94a3b8;margin-bottom:2rem;font-size:.9rem;}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;}
.stat{background:rgba(255,255,255,0.05);border-radius:12px;padding:1rem;border:1px solid rgba(255,255,255,0.1);text-align:center;}
.stat .num{font-size:2rem;font-weight:800;color:#c4b5fd;}
.stat .label{font-size:0.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:0.1em;}
.log{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:1rem;max-height:400px;overflow-y:auto;font-family:monospace;font-size:0.85rem;line-height:2;}
.ok{color:#6ee7b7;} .fuzzy{color:#fbbf24;} .miss{color:#f87171;}
a.back{color:#c4b5fd;text-decoration:underline;}
</style>
</head><body>

<h1>🔄 Checklist Sync Complete</h1>
<p class="subtitle">Synced checked items from <a href="<?= $jsonUrl ?>" target="_blank" style="color:#93c5fd;">your checklist</a> → postmark_locations</p>

<div class="stats">
    <div class="stat"><div class="num"><?= $totalChecked ?></div><div class="label">Checked in Checklist</div></div>
    <div class="stat"><div class="num" style="color:#6ee7b7;"><?= $matched ?></div><div class="label">Matched & Updated</div></div>
    <div class="stat"><div class="num" style="color:#f87171;"><?= $notFound ?></div><div class="label">Not Found</div></div>
    <div class="stat"><div class="num" style="color:#fbbf24;"><?= $totalAcquired ?>/<?= $totalLocations ?></div><div class="label">Total Acquired</div></div>
</div>

<div class="log">
<?php foreach ($results as $r): ?>
    <div class="<?= $r['status'] ?>"><?= htmlspecialchars($r['msg']) ?></div>
<?php endforeach; ?>
</div>

<p style="margin-top:1.5rem;">
    <a class="back" href="<?= SITE_URL ?>/admin/module_page.php?m=postmark_atlas&page=locations">← Locations Tracker</a> · 
    <a class="back" href="<?= SITE_URL ?>/atlas.php">View Atlas Map →</a>
</p>

</body></html>
