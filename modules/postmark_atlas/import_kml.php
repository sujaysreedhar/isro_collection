<?php
/**
 * Postmark Atlas — 3-Step Import Wizard
 *
 * Step 1 (GET)          — Landing page, "Start Analysis" button
 * Step 2 (POST prepare) — Fetch both sources, compute diff, preview without
 *                         touching the DB, store plan in session
 * Step 3 (POST confirm) — Apply the stored plan, show results
 */

if (!defined('SITE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}

if (session_status() === PHP_SESSION_NONE) session_start();

global $pdo;

// ─── Helper: fetch URL via cURL (fallback: file_get_contents) ────────────────
if (!function_exists('ppcFetchUrl')) {
    function ppcFetchUrl(string $url): string|false {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_USERAGENT      => 'PostmarkAtlasImporter/3.0',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
            ]);
            $body  = curl_exec($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            if (!$errno && $body !== false && $body !== '') return $body;
        }
        // Fallback
        $ctx = stream_context_create([
            'http' => ['method' => 'GET', 'header' => "User-Agent: PostmarkAtlasImporter/3.0\r\n", 'timeout' => 45],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        return @file_get_contents($url, false, $ctx);
    }
}

$step = $_POST['step'] ?? 'start';

// ═══════════════════════════════════════════════════════════════════════════════
// STEP 2 — PREPARE: fetch, diff, preview (no DB writes)
// ═══════════════════════════════════════════════════════════════════════════════
if ($step === 'prepare') {
    set_time_limit(120);

    $errors = [];
    // Plan arrays — stored in session for Step 3
    $plan = [
        'mark_acquired'  => [],   // [ [id, pin, post_office, ppc_name], ... ]
        'update_name'    => [],   // [ [id, pin, post_office, old_name, new_name], ... ]
        'insert_new'     => [],   // [ [pin, post_office, ppc_name, district, state], ... ]
    ];

    // ── Source 1: Checklist JSON ─────────────────────────────────────────────
    $jsonUrl     = 'https://postcardcollection.sujaysreedhar.com/checklist/checklist-data.json';
    $jsonContent = ppcFetchUrl($jsonUrl);

    if ($jsonContent) {
        $items = json_decode($jsonContent, true) ?? [];
        $chkStmt = $pdo->prepare(
            "SELECT id, post_office, ppc_name, is_acquired
             FROM postmark_locations WHERE pin_code = ?"
        );
        foreach ($items as $item) {
            if (empty($item['collected'])) continue;
            $pin     = (string)($item['pincode']      ?? '');
            $po      = trim($item['post_office']       ?? '');
            $name    = trim($item['name_of_ppc']       ?? '');
            if (empty($pin)) continue;

            $chkStmt->execute([$pin]);
            $rows = $chkStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) continue; // Not in DB yet — HTML import will add it

            // Find best match if multiple locations share PIN
            $row = $rows[0];
            if (count($rows) > 1 && $po !== '') {
                foreach ($rows as $r) {
                    if (strcasecmp((string)$r['post_office'], $po) === 0 || strcasecmp((string)$r['ppc_name'], $name) === 0) {
                        $row = $r;
                        break;
                    }
                }
            }

            if (!$row['is_acquired']) {
                $plan['mark_acquired'][] = [
                    'id'          => $row['id'],
                    'pin'         => $pin,
                    'post_office' => $row['post_office'],
                    'ppc_name'    => $name,
                ];
            }
            // Also update ppc_name if missing
            if (empty($row['ppc_name']) && $name) {
                $plan['update_name'][] = [
                    'id'          => $row['id'],
                    'pin'         => $pin,
                    'post_office' => $row['post_office'],
                    'old_name'    => $row['ppc_name'],
                    'new_name'    => $name,
                ];
            }
        }
    } else {
        $errors[] = "Could not download checklist JSON from {$jsonUrl}. Check that XAMPP has internet access and cURL is enabled.";
    }

    // ── Source 2: PPC HTML List ──────────────────────────────────────────────
    $htmlUrl     = 'https://praneethsaichunduru.github.io/ppcsofindia.github.io/list-pincode.html';
    $htmlContent = ppcFetchUrl($htmlUrl);

    if ($htmlContent) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($htmlContent);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $rows    = $xpath->query('//table//tbody//tr');
        $chkStmt = $pdo->prepare("SELECT id, post_office, ppc_name FROM postmark_locations WHERE pin_code = ?");

        $claimedIdsForUpdate = []; // Track which legacy DB rows we've already "claimed" to update their empty ppc_name

        foreach ($rows as $row) {
            $cells = $xpath->query('td', $row);
            if (!$cells || $cells->length < 7) continue;

            $cell  = fn(int $i) => trim(preg_replace('/\s+/', ' ', $cells->item($i)->textContent ?? ''));
            $name  = $cell(1);
            $po    = $cell(3);
            $dist  = $cell(4);
            $state = $cell(5);
            $pin   = preg_replace('/\D/', '', $cell(6));

            if (strlen($pin) !== 6 || empty($po)) continue;

            $chkStmt->execute([$pin]);
            $dbRows = $chkStmt->fetchAll(PDO::FETCH_ASSOC);

            $exactMatch = false;
            $legacyEmptyMatch = null;

            foreach ($dbRows as $dbRow) {
                // Must match PO string (case-insensitive for safety)
                if (strcasecmp($dbRow['post_office'], $po) !== 0) continue;

                if (strcasecmp((string)$dbRow['ppc_name'], $name) === 0) {
                    $exactMatch = true;
                    break;
                }

                if (empty($dbRow['ppc_name']) && !in_array($dbRow['id'], $claimedIdsForUpdate)) {
                    $legacyEmptyMatch = $dbRow;
                }
            }

            if ($exactMatch) {
                // Already perfectly synced in DB. Check next HTML row.
                continue;
            } else if ($legacyEmptyMatch && $name) {
                // Found an existing row with same PIN+PO but empty PPC name
                $claimedIdsForUpdate[] = $legacyEmptyMatch['id'];
                $plan['update_name'][] = [
                    'id'          => $legacyEmptyMatch['id'],
                    'pin'         => $pin,
                    'post_office' => $po,
                    'old_name'    => '',
                    'new_name'    => $name,
                ];
            } else {
                // Either no PIN+PO matched, OR the PIN+PO matched but all its DB rows 
                // already have a DIFFERENT ppc_name! This means it's a multi-PPC post office (like Kolkata GPO).
                // Or we already claimed the empty legacy row for a previous iteration.
                // It must be a new record!
                $plan['insert_new'][] = [
                    'pin'         => $pin,
                    'post_office' => $po,
                    'ppc_name'    => $name,
                    'district'    => $dist,
                    'state'       => $state,
                ];
            }
        }
    } else {
        $errors[] = "Could not download PPC HTML list from {$htmlUrl}. Check internet access.";
    }

    // Remove duplicate update_name entries (same id)
    $seenIds = [];
    $plan['update_name'] = array_filter($plan['update_name'], function($u) use (&$seenIds) {
        if (in_array($u['id'], $seenIds)) return false;
        $seenIds[] = $u['id'];
        return true;
    });
    $plan['update_name'] = array_values($plan['update_name']);

    $_SESSION['ppc_import_plan']   = $plan;
    $_SESSION['ppc_import_errors'] = $errors;

    // ── Render Step 2 Preview ────────────────────────────────────────────────
    $totalChanges = count($plan['mark_acquired']) + count($plan['update_name']) + count($plan['insert_new']);
    renderWizardPage('preview', $plan, $errors, $totalChanges);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// STEP 3 — CONFIRM: apply stored plan
// ═══════════════════════════════════════════════════════════════════════════════
if ($step === 'confirm') {
    $plan   = $_SESSION['ppc_import_plan']   ?? null;
    $errors = $_SESSION['ppc_import_errors'] ?? [];

    if (!$plan) {
        header('Location: ' . SITE_URL . '/admin/module_page.php?m=postmark_atlas&page=import');
        exit;
    }

    unset($_SESSION['ppc_import_plan'], $_SESSION['ppc_import_errors']);

    $applied = ['acquired' => 0, 'named' => 0, 'inserted' => 0];

    // Apply: mark acquired
    $acqStmt = $pdo->prepare("UPDATE postmark_locations SET is_acquired = 1, ppc_name = COALESCE(NULLIF(?, ''), ppc_name) WHERE id = ?");
    foreach ($plan['mark_acquired'] as $r) {
        $acqStmt->execute([$r['ppc_name'], $r['id']]);
        $applied['acquired']++;
    }

    // Apply: update ppc_name
    $nameStmt = $pdo->prepare("UPDATE postmark_locations SET ppc_name = ? WHERE id = ? AND (ppc_name IS NULL OR ppc_name = '')");
    foreach ($plan['update_name'] as $r) {
        $nameStmt->execute([$r['new_name'], $r['id']]);
        $applied['named']++;
    }

    // Apply: insert new
    $insStmt = $pdo->prepare(
        "INSERT INTO postmark_locations (pin_code, post_office, ppc_name, district, state, is_acquired)
         VALUES (?, ?, ?, ?, ?, 0)"
    );
    foreach ($plan['insert_new'] as $r) {
        $insStmt->execute([$r['pin'], $r['post_office'], $r['ppc_name'], $r['district'], $r['state']]);
        $applied['inserted']++;
    }

    $total      = $pdo->query("SELECT COUNT(*) FROM postmark_locations")->fetchColumn();
    $totalAcq   = $pdo->query("SELECT COUNT(*) FROM postmark_locations WHERE is_acquired = 1")->fetchColumn();

    renderWizardPage('done', ['applied' => $applied, 'total' => $total, 'totalAcq' => $totalAcq], $errors, 0);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// STEP 1 — START (default)
// ═══════════════════════════════════════════════════════════════════════════════
unset($_SESSION['ppc_import_plan'], $_SESSION['ppc_import_errors']);
renderWizardPage('start', [], [], 0);
exit;

// ═══════════════════════════════════════════════════════════════════════════════
// Renderer
// ═══════════════════════════════════════════════════════════════════════════════
function renderWizardPage(string $view, array $data, array $errors, int $totalChanges): void {
    $accentColor = ['start' => '#c4b5fd', 'preview' => '#fbbf24', 'done' => '#6ee7b7'];
    $stepLabels  = ['start' => '1', 'preview' => '2', 'done' => '3'];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Import Wizard — Postmark Atlas</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
    body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; }
    .card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; }
    .stat { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 1.25rem; text-align: center; }
    .stat .num { font-size: 2.2rem; font-weight: 800; }
    .stat .lbl { font-size: 0.7rem; color: #64748b; text-transform: uppercase; letter-spacing: .1em; margin-top: 4px; }
    .log { max-height: 340px; overflow-y: auto; font-family: monospace; font-size: 0.8rem; line-height: 1.9; }
    .log::-webkit-scrollbar { width: 4px; } .log::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
    .step-dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .85rem; font-weight: 700; }
    .step-active { background: <?= $accentColor[$view] ?? '#c4b5fd' ?>; color: #0f172a; }
    .step-done   { background: #16a34a; color: #fff; }
    .step-todo   { background: rgba(255,255,255,0.08); color: #475569; }
    table.diff { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    table.diff th { background: rgba(255,255,255,0.06); color: #94a3b8; text-transform: uppercase; font-size: 0.68rem; letter-spacing: .08em; padding: 8px 12px; text-align: left; position: sticky; top: 0; }
    table.diff td { padding: 7px 12px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #cbd5e1; vertical-align: top; }
    .badge-new { background: rgba(96,165,250,0.15); color: #60a5fa; border-radius: 4px; padding: 1px 6px; font-size: .7rem; font-weight: 700; }
    .badge-acq { background: rgba(110,231,183,0.15); color: #6ee7b7; border-radius: 4px; padding: 1px 6px; font-size: .7rem; font-weight: 700; }
    .badge-name { background: rgba(251,191,36,0.15); color: #fbbf24; border-radius: 4px; padding: 1px 6px; font-size: .7rem; font-weight: 700; }
</style>
</head>
<body class="min-h-screen py-10 px-4">
<div class="max-w-4xl mx-auto">

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center gap-4 mb-6">
            <?php
            $steps = ['start' => 'Fetch & Analyse', 'preview' => 'Review Changes', 'done' => 'Applied'];
            $order = ['start', 'preview', 'done'];
            $ci    = array_search($view, $order);
            foreach ($order as $i => $s): ?>
            <div class="flex items-center gap-2">
                <div class="step-dot <?= $i < $ci ? 'step-done' : ($i === $ci ? 'step-active' : 'step-todo') ?>">
                    <?= $i < $ci ? '✓' : ($i + 1) ?>
                </div>
                <span class="text-sm <?= $i === $ci ? 'text-white font-semibold' : 'text-slate-500' ?>"><?= $steps[$s] ?></span>
            </div>
            <?php if ($i < 2): ?><div class="flex-1 h-px bg-slate-700"></div><?php endif; ?>
            <?php endforeach; ?>
        </div>
        <h1 class="text-2xl font-bold text-white">Import &amp; Sync — Postmark Atlas</h1>
        <p class="text-slate-400 text-sm mt-1">Sources: Checklist JSON + PPC India HTML list</p>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="mb-4 p-4 rounded-xl border border-red-800 bg-red-950/40 text-red-300 text-sm flex items-start gap-3">
        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span><?= htmlspecialchars($e) ?></span>
    </div>
    <?php endforeach; ?>

    <?php if ($view === 'start'): ?>

    <!-- ── STEP 1: Start ─────────────────────────────────────────────── -->
    <div class="card p-8 text-center">
        <div class="text-6xl mb-4">🔍</div>
        <h2 class="text-xl font-bold text-white mb-2">Ready to analyse</h2>
        <p class="text-slate-400 text-sm mb-2 max-w-md mx-auto">
            Click <strong class="text-white">Start Analysis</strong> to fetch the latest data from both sources and preview exactly what will change before anything is saved.
        </p>
        <div class="mt-6 mb-8 grid grid-cols-2 gap-4 max-w-sm mx-auto text-left">
            <div class="card p-3">
                <div class="text-xs text-slate-500 mb-1">Source 1</div>
                <div class="text-sm font-medium text-white">Checklist JSON</div>
                <div class="text-xs text-slate-400 mt-0.5">Marks acquired postmarks</div>
            </div>
            <div class="card p-3">
                <div class="text-xs text-slate-500 mb-1">Source 2</div>
                <div class="text-sm font-medium text-white">PPC India HTML List</div>
                <div class="text-xs text-slate-400 mt-0.5">Adds new PPC entries</div>
            </div>
        </div>
        <form method="POST" action="<?= SITE_URL ?>/admin/module_page.php?m=postmark_atlas&page=import">
            <input type="hidden" name="step" value="prepare">
            <button type="submit" class="inline-flex items-center gap-2 px-8 py-3 bg-violet-600 hover:bg-violet-500 text-white font-semibold rounded-xl transition-colors text-base">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Start Analysis
            </button>
        </form>
    </div>

    <?php elseif ($view === 'preview'): ?>

    <!-- ── STEP 2: Preview ───────────────────────────────────────────── -->
    <?php
    $plan    = $data;
    $nAcq    = count($plan['mark_acquired']);
    $nName   = count($plan['update_name']);
    $nNew    = count($plan['insert_new']);
    ?>

    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="stat"><div class="num" style="color:#6ee7b7"><?= $nAcq ?></div><div class="lbl">Mark Acquired</div></div>
        <div class="stat"><div class="num" style="color:#fbbf24"><?= $nName ?></div><div class="lbl">Update PPC Name</div></div>
        <div class="stat"><div class="num" style="color:#60a5fa"><?= $nNew ?></div><div class="lbl">New Entries to Add</div></div>
    </div>

    <?php if ($totalChanges === 0 && empty($errors)): ?>
    <div class="card p-6 text-center text-slate-400 mb-6">
        <div class="text-4xl mb-3">✅</div>
        <div class="font-semibold text-white mb-1">Database is already up to date</div>
        <div class="text-sm">No changes are needed.</div>
    </div>
    <?php else: ?>

    <!-- Acquired -->
    <?php if ($nAcq > 0): ?>
    <div class="card mb-4 overflow-hidden">
        <div class="px-5 py-3 border-b border-white/5 flex items-center gap-3">
            <span class="badge-acq">ACQUIRED</span>
            <span class="text-sm text-slate-300 font-medium"><?= $nAcq ?> postmark(s) will be marked as collected</span>
        </div>
        <div class="log p-2">
        <table class="diff"><thead><tr><th>PIN</th><th>Post Office</th><th>Name of PPC</th></tr></thead><tbody>
        <?php foreach ($plan['mark_acquired'] as $r): ?>
            <tr>
                <td class="text-slate-400 whitespace-nowrap"><?= htmlspecialchars($r['pin']) ?></td>
                <td><?= htmlspecialchars($r['post_office']) ?></td>
                <td class="text-emerald-400"><?= htmlspecialchars($r['ppc_name'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Name updates -->
    <?php if ($nName > 0): ?>
    <div class="card mb-4 overflow-hidden">
        <div class="px-5 py-3 border-b border-white/5 flex items-center gap-3">
            <span class="badge-name">PPC NAME</span>
            <span class="text-sm text-slate-300 font-medium"><?= $nName ?> row(s) will have their PPC name filled in</span>
        </div>
        <div class="log p-2">
        <table class="diff"><thead><tr><th>PIN</th><th>Post Office</th><th>New Name</th></tr></thead><tbody>
        <?php foreach ($plan['update_name'] as $r): ?>
            <tr>
                <td class="text-slate-400 whitespace-nowrap"><?= htmlspecialchars($r['pin']) ?></td>
                <td><?= htmlspecialchars($r['post_office']) ?></td>
                <td class="text-amber-400"><?= htmlspecialchars($r['new_name']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
        </div>
    </div>
    <?php endif; ?>

    <!-- New inserts -->
    <?php if ($nNew > 0): ?>
    <div class="card mb-4 overflow-hidden">
        <div class="px-5 py-3 border-b border-white/5 flex items-center gap-3">
            <span class="badge-new">NEW</span>
            <span class="text-sm text-slate-300 font-medium"><?= $nNew ?> new PPC entries will be inserted (no coordinates yet)</span>
        </div>
        <div class="log p-2">
        <table class="diff"><thead><tr><th>PIN</th><th>Post Office</th><th>Name of PPC</th><th>District</th><th>State</th></tr></thead><tbody>
        <?php foreach ($plan['insert_new'] as $r): ?>
            <tr>
                <td class="text-slate-400 whitespace-nowrap"><?= htmlspecialchars($r['pin']) ?></td>
                <td><?= htmlspecialchars($r['post_office']) ?></td>
                <td class="text-blue-400"><?= htmlspecialchars($r['ppc_name']) ?></td>
                <td class="text-slate-500"><?= htmlspecialchars($r['district']) ?></td>
                <td class="text-slate-500"><?= htmlspecialchars($r['state']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Action bar -->
    <div class="flex items-center justify-between mt-6">
        <a href="<?= SITE_URL ?>/admin/module_page.php?m=postmark_atlas&page=import"
           class="px-5 py-2.5 rounded-xl border border-slate-700 text-slate-300 hover:border-slate-500 hover:text-white transition-colors text-sm font-medium">
            ← Cancel
        </a>
        <form method="POST" action="<?= SITE_URL ?>/admin/module_page.php?m=postmark_atlas&page=import">
            <input type="hidden" name="step" value="confirm">
            <button type="submit"
                    onclick="this.disabled=true;this.textContent='Applying…';this.form.submit();"
                    class="inline-flex items-center gap-2 px-8 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl transition-colors text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Confirm &amp; Apply <?= $totalChanges ?> change<?= $totalChanges !== 1 ? 's' : '' ?>
            </button>
        </form>
    </div>
    <?php endif; // totalChanges > 0 ?>

    <?php elseif ($view === 'done'): ?>

    <!-- ── STEP 3: Done ──────────────────────────────────────────────── -->
    <?php $a = $data['applied']; ?>
    <div class="card p-8 text-center mb-6">
        <div class="text-5xl mb-4">🎉</div>
        <h2 class="text-xl font-bold text-white mb-1">Import complete</h2>
        <p class="text-slate-400 text-sm">All changes have been saved to the database.</p>
    </div>
    <div class="grid grid-cols-3 gap-4 mb-8">
        <div class="stat"><div class="num" style="color:#6ee7b7"><?= $a['acquired'] ?></div><div class="lbl">Marked Acquired</div></div>
        <div class="stat"><div class="num" style="color:#fbbf24"><?= $a['named'] ?></div><div class="lbl">Names Updated</div></div>
        <div class="stat"><div class="num" style="color:#60a5fa"><?= $a['inserted'] ?></div><div class="lbl">New PPCs Inserted</div></div>
    </div>
    <div class="stat mb-6">
        <div class="num" style="color:#fbbf24"><?= $data['totalAcq'] ?> / <?= $data['total'] ?></div>
        <div class="lbl">Total Acquired / Total PPCs in Database</div>
    </div>
    <?php foreach ($errors as $e): ?>
    <div class="mb-3 p-3 rounded-lg bg-yellow-950/40 border border-yellow-800 text-yellow-200 text-xs"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php endif; ?>

    <!-- Footer nav -->
    <div class="mt-8 flex gap-6 text-sm">
        <a href="<?= SITE_URL ?>/admin/module_page.php?m=postmark_atlas&page=locations" class="text-violet-400 hover:text-violet-300">← Locations Tracker</a>
        <a href="<?= SITE_URL ?>/atlas.php" target="_blank" class="text-violet-400 hover:text-violet-300">View Atlas Map →</a>
    </div>
</div>
</body>
</html>
<?php
}
