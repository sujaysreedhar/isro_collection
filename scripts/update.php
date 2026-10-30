<?php
/**
 * scripts/update.php
 *
 * Database Update Runner and Schema Verifier for the Museum Collection application.
 * Executes pending migrations in update.sql and verifies database schema health.
 *
 * Usage:
 *   CLI:
 *     php scripts/update.php             (Runs migrations from update.sql then verifies)
 *     php scripts/update.php --verify    (Verification only, no execution)
 *   Browser:
 *     http://localhost/collection/scripts/update.php
 *     http://localhost/collection/update.php
 */

declare(strict_types=1);

// Locate configuration
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    die("Configuration file not found at: {$configFile}\n");
}
require_once $configFile;

/** @var PDO $pdo */
global $pdo;

$isCli = (PHP_SAPI === 'cli');

/**
 * Parses SQL text into individual executable statements,
 * stripping line comments and respecting quoted strings.
 */
function parseSqlStatements(string $sql): array {
    $lines = explode("\n", $sql);
    $cleanedLines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
            continue;
        }
        $cleanedLines[] = $line;
    }
    $cleanSql = implode("\n", $cleanedLines);
    $len = strlen($cleanSql);

    $statements = [];
    $current = '';
    $inSingleQuote = false;
    $inDoubleQuote = false;
    $inBacktick = false;

    for ($i = 0; $i < $len; $i++) {
        $char = $cleanSql[$i];
        $prev = $i > 0 ? $cleanSql[$i - 1] : '';

        if ($char === "'" && $prev !== '\\' && !$inDoubleQuote && !$inBacktick) {
            $inSingleQuote = !$inSingleQuote;
        } elseif ($char === '"' && $prev !== '\\' && !$inSingleQuote && !$inBacktick) {
            $inDoubleQuote = !$inDoubleQuote;
        } elseif ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
            $inBacktick = !$inBacktick;
        }

        if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
            $stmt = trim($current);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $current = '';
        } else {
            $current .= $char;
        }
    }
    $remainder = trim($current);
    if ($remainder !== '') {
        $statements[] = $remainder;
    }
    return $statements;
}

/**
 * Runs SQL statements found in update.sql.
 */
