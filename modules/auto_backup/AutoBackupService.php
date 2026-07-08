<?php

class AutoBackupService
{
    private PDO $pdo;
    private string $projectRoot;
    private string $storageDir;

    public function __construct(PDO $pdo, string $projectRoot, string $storageDir)
    {
        $this->pdo = $pdo;
        $this->projectRoot = rtrim($projectRoot, '/\\');
        $this->storageDir = rtrim($storageDir, '/\\');
    }

    /**
     * Run the backup check. If database or media backups are due, run them.
     */
    public function runBackupCheck(): ?array
    {
        // 1. Check if auto backup is enabled
        if ($this->getSetting('auto_backup_enabled', '1') !== '1') {
            return null;
        }

        $now = time();
        $lastDbRun = (int)$this->getSetting('auto_backup_last_db_run', '0');
        $lastMediaRun = (int)$this->getSetting('auto_backup_last_media_run', '0');

        $dbDue = ($now - $lastDbRun >= 86400) || ($lastDbRun === 0);
        $mediaDue = ($now - $lastMediaRun >= 604800) || ($lastMediaRun === 0);

        if (!$dbDue && !$mediaDue) {
            return null;
        }

        // 2. Try to acquire lock
        if (!$this->acquireLock($now)) {
            return null; // Already running or locked
        }

        try {
            $parts = [];
            if ($dbDue) {
                $parts[] = 'db';
            }
            if ($mediaDue) {
                $parts[] = 'media';
            }

            $result = $this->run($parts, 'auto');

            // 3. Update last run timestamps on success
            if ($result['success']) {
                if ($dbDue) {
                    $this->saveSetting('auto_backup_last_db_run', (string)$now);
                }
                if ($mediaDue) {
                    $this->saveSetting('auto_backup_last_media_run', (string)$now);
                }
                $this->cleanupOldBackups();
            }

            return $result;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Run backup manually or automatically
     */
    public function run(array $parts, string $trigger = 'manual'): array
    {
        if (empty($parts)) {
            return [
                'success' => false,
                'message' => 'No backup parts selected.',
                'parts' => [],
            ];
        }

        $this->ensureStorageDirectory();

        $runId = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $runDir = $this->storageDir . DIRECTORY_SEPARATOR . 'run_' . $runId;

        if (!is_dir($runDir) && !mkdir($runDir, 0755, true) && !is_dir($runDir)) {
            return [
                'success' => false,
                'message' => 'Unable to create backup directory.',
                'parts' => $parts,
            ];
        }

        $artifacts = [];
        $startedAt = gmdate('c');

        try {
            if (in_array('db', $parts, true)) {
                $dbPath = $runDir . DIRECTORY_SEPARATOR . 'database.sql';
                $this->exportDatabase($dbPath);
                $artifacts['db'] = [
                    'filename' => 'database.sql',
                    'bytes' => is_file($dbPath) ? (int)filesize($dbPath) : 0,
                ];
            }

            if (in_array('media', $parts, true)) {
                $mediaPath = $runDir . DIRECTORY_SEPARATOR . 'media.zip';
                $this->createZipFromDirectory(
                    $this->projectRoot . DIRECTORY_SEPARATOR . 'uploads',
                    $mediaPath,
                    ['thumbs'] // Exclude legacy thumbs directory as requested
                );
                $artifacts['media'] = [
                    'filename' => 'media.zip',
                    'bytes' => is_file($mediaPath) ? (int)filesize($mediaPath) : 0,
                ];
            }

            $manifest = [
                'success' => true,
                'trigger' => $trigger,
                'parts' => $parts,
                'run_id' => $runId,
                'run_dir' => $runDir,
                'started_at' => $startedAt,
                'completed_at' => gmdate('c'),
                'artifacts' => $artifacts,
            ];

            file_put_contents(
                $runDir . DIRECTORY_SEPARATOR . 'manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            return $manifest;
        } catch (\Throwable $e) {
            // Cleanup incomplete run dir
            $this->deleteDirectoryRecursive($runDir);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'parts' => $parts,
                'started_at' => $startedAt,
                'completed_at' => gmdate('c'),
            ];
        }
    }

    /**
     * Get all backups available in the folder
     */
    public function getBackupHistory(): array
    {
        if (!is_dir($this->storageDir)) {
            return [];
        }

        $dirs = glob($this->storageDir . DIRECTORY_SEPARATOR . 'run_*', GLOB_ONLYDIR);
        if (!$dirs) {
            return [];
        }

        $history = [];
        foreach ($dirs as $dir) {
            $manifestFile = $dir . DIRECTORY_SEPARATOR . 'manifest.json';
            if (is_file($manifestFile)) {
                $decoded = json_decode((string)file_get_contents($manifestFile), true);
                if (is_array($decoded)) {
                    $decoded['folder_name'] = basename($dir);
                    $history[] = $decoded;
                }
            }
        }

        // Sort descending by completion time or folder name
        usort($history, function ($a, $b) {
            return strcmp($b['folder_name'], $a['folder_name']);
        });

        return $history;
    }

    /**
     * Delete an individual backup folder
     */
    public function deleteBackup(string $folderName): bool
    {
        // Safe check to avoid directory traversal
        if (!preg_match('/^run_\d{8}_\d{6}_[a-f0-9]{8}$/', $folderName)) {
            return false;
        }

        $targetDir = $this->storageDir . DIRECTORY_SEPARATOR . $folderName;
        if (is_dir($targetDir)) {
            return $this->deleteDirectoryRecursive($targetDir);
        }

        return false;
    }

    /**
     * Keep only the 5 most recent backup folders and delete all others.
     */
    public function cleanupOldBackups(): void
    {
        if (!is_dir($this->storageDir)) {
            return;
        }

        $dirs = glob($this->storageDir . DIRECTORY_SEPARATOR . 'run_*', GLOB_ONLYDIR);
        if (!$dirs || count($dirs) <= 5) {
            return;
        }

        // Sort directories alphabetically (runs naturally by date: run_YYYYMMDD_HHMMSS...)
        sort($dirs);

        // We want to keep the last 5, so we delete everything up to count - 5
        $toDeleteCount = count($dirs) - 5;
        for ($i = 0; $i < $toDeleteCount; $i++) {
            $this->deleteDirectoryRecursive($dirs[$i]);
        }
    }

    /**
     * Set a lock so multiple concurrent requests don't trigger backup
     */
    private function acquireLock(int $now): bool
    {
        $lockTime = (int)$this->getSetting('auto_backup_lock', '0');
        // If locked less than 15 minutes ago, skip
        if ($lockTime > 0 && ($now - $lockTime < 900)) {
            return false;
        }

        $this->saveSetting('auto_backup_lock', (string)$now);
        return true;
    }

    /**
     * Release the lock
     */
    private function releaseLock(): void
    {
        $this->saveSetting('auto_backup_lock', '0');
    }

    private function getSetting(string $key, string $default): string
    {
        $stmt = $this->pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :key');
        $stmt->execute([':key' => $key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : $default;
    }

    private function saveSetting(string $key, string $val): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :val)
             ON DUPLICATE KEY UPDATE setting_value = :val2'
        );
        $stmt->execute([':key' => $key, ':val' => $val, ':val2' => $val]);

        // Keep local memory configs synchronized
        $GLOBALS['appSettings'][$key] = $val;
        AppConfig::set($key, $val);
    }

    private function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0755, true) && !is_dir($this->storageDir)) {
            throw new RuntimeException('Unable to create backup storage directory.');
        }

        $htaccess = $this->storageDir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }

        $indexFile = $this->storageDir . DIRECTORY_SEPARATOR . 'index.php';
        if (!is_file($indexFile)) {
            file_put_contents($indexFile, "<?php http_response_code(403); exit;\n");
        }
    }

    private function deleteDirectoryRecursive(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    private function createZipFromDirectory(
        string $sourceDir,
        string $targetPath,
        array $excludeTopLevel = []
    ): void {
        if (!is_dir($sourceDir)) {
            throw new RuntimeException('Backup source directory not found: ' . $sourceDir);
        }

        $zip = new ZipArchive();
        if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create archive: ' . $targetPath);
        }

        $sourceReal = realpath($sourceDir);
        if ($sourceReal === false) {
            $zip->close();
            throw new RuntimeException('Unable to resolve backup source directory.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceReal, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $absolutePath = $item->getPathname();
            $relativePath = str_replace('\\', '/', substr($absolutePath, strlen($sourceReal) + 1));
            if ($relativePath === false || $relativePath === '') {
                continue;
            }

            // Exclude checking
            if ($this->shouldExcludePath($relativePath, $excludeTopLevel)) {
                continue;
            }

            if ($item->isDir()) {
                $zip->addEmptyDir($relativePath);
                continue;
            }

            if (!$zip->addFile($absolutePath, $relativePath)) {
                $zip->close();
                throw new RuntimeException('Unable to add file to archive: ' . $relativePath);
            }
        }

        $zip->close();
    }

    private function shouldExcludePath(string $relativePath, array $excludeTopLevel): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $firstSegment = strtok($relativePath, '/');
        if ($firstSegment !== false && in_array($firstSegment, $excludeTopLevel, true)) {
            return true;
        }
        return false;
    }

    private function exportDatabase(string $targetPath): void
    {
        $tablesStmt = $this->pdo->query('SHOW FULL TABLES');
        $tables = [];
        $views = [];

        while ($row = $tablesStmt->fetch(PDO::FETCH_NUM)) {
            $name = $row[0] ?? null;
            $type = strtoupper((string) ($row[1] ?? 'BASE TABLE'));
            if (!$name) {
                continue;
            }

            if ($type === 'VIEW') {
                $views[] = $name;
            } else {
                $tables[] = $name;
            }
        }

        $handle = fopen($targetPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to write database dump.');
        }

        try {
            fwrite($handle, "-- Automatic Backup database export\n");
            fwrite($handle, '-- Generated: ' . gmdate('Y-m-d H:i:s') . " UTC\n\n");
            fwrite($handle, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
            fwrite($handle, "SET time_zone = '+00:00';\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
            fwrite($handle, "SET NAMES utf8mb4;\n\n");

            foreach ($tables as $table) {
                $this->writeTableDump($handle, $table);
            }

            foreach ($views as $view) {
                $this->writeViewDump($handle, $view);
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        } finally {
            fclose($handle);
        }
    }

    private function writeTableDump($handle, string $table): void
    {
        $quotedTable = $this->quoteIdentifier($table);
        $createStmt = $this->pdo->query("SHOW CREATE TABLE {$quotedTable}");
        $createRow = $createStmt ? $createStmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$createRow) {
            throw new RuntimeException("Unable to read schema for table {$table}.");
        }

        $createSql = $createRow['Create Table'] ?? array_values($createRow)[1] ?? null;
        if (!$createSql) {
            throw new RuntimeException("Missing CREATE TABLE statement for {$table}.");
        }

        fwrite($handle, "-- Table structure for {$table}\n");
        fwrite($handle, "DROP TABLE IF EXISTS {$quotedTable};\n");
        fwrite($handle, $createSql . ";\n\n");

        $rowsStmt = $this->pdo->query("SELECT * FROM {$quotedTable}");
        if (!$rowsStmt) {
            fwrite($handle, "\n");
            return;
        }

        $buffer = [];
        $columns = null;
        while ($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
            if ($columns === null) {
                $columns = array_keys($row);
            }

            $buffer[] = $this->buildInsertTuple($row);
            if (count($buffer) >= 100) {
                $this->flushInsertBuffer($handle, $table, $columns, $buffer);
                $buffer = [];
            }
        }

        if (!empty($buffer) && $columns !== null) {
            $this->flushInsertBuffer($handle, $table, $columns, $buffer);
        }

        fwrite($handle, "\n");
    }

    private function writeViewDump($handle, string $view): void
    {
        $quotedView = $this->quoteIdentifier($view);
        $viewStmt = $this->pdo->query("SHOW CREATE VIEW {$quotedView}");
        $viewRow = $viewStmt ? $viewStmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$viewRow) {
            return;
        }

        $createView = $viewRow['Create View'] ?? array_values($viewRow)[1] ?? null;
        if (!$createView) {
            return;
        }

        fwrite($handle, "-- View structure for {$view}\n");
        fwrite($handle, "DROP VIEW IF EXISTS {$quotedView};\n");
        fwrite($handle, $createView . ";\n\n");
    }

    private function flushInsertBuffer($handle, string $table, array $columns, array $buffer): void
    {
        $quotedColumns = array_map([$this, 'quoteIdentifier'], $columns);
        $sql = 'INSERT INTO ' . $this->quoteIdentifier($table)
            . ' (' . implode(', ', $quotedColumns) . ') VALUES' . "\n"
            . implode(",\n", $buffer)
            . ";\n";

        fwrite($handle, $sql);
    }

    private function buildInsertTuple(array $row): string
    {
        $values = [];
        foreach ($row as $value) {
            if ($value === null) {
                $values[] = 'NULL';
                continue;
            }

            $values[] = $this->pdo->quote((string) $value);
        }

        return '(' . implode(', ', $values) . ')';
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
