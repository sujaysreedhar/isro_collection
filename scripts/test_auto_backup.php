<?php
// scripts/test_auto_backup.php

// 1. Setup environment
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modules/auto_backup/AutoBackupService.php';
require_once __DIR__ . '/../modules/auto_backup/module.php';

echo "--- Automatic Backups Testing Script ---\n";

// 2. Ensure module is active in database
$activeModulesJson = $appSettings['active_modules'] ?? '[]';
$activeModules = json_decode($activeModulesJson, true);
if (!is_array($activeModules)) {
    $activeModules = [];
}

if (!in_array('auto_backup', $activeModules, true)) {
    $activeModules[] = 'auto_backup';
    $newActiveJson = json_encode(array_values($activeModules));
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'active_modules'");
    $stmt->execute([$newActiveJson]);
    echo "Activated 'auto_backup' module in settings.\n";
} else {
    echo "Module 'auto_backup' is already active.\n";
}

$storageDir = ABSPATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'auto_backups';
$service = new AutoBackupService($pdo, ABSPATH, $storageDir);

// 3. Reset last run timestamps to 0 to simulate fresh or expired state
$pdo->query("UPDATE settings SET setting_value = '0' WHERE setting_key IN ('auto_backup_last_db_run', 'auto_backup_last_media_run')");
echo "Reset database setting timestamps to 0.\n";

// 4. Run the backup check
echo "Running runBackupCheck()...\n";
$result = $service->runBackupCheck();
if ($result && $result['success']) {
    echo "Backup completed successfully! Run ID: " . $result['run_id'] . "\n";
    echo "Parts generated: " . implode(', ', $result['parts']) . "\n";
} else {
    echo "Backup check did not run or failed. Message: " . ($result['message'] ?? 'No message') . "\n";
}

// 5. Test manual runs to verify pruning (retaining only 5 updates)
echo "\nSimulating multiple manual runs to test retention (keeping only 5 backups)...\n";
for ($i = 1; $i <= 6; $i++) {
    echo "Triggering manual run #$i...\n";
    // For run #6, do db and media to test both
    $parts = ($i === 6) ? ['db', 'media'] : ['db'];
    $res = $service->run($parts, 'test_manual');
    if ($res['success']) {
        echo "Created run: " . $res['run_id'] . " with parts: " . implode(', ', $parts) . "\n";
        if ($i === 6) {
            $runDir = $res['run_dir'];
            $dbFile = $runDir . DIRECTORY_SEPARATOR . 'database.sql';
            $mediaFile = $runDir . DIRECTORY_SEPARATOR . 'media.zip';
            if (is_file($dbFile) && filesize($dbFile) > 0) {
                echo "SUCCESS: database.sql exists and is non-empty (" . filesize($dbFile) . " bytes)\n";
            } else {
                echo "FAILURE: database.sql is missing or empty\n";
            }
            if (is_file($mediaFile) && filesize($mediaFile) > 0) {
                echo "SUCCESS: media.zip exists and is non-empty (" . filesize($mediaFile) . " bytes)\n";
            } else {
                echo "FAILURE: media.zip is missing or empty\n";
            }
        }
    } else {
        echo "Run failed: " . ($res['message'] ?? '') . "\n";
    }
    // Clean up old ones after each run to simulate actual behavior
    $service->cleanupOldBackups();
    // sleep a bit to ensure unique timestamp filenames if needed, though runId includes random bytes
    usleep(100000); 
}

// 6. Check stored folders count
$dirs = glob($storageDir . DIRECTORY_SEPARATOR . 'run_*', GLOB_ONLYDIR);
echo "\nTotal stored runs found: " . count($dirs) . "\n";
foreach ($dirs as $d) {
    echo " - " . basename($d) . "\n";
}

if (count($dirs) === 5) {
    echo "SUCCESS: Pruning logic correctly kept exactly 5 updates.\n";
} else {
    echo "FAILURE: Pruning logic kept " . count($dirs) . " instead of 5.\n";
}

echo "\n--- Test Completed ---\n";