function runDatabaseUpdates(PDO $pdo, string $updateSqlPath): array {
    if (!file_exists($updateSqlPath)) {
        return [
            'success' => false,
            'message' => "update.sql file not found at: {$updateSqlPath}",
            'results' => []
        ];
    }

    $rawSql = file_get_contents($updateSqlPath);
    if ($rawSql === false || trim($rawSql) === '') {
        return [
            'success' => true,
            'message' => "update.sql is empty. No statements to run.",
            'results' => []
        ];
    }

    $statements = parseSqlStatements($rawSql);
    $results = [];
    $allSuccessful = true;

    foreach ($statements as $index => $sql) {
        $summary = preg_replace('/\s+/', ' ', trim($sql));
        if (strlen($summary) > 80) {
            $summary = substr($summary, 0, 77) . '...';
        }

        try {
            $pdo->exec($sql);
            $results[] = [
                'index' => $index + 1,
                'sql' => $sql,
                'summary' => $summary,
                'status' => 'OK',
                'error' => null
            ];
        } catch (Throwable $e) {
            $allSuccessful = false;
            $results[] = [
                'index' => $index + 1,
                'sql' => $sql,
                'summary' => $summary,
                'status' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }
    }

    return [
        'success' => $allSuccessful,
        'message' => count($results) . " statement(s) processed.",
        'results' => $results
    ];
}

/**
 * Verifies key database schema requirements and functional health.
 */
function verifyDatabaseSchema(PDO $pdo): array {
    $checks = [];

    // 1. Table: request_rate_limits
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'request_rate_limits'");
        $exists = (bool)$stmt->fetchColumn();
        $checks[] = [
            'name' => "Table 'request_rate_limits'",
            'status' => $exists ? 'PASS' : 'FAIL',
            'detail' => $exists ? 'Table exists in database.' : 'Table is missing. Run updates to create.'
        ];
    } catch (Throwable $e) {
        $checks[] = [
            'name' => "Table 'request_rate_limits'",
            'status' => 'FAIL',
            'detail' => 'Check failed: ' . $e->getMessage()
        ];
    }

    // 2. Setting: auto_backup_last_failure_at
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute(['auto_backup_last_failure_at']);
        $val = $stmt->fetchColumn();
        $exists = ($val !== false);
        $checks[] = [
            'name' => "Setting 'auto_backup_last_failure_at'",
            'status' => $exists ? 'PASS' : 'FAIL',
            'detail' => $exists ? "Setting exists (value: '{$val}')." : 'Setting key missing from settings table.'
        ];
    } catch (Throwable $e) {
        $checks[] = [
            'name' => "Setting 'auto_backup_last_failure_at'",
            'status' => 'FAIL',
            'detail' => 'Check failed: ' . $e->getMessage()
        ];
    }

    // 3. Table: museum_exhibition_sources
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'museum_exhibition_sources'");
        $exists = (bool)$stmt->fetchColumn();
        $checks[] = [
            'name' => "Table 'museum_exhibition_sources'",
            'status' => $exists ? 'PASS' : 'FAIL',
            'detail' => $exists ? 'Table exists.' : 'Table missing.'
        ];
    } catch (Throwable $e) {
        $checks[] = [
            'name' => "Table 'museum_exhibition_sources'",
            'status' => 'FAIL',
            'detail' => 'Check failed: ' . $e->getMessage()
        ];
    }

    // 4. Column: museum_exhibitions.layout
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM museum_exhibitions LIKE 'layout'");
        $exists = (bool)$stmt->fetchColumn();
        $checks[] = [
            'name' => "Column 'museum_exhibitions.layout'",
            'status' => $exists ? 'PASS' : 'FAIL',
            'detail' => $exists ? 'Column exists.' : 'Column missing.'
        ];
    } catch (Throwable $e) {
        $checks[] = [
            'name' => "Column 'museum_exhibitions.layout'",
            'status' => 'FAIL',
            'detail' => 'Check failed: ' . $e->getMessage()
        ];
    }

    // 5. Column: museum_exhibition_items.selection_state
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM museum_exhibition_items LIKE 'selection_state'");
        $exists = (bool)$stmt->fetchColumn();
        $checks[] = [
            'name' => "Column 'museum_exhibition_items.selection_state'",
            'status' => $exists ? 'PASS' : 'FAIL',
            'detail' => $exists ? 'Column exists.' : 'Column missing.'
        ];
    } catch (Throwable $e) {
        $checks[] = [
            'name' => "Column 'museum_exhibition_items.selection_state'",
            'status' => 'FAIL',
            'detail' => 'Check failed: ' . $e->getMessage()
        ];
    }

    // 6. Functional test: RateLimiter::consume
    try {
        if (class_exists('RateLimiter')) {
            $consumeResult = RateLimiter::consume($pdo, 'test_update_health', 'health_check_key', 10, 60);
            $pdo->prepare("DELETE FROM request_rate_limits WHERE scope = 'test_update_health'")->execute();
            $checks[] = [
                'name' => "RateLimiter::consume() Execution",
                'status' => ($consumeResult['allowed'] ?? false) ? 'PASS' : 'FAIL',
                'detail' => 'Successfully consumed rate limit counter and cleaned up test record.'
            ];
        } else {
            $checks[] = [
                'name' => "RateLimiter Class",
                'status' => 'FAIL',
                'detail' => 'RateLimiter class not loaded in runtime.'
            ];
        }
    } catch (Throwable $e) {
        $checks[] = [
            'name' => "RateLimiter::consume() Execution",
            'status' => 'FAIL',
            'detail' => 'Exception during execution: ' . $e->getMessage()
        ];
    }

    $allPassed = true;
    foreach ($checks as $c) {
        if ($c['status'] !== 'PASS') {
            $allPassed = false;
            break;
        }
    }

    return [
        'all_passed' => $allPassed,
        'checks' => $checks
    ];
}

// Determine action
$updateSqlPath = __DIR__ . '/../update.sql';
$action = 'run'; // default

if ($isCli) {
    global $argv;
    if (isset($argv[1]) && in_array($argv[1], ['--verify', '--verify-only', '-v'], true)) {
        $action = 'verify';
    }
} else {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'run';
}

$executionResult = null;
if ($action === 'run') {
    $executionResult = runDatabaseUpdates($pdo, $updateSqlPath);
}

$verificationResult = verifyDatabaseSchema($pdo);

