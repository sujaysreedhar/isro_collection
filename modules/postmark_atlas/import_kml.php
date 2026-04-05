<?php
/**
 * Postmark Atlas — Import & Sync
 *
 * Two sources are combined on every import run:
 *
 * 1. CHECKLIST JSON  (postcardcollection.sujaysreedhar.com)
 *    Marks already-collected postmarks as is_acquired = 1.
 *    Also writes ppc_name from the JSON's name_of_ppc field.
 *
 * 2. PPC HTML LIST  (praneethsaichunduru.github.io)
 *    Scrapes the full list of known PPCs in India and INSERTS
 *    any new ones that don't already exist by pin_code + post_office.
 *    Fills in ppc_name, district, state from the HTML table.
 *    Does NOT overwrite latitude/longitude (set those manually or via KML).
 */

if (!defined('SITE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}

global $pdo;

set_time_limit(120);

$errors    = [];
$log       = [];
$stats     = ['json_checked' => 0, 'json_matched' => 0, 'json_missed' => 0,
              'ppc_found' => 0,  'ppc_inserted' => 0, 'ppc_skipped' => 0];

// ═══════════════════════════════════════════════════════════════════════
// SOURCE 1 — Checklist JSON
// ═══════════════════════════════════════════════════════════════════════
$jsonUrl = 'https://postcardcollection.sujaysreedhar.com/checklist/checklist-data.json';
$opts = [
    'http' => ['method' => 'GET', 'header' => "User-Agent: PostmarkAtlasImporter/2.0\r\n", 'timeout' => 30],
    'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
];
$ctx = stream_context_create($opts);
$jsonContent = @file_get_contents($jsonUrl, false, $ctx);

if ($jsonContent) {
    $items = json_decode($jsonContent, true) ?? [];

    // Update ppc_name for ALL items (not just checked), and set is_acquired for checked ones
    $upsertStmt = $pdo->prepare(
        "UPDATE postmark_locations SET is_acquired = ?, ppc_name = COALESCE(NULLIF(?, ''), ppc_name) WHERE pin_code = ? AND post_office LIKE ?"
    );
    $fuzzyStmt = $pdo->prepare(
        "UPDATE postmark_locations SET is_acquired = ?, ppc_name = COALESCE(NULLIF(?, ''), ppc_name) WHERE pin_code = ?"
    );

    foreach ($items as $item) {
        $pinCode    = (string)($item['pincode']      ?? '');
        $postOffice = trim($item['post_office']       ?? '');
        $ppcName    = trim($item['name_of_ppc']       ?? '');
        $acquired   = !empty($item['collected']) ? 1 : 0;

        if (empty($pinCode)) continue;
        $stats['json_checked']++;

        $upsertStmt->execute([$acquired, $ppcName, $pinCode, $postOffice]);
        if ($upsertStmt->rowCount() > 0) {
            $stats['json_matched']++;
            $icon = $acquired ? '✓' : '·';
            $log[] = ['status' => $acquired ? 'ok' : 'neutral', 'msg' => "{$icon} [{$pinCode}] {$postOffice}" . ($ppcName ? " — {$ppcName}" : '')];
        } else {
            // Fuzzy match by PIN only
            $fuzzyStmt->execute([$acquired, $ppcName, $pinCode]);
            if ($fuzzyStmt->rowCount() > 0) {
                $stats['json_matched']++;
                $log[] = ['status' => 'fuzzy', 'msg' => "~ [{$pinCode}] {$postOffice} — matched by PIN ({$fuzzyStmt->rowCount()} row)"];
            } else {
                $stats['json_missed']++;
                $log[] = ['status' => 'miss', 'msg' => "✗ [{$pinCode}] {$postOffice} — not in DB"];
            }
        }
    }
} else {
    $errors[] = "⚠ Could not download checklist JSON from {$jsonUrl}";
}

// ═══════════════════════════════════════════════════════════════════════
// SOURCE 2 — PPC HTML List (scrape table rows)
// ═══════════════════════════════════════════════════════════════════════
$htmlUrl = 'https://praneethsaichunduru.github.io/ppcsofindia.github.io/list-pincode.html';
$htmlContent = @file_get_contents($htmlUrl, false, $ctx);

if ($htmlContent) {
    // Suppress HTML parse warnings
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($htmlContent);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // Find all <tr> rows inside <tbody>
    $rows = $xpath->query('//table//tbody//tr');
    if (!$rows || $rows->length === 0) {
        // Fallback: any tr with at least 5 tds
        $rows = $xpath->query('//tr[count(td) >= 5]');
    }

    $checkStmt  = $pdo->prepare("SELECT id FROM postmark_locations WHERE pin_code = ? AND post_office LIKE ? LIMIT 1");
    $insertStmt = $pdo->prepare(
        "INSERT INTO postmark_locations (pin_code, post_office, ppc_name, district, state, is_acquired)
         VALUES (?, ?, ?, ?, ?, 0)"
    );
    $updateNameStmt = $pdo->prepare(
        "UPDATE postmark_locations SET ppc_name = ? WHERE id = ? AND (ppc_name IS NULL OR ppc_name = '')"
    );

    foreach ($rows as $row) {
        $cells = $xpath->query('td', $row);
        if (!$cells || $cells->length < 6) continue;

        $getCellText = fn($i) => trim(preg_replace('/\s+/', ' ', $cells->item($i)->textContent ?? ''));

        // Table columns (0-indexed after removing S.No.):
        // 0: S.No  1: Name of PPC  2: Date  3: Post Office and Rank  4: District  5: State  6: Pincode
        if ($cells->length >= 7) {
            $ppcName    = $getCellText(1);
            $postOffice = $getCellText(3);
            $district   = $getCellText(4);
            $state      = $getCellText(5);
            $pinCode    = preg_replace('/\D/', '', $getCellText(6));
        } else {
            continue;
        }

        if (strlen($pinCode) !== 6 || empty($postOffice)) continue;
        $stats['ppc_found']++;

        // Check if this exact pin + post_office already exists
        $checkStmt->execute([$pinCode, $postOffice]);
        $existingId = $checkStmt->fetchColumn();

        if ($existingId) {
            // Update ppc_name if missing
            $updateNameStmt->execute([$ppcName, $existingId]);
            $stats['ppc_skipped']++;
        } else {
            // Insert new row (no coordinates — user sets those via KML or manually)
            $insertStmt->execute([$pinCode, $postOffice, $ppcName, $district, $state]);
            $stats['ppc_inserted']++;
            $log[] = ['status' => 'new', 'msg' => "+ [{$pinCode}] {$postOffice}" . ($ppcName ? " — {$ppcName}" : '') . " ({$state})"];
        }
    }
} else {
    $errors[] = "⚠ Could not download PPC HTML list from {$htmlUrl}";
}

// ═══════════════════════════════════════════════════════════════════════
// Final DB counts
// ═══════════════════════════════════════════════════════════════════════
$totalLocations = $pdo->query("SELECT COUNT(*) FROM postmark_locations")->fetchColumn();
$totalAcquired  = $pdo->query("SELECT COUNT(*) FROM postmark_locations WHERE is_acquired = 1")->fetchColumn();

?><!DOCTYPE html>
<html>
<head>
<title>Import & Sync — Postmark Atlas</title>
<style>
body { font-family: 'Segoe UI', sans-serif; max-width: 1000px; margin: 40px auto; background: #0f172a; color: #e2e8f0; padding: 20px; }
h1 { color: #fff; font-size: 1.5rem; margin-bottom: .25rem; }
h2 { color: #94a3b8; font-size: 1rem; font-weight: 600; margin: 1.5rem 0 .75rem; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: .5rem; }
.subtitle { color: #94a3b8; margin-bottom: 2rem; font-size: .9rem; }
.stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-bottom: 2rem; }
.stat { background: rgba(255,255,255,0.05); border-radius: 12px; padding: 1rem; border: 1px solid rgba(255,255,255,0.1); text-align: center; }
.stat .num { font-size: 1.8rem; font-weight: 800; color: #c4b5fd; }
.stat .label { font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 4px; }
.log { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 1rem; max-height: 420px; overflow-y: auto; font-family: monospace; font-size: 0.82rem; line-height: 2; }
.ok { color: #6ee7b7; } .neutral { color: #94a3b8; } .fuzzy { color: #fbbf24; } .miss { color: #f87171; } .new { color: #60a5fa; }
.error { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.3); border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; color: #fca5a5; font-size: .9rem; }
a.back { color: #c4b5fd; text-decoration: underline; }
</style>
</head><body>

<h1>🔄 Import &amp; Sync Complete</h1>
<p class="subtitle">Synced from checklist JSON + PPC HTML list</p>

<?php foreach ($errors as $err): ?>
<div class="error"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<div class="stats">
    <div class="stat"><div class="num"><?= $stats['json_matched'] ?></div><div class="label">Checklist Updated</div></div>
    <div class="stat"><div class="num" style="color:#f87171"><?= $stats['json_missed'] ?></div><div class="label">Checklist Unmatched</div></div>
    <div class="stat"><div class="num" style="color:#60a5fa"><?= $stats['ppc_inserted'] ?></div><div class="label">New PPCs Added</div></div>
    <div class="stat"><div class="num" style="color:#94a3b8"><?= $stats['ppc_skipped'] ?></div><div class="label">PPCs Already Known</div></div>
    <div class="stat"><div class="num" style="color:#fbbf24"><?= $totalAcquired ?>/<?= $totalLocations ?></div><div class="label">Total Acquired</div></div>
</div>

<h2>📋 Import Log <small style="color:#475569;font-weight:400">(newest rows at bottom)</small></h2>
<div class="log">
<?php foreach ($log as $r): ?>
    <div class="<?= $r['status'] ?>"><?= htmlspecialchars($r['msg']) ?></div>
<?php endforeach; ?>
</div>

<p style="margin-top:1.5rem;">
    <a class="back" href="<?= SITE_URL ?>/admin/module_page.php?m=postmark_atlas&page=locations">← Locations Tracker</a> ·
    <a class="back" href="<?= SITE_URL ?>/atlas.php">View Atlas Map →</a>
</p>
</body></html>
