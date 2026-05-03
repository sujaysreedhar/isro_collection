<?php

class BackupManagerService
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

    public function run(string $type, string $trigger = 'manual'): array
    {
        $parts = $this->normalizeParts($type);
        if (empty($parts)) {
            return [
                'success' => false,
                'message' => 'Unsupported backup type.',
                'requested_type' => $type,
                'parts' => [],
            ];
        }

        $this->ensureStorageDirectory();

        $runId = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $runDir = $this->storageDir . DIRECTORY_SEPARATOR . $runId;

        if (!is_dir($runDir) && !mkdir($runDir, 0755, true) && !is_dir($runDir)) {
            return [
                'success' => false,
                'message' => 'Unable to create backup directory.',
                'requested_type' => $type,
                'parts' => $parts,
            ];
        }

        $artifacts = [];
        $startedAt = gmdate('c');

        try {
            if (in_array('db', $parts, true)) {
                $dbPath = $runDir . DIRECTORY_SEPARATOR . 'database.sql';
                $this->exportDatabase($dbPath);
                $artifacts['db'] = $this->buildArtifactMeta($dbPath);
            }

            if (in_array('media', $parts, true)) {
                $mediaPath = $runDir . DIRECTORY_SEPARATOR . 'media.zip';
                $this->createZipFromDirectory(
                    $this->projectRoot . DIRECTORY_SEPARATOR . 'uploads',
                    $mediaPath,
                    ['thumbs']
                );
                $artifacts['media'] = $this->buildArtifactMeta($mediaPath);
            }

            if (in_array('app', $parts, true)) {
                $appPath = $runDir . DIRECTORY_SEPARATOR . 'application.zip';
                $this->createApplicationZip($appPath);
                $artifacts['app'] = $this->buildArtifactMeta($appPath);
            }

            $manifest = [
                'success' => true,
                'trigger' => $trigger,
                'requested_type' => $type,
                'parts' => $parts,
                'run_id' => $runId,
                'run_dir' => $runDir,
                'started_at' => $startedAt,
                'completed_at' => gmdate('c'),
                'artifacts' => $artifacts,
            ];

            $this->writeJson($runDir . DIRECTORY_SEPARATOR . 'manifest.json', $manifest);
            $this->writeJson($this->storageDir . DIRECTORY_SEPARATOR . 'latest.json', $manifest);

            return $manifest;
        } catch (\Throwable $e) {
            $failure = [
                'success' => false,
                'trigger' => $trigger,
                'requested_type' => $type,
                'parts' => $parts,
                'run_id' => $runId,
                'run_dir' => $runDir,
                'started_at' => $startedAt,
                'completed_at' => gmdate('c'),
                'message' => $e->getMessage(),
                'artifacts' => $artifacts,
            ];

            $this->writeJson($runDir . DIRECTORY_SEPARATOR . 'manifest.json', $failure);
            $this->writeJson($this->storageDir . DIRECTORY_SEPARATOR . 'latest.json', $failure);

            return $failure;
        }
    }

    public function readLatestStatus(): array
    {
        $latestFile = $this->storageDir . DIRECTORY_SEPARATOR . 'latest.json';
        if (!is_file($latestFile)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($latestFile), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function normalizeParts(string $type): array
    {
        $type = strtolower(trim($type));
        if ($type === '') {
            return [];
        }

        $tokens = preg_split('/[^a-z0-9]+/', $type) ?: [];
        $requested = [];

        foreach ($tokens as $token) {
            if ($token === '' || $token === 'backup' || $token === 'runback') {
                continue;
            }

            if ($token === 'files' || $token === 'full' || $token === 'all') {
                $requested['media'] = true;
                $requested['app'] = true;
                continue;
            }

            if ($token === 'db' || $token === 'database') {
                $requested['db'] = true;
                continue;
            }

            if ($token === 'media' || $token === 'uploads' || $token === 'upload') {
                $requested['media'] = true;
                continue;
            }

            if ($token === 'app' || $token === 'application' || $token === 'code' || $token === 'config') {
                $requested['app'] = true;
            }
        }

        $ordered = [];
        foreach (['db', 'media', 'app'] as $part) {
            if (isset($requested[$part])) {
                $ordered[] = $part;
            }
        }

        return $ordered;
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

    private function buildArtifactMeta(string $absolutePath): array
    {
        return [
            'path' => $absolutePath,
            'filename' => basename($absolutePath),
            'bytes' => is_file($absolutePath) ? (int) filesize($absolutePath) : 0,
        ];
    }

    private function writeJson(string $path, array $payload): void
    {
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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
            fwrite($handle, "-- Collection backup database export\n");
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

    private function createApplicationZip(string $targetPath): void
    {
        $excludeTopLevel = [
            '.git',
            'node_modules',
            'scratch',
            'tmp',
            'uploads',
        ];

        $excludePatterns = [
            'includes/cache/',
        ];

        $this->createZipFromDirectory($this->projectRoot, $targetPath, $excludeTopLevel, $excludePatterns);
    }

    private function createZipFromDirectory(
        string $sourceDir,
        string $targetPath,
        array $excludeTopLevel = [],
        array $excludePatterns = []
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

            if ($this->shouldExcludePath($relativePath, $excludeTopLevel, $excludePatterns)) {
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

    private function shouldExcludePath(string $relativePath, array $excludeTopLevel, array $excludePatterns): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $firstSegment = strtok($relativePath, '/');
        if ($firstSegment !== false && in_array($firstSegment, $excludeTopLevel, true)) {
            return true;
        }

        foreach ($excludePatterns as $pattern) {
            if (str_starts_with($relativePath, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