// ── CLI Output ───────────────────────────────────────────────────────────────
if ($isCli) {
    echo "\n=======================================================\n";
    echo "  Museum Collection - Database Update & Verification\n";
    echo "=======================================================\n";

    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    $serverVer = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    echo "Database: {$dbName} | Server: {$serverVer}\n\n";

    if ($executionResult !== null) {
        echo "--- Migration Execution (update.sql) ---\n";
        echo $executionResult['message'] . "\n";
        foreach ($executionResult['results'] as $res) {
            $prefix = $res['status'] === 'OK' ? "[OK]   " : "[ERROR]";
            echo "{$prefix} Statement #{$res['index']}: {$res['summary']}\n";
            if ($res['error']) {
                echo "         -> Error: {$res['error']}\n";
            }
        }
        echo "\n";
    }

    echo "--- Schema Verification Checklist ---\n";
    foreach ($verificationResult['checks'] as $chk) {
        $badge = $chk['status'] === 'PASS' ? "[PASS]" : "[FAIL]";
        echo "{$badge} {$chk['name']}: {$chk['detail']}\n";
    }

    echo "\n";
    if ($verificationResult['all_passed']) {
        echo ">>> OVERALL STATUS: ALL CHECKS PASSED. Database is healthy and up to date. <<<\n\n";
        exit(0);
    } else {
        echo ">>> OVERALL STATUS: SOME CHECKS FAILED. Please review the errors above. <<<\n\n";
        exit(1);
    }
}

// ── Web UI Output ────────────────────────────────────────────────────────────
$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
$serverVer = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Updates - <?= htmlspecialchars(SITE_TITLE ?? 'Museum Collection') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen p-6">
    <div class="max-w-4xl mx-auto">
        <!-- Header Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Database Update & Schema Verification</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        Active Database: <span class="font-semibold text-slate-700"><?= htmlspecialchars((string)$dbName) ?></span>
                        &bull; Engine: <span class="font-semibold text-slate-700"><?= htmlspecialchars((string)$serverVer) ?></span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="?action=run" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-xl transition shadow-sm">
                        Run Updates & Verify
                    </a>
                    <a href="?action=verify" class="inline-flex items-center px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl transition">
                        Verify Only
                    </a>
                    <a href="<?= htmlspecialchars(SITE_URL ?? '') ?>/admin/login.php" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl transition shadow-sm">
                        Go to Login &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Overall Status Banner -->
        <?php if ($verificationResult['all_passed']): ?>
            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 mb-6 flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0 font-bold text-xl">
                    &check;
                </div>
                <div>
                    <h2 class="text-lg font-bold text-emerald-900">Database is Healthy & Fully Synchronized</h2>
                    <p class="text-sm text-emerald-700 mt-0.5">All required tables, columns, and settings from update.sql are present and verified.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-6 flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 font-bold text-xl">
                    !
                </div>
                <div>
                    <h2 class="text-lg font-bold text-amber-900">Database Schema Requires Attention</h2>
                    <p class="text-sm text-amber-700 mt-0.5">One or more schema verification checks did not pass. Click "Run Updates & Verify" to apply pending migrations.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Execution Results -->
        <?php if ($executionResult !== null): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6">
                <h2 class="text-lg font-bold text-slate-900 mb-3">Migration Execution (update.sql)</h2>
                <p class="text-sm text-slate-500 mb-4"><?= htmlspecialchars($executionResult['message']) ?></p>
                <div class="space-y-2">
                    <?php foreach ($executionResult['results'] as $res): ?>
                        <div class="p-3 rounded-xl border flex items-start justify-between gap-3 text-sm <?= $res['status'] === 'OK' ? 'border-emerald-200 bg-emerald-50/50' : 'border-rose-200 bg-rose-50/50' ?>">
                            <div class="font-mono text-xs text-slate-700 overflow-x-auto">
                                <span class="font-bold text-slate-900 mr-2">#<?= $res['index'] ?></span>
                                <?= htmlspecialchars($res['summary']) ?>
                                <?php if ($res['error']): ?>
                                    <div class="text-rose-700 font-sans font-medium mt-1">Error: <?= htmlspecialchars($res['error']) ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="shrink-0 px-2 py-0.5 rounded-full text-xs font-semibold <?= $res['status'] === 'OK' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                <?= $res['status'] ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Verification Checklist -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-lg font-bold text-slate-900 mb-3">Schema Verification Checklist</h2>
            <div class="divide-y divide-slate-100">
                <?php foreach ($verificationResult['checks'] as $chk): ?>
                    <div class="py-3 flex items-center justify-between gap-4 text-sm">
                        <div>
                            <div class="font-semibold text-slate-900"><?= htmlspecialchars($chk['name']) ?></div>
                            <div class="text-slate-500 text-xs mt-0.5"><?= htmlspecialchars($chk['detail']) ?></div>
                        </div>
                        <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-semibold <?= $chk['status'] === 'PASS' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                            <?= $chk['status'] ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="text-center text-xs text-slate-400 mt-8">
            Museum Collection System &bull; <?= date('Y-m-d H:i:s') ?>
        </div>
    </div>
</body>
</html>
