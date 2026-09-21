<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modules/auto_backup/AutoBackupService.php';

try {
    $service = new AutoBackupService($pdo, ABSPATH, ABSPATH . '/tmp/auto_backups');
    $result = $service->runBackupCheck();
    if ($result === null) {
        echo "No due backup or another runner holds the lock.\n";
        exit(0);
    }
    if (empty($result['success'])) {
        fwrite(STDERR, "Backup failed. Consult the application error log.\n");
        exit(1);
    }
    echo "Backup completed: " . $result['run_id'] . "\n";
} catch (Throwable $error) {
    $id = bin2hex(random_bytes(8));
    error_log('[' . $id . '] Automatic backup failed: ' . $error->getMessage());
    fwrite(STDERR, "Backup failed (reference $id).\n");
    exit(1);
}
