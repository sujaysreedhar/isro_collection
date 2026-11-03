<?php
declare(strict_types=1);

class MigrateService
{
    private const EXPORT_VERSION = 1;

    private const CONTENT_TABLES = [
        'categories',
        'tags',
        'items',
        'media',
        'item_category',
        'item_tag',
        'item_related',
        'narratives',
        'item_narrative',
        'collections',
        'collection_items',
        'people',
        'item_people',
        'item_panoramics',
        'seo_meta',
        'navigation_menus',
        'navigation_menu_items',
        'module_themes',
        'module_theme_item',
        'module_sets',
        'module_set_structure',
        'module_set_items',
        'module_storage',
        'module_item_specs_config',
        'module_item_specs',
        'module_valuations',
        'collection_private_details',
        'collection_checklists',
        'collection_checklist_entries',
        'module_exhibition_pages',
        'module_exhibition_items',
        'blog_posts',
        'blog_post_tag',
        'module_wishlist_items',
        'module_wishlist_stores',
        'module_wishlist_price_history',
        'postmark_locations',
    ];

    private const EXCLUDED_USER_TABLES = [
        'admins',
        'api_keys',
        'settings',
        'subscribers',
        'trade_requests',
        'item_comments',
        'user_galleries',
        'user_gallery_items',
    ];

    private const REPLACE_CLEANUP_TABLES = [
        'item_comments',
        'trade_requests',
        'user_gallery_items',
        'user_galleries',
    ];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private PDO $pdo;
    private string $rootPath;
    private string $uploadsPath;
    private string $tempPath;

    public function __construct(PDO $pdo, string $rootPath)
    {
        $this->pdo = $pdo;
        $this->rootPath = rtrim($rootPath, '/\\');
        $this->uploadsPath = $this->rootPath . DIRECTORY_SEPARATOR . 'uploads';
        $this->tempPath = $this->rootPath . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'migrate';
    }

    public static function contentTables(): array
    {
        return self::CONTENT_TABLES;
    }

    public static function excludedUserTables(): array
    {
        return self::EXCLUDED_USER_TABLES;
    }

    public static function sanitizeRowForExport(string $table, array $row): array
    {
        if ($table === 'blog_posts' && array_key_exists('author_id', $row)) {
            $row['author_id'] = null;
        }

        return $row;
    }

    public static function mediaPathsForRows(string $table, array $rows): array
    {
        $paths = [];

        foreach ($rows as $row) {
            if ($table === 'media') {
                $filePath = self::safeBasename((string)($row['file_path'] ?? ''));
                if ($filePath === '') {
                    continue;
                }

                $mediaType = (string)($row['media_type'] ?? 'image');
                if ($mediaType === 'pdf') {
                    self::addPath($paths, 'pdfs/' . $filePath);
                    continue;
                }

                if ($mediaType === 'image') {
                    self::addPath($paths, 'thumbnails/' . $filePath);
                    self::addPath($paths, 'display/' . $filePath);
                    if (preg_match('/^(img_\d+_[a-f0-9]{16})_1200\.webp$/', $filePath, $current)) {
                        foreach ([200, 640] as $size) self::addPath($paths, 'thumbnails/' . $current[1] . '_' . $size . '.webp');
                    }
                    self::addOriginalCandidates($paths, $filePath);
                }
                continue;
            }

            if ($table === 'categories') {
                self::addPath($paths, self::folderFile('categories', (string)($row['image_path'] ?? '')));
            } elseif ($table === 'collections') {
                self::addPath($paths, self::folderFile('display', (string)($row['cover_image'] ?? '')));
            } elseif ($table === 'people') {
                $profile = (string)($row['profile_image'] ?? '');
                if (preg_match('#^original/(person_[a-f0-9]+)\.(jpg|png|gif|webp)$#', $profile, $photo)) {
                    self::addPath($paths, $profile);
                    self::addPath($paths, 'thumbnails/' . $photo[1] . '.webp');
                } else {
                    self::addPath($paths, self::folderFile('display', $profile));
                }
            } elseif ($table === 'item_panoramics') {
                self::addPath($paths, self::folderFile('panoramics', (string)($row['file_path'] ?? '')));
            } elseif ($table === 'blog_posts') {
                self::addPath($paths, (string)($row['featured_image'] ?? ''));
            } elseif ($table === 'module_wishlist_items') {
                $image = self::safeBasename((string)($row['image_url'] ?? ''));
                if ($image !== '') {
                    self::addPath($paths, 'thumbnails/' . $image);
                    self::addPath($paths, 'originals/' . $image);
                    self::addPath($paths, 'original/' . $image);
                }
            } elseif ($table === 'module_sets') {
                self::addMaybeUploadReference($paths, (string)($row['banner_image'] ?? ''));
            } elseif ($table === 'module_exhibition_pages') {
                self::addMaybeUploadReference($paths, (string)($row['banner_image'] ?? ''));
            }
        }

        return array_values(array_unique($paths));
    }

    public static function normalizeArchivePath(string $path, string $prefix): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = str_replace('\\', '/', $prefix);

        if (!str_starts_with($path, $prefix)) {
            throw new InvalidArgumentException('Archive entry is outside the expected directory.');
        }

        $relative = substr($path, strlen($prefix));
        return self::normalizeRelativePath($relative);
    }

    public function overview(): array
    {
        $tables = [];
        $totalRows = 0;

        foreach (self::CONTENT_TABLES as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            $count = (int)$this->pdo->query('SELECT COUNT(*) FROM ' . self::quoteIdentifier($table))->fetchColumn();
            $tables[$table] = $count;
            $totalRows += $count;
        }

        return [
            'tables' => $tables,
            'table_count' => count($tables),
            'row_count' => $totalRows,
            'excluded_tables' => self::EXCLUDED_USER_TABLES,
            'replace_cleanup_tables' => array_values(array_filter(self::REPLACE_CLEANUP_TABLES, fn($table) => $this->tableExists($table))),
            'zip_available' => class_exists('ZipArchive'),
        ];
    }

    public function streamExport(): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('The PHP Zip extension is required to export migration packages.');
        }

        $this->ensureDirectory($this->tempPath);
        $zipPath = $this->tempPath . DIRECTORY_SEPARATOR . 'site-migration-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.zip';
        $summary = $this->createExportPackage($zipPath);
        $downloadName = 'site-migration-' . date('Y-m-d-His') . '.zip';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('X-Migration-Tables: ' . (string)count($summary['tables']));
        readfile($zipPath);
        @unlink($zipPath);
    }

    public function createExportPackage(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create migration zip file.');
        }

        $tables = [];
        $mediaPaths = [];

        foreach (self::CONTENT_TABLES as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            $rows = $this->fetchRows($table);
            $rows = array_map(fn($row) => self::sanitizeRowForExport($table, $row), $rows);
            $tables[$table] = count($rows);

            $zip->addFromString(
                'data/' . $table . '.json',
                json_encode(['table' => $table, 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            foreach (self::mediaPathsForRows($table, $rows) as $path) {
                $mediaPaths[] = $path;
            }
        }

        $mediaPaths = array_values(array_unique($mediaPaths));
        $addedFiles = 0;

        foreach ($mediaPaths as $relativePath) {
            $added = $this->addUploadFileToZip($zip, $relativePath);
            if ($added) {
                $addedFiles++;
            }
        }

        $manifest = [
            'app' => 'collection',
            'export_version' => self::EXPORT_VERSION,
            'created_at' => gmdate('c'),
            'tables' => $tables,
            'media_file_count' => $addedFiles,
            'excluded_user_tables' => self::EXCLUDED_USER_TABLES,
            'notes' => [
                'User/admin accounts, API keys, subscribers, comments, trade requests, user galleries, and settings are not exported.',
                'Blog post author_id values are set to null to avoid migrating admin account references.',
            ],
        ];

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->close();

        return $manifest;
    }

    public function importPackage(string $zipPath, bool $replaceCurrentData): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('The PHP Zip extension is required to import migration packages.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open migration zip file.');
        }

        $manifestJson = $zip->getFromName('manifest.json');
        if ($manifestJson === false) {
            $zip->close();
            throw new RuntimeException('The selected file is missing manifest.json.');
        }

        $manifest = json_decode($manifestJson, true);
        if (!is_array($manifest) || (int)($manifest['export_version'] ?? 0) !== self::EXPORT_VERSION) {
            $zip->close();
            throw new RuntimeException('This migration package version is not supported.');
        }

        $this->ensureDirectory($this->tempPath);
        $stagingPath = $this->tempPath . DIRECTORY_SEPARATOR . 'import-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $this->ensureDirectory($stagingPath);

        $oldUploadPaths = $replaceCurrentData ? $this->currentUploadPaths() : [];
        $stagedFiles = $this->stageFilesFromZip($zip, $stagingPath);
        $importedRows = [];
        $deletedRows = [];

        try {
            $this->pdo->beginTransaction();
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');

            if ($replaceCurrentData) {
                foreach (array_reverse(self::REPLACE_CLEANUP_TABLES) as $table) {
                    if ($this->tableExists($table)) {
                        $deletedRows[$table] = $this->deleteTableRows($table);
                    }
                }

                foreach (array_reverse(self::CONTENT_TABLES) as $table) {
                    if ($this->tableExists($table)) {
                        $deletedRows[$table] = $this->deleteTableRows($table);
                    }
                }
            }

            foreach (self::CONTENT_TABLES as $table) {
                if (!$this->tableExists($table)) {
                    continue;
                }

                $entry = 'data/' . $table . '.json';
                $json = $zip->getFromName($entry);
                if ($json === false) {
                    continue;
                }

                $payload = json_decode($json, true);
                if (!is_array($payload) || !isset($payload['rows']) || !is_array($payload['rows'])) {
                    throw new RuntimeException("Invalid data payload for {$table}.");
                }

                $importedRows[$table] = $this->insertRows($table, $payload['rows'], !$replaceCurrentData);
            }

            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $zip->close();
            $this->removeTree($stagingPath);
            throw $e;
        }

        $zip->close();

        $deletedFiles = $replaceCurrentData ? $this->deleteUploadFiles($oldUploadPaths) : 0;
        $copiedFiles = $this->copyStagedFiles($stagingPath);
        $this->removeTree($stagingPath);

        return [
            'message' => $replaceCurrentData ? 'Migration package imported and current content replaced.' : 'Migration package imported without deleting current content.',
            'mode' => $replaceCurrentData ? 'replace' : 'merge',
            'imported_rows' => $importedRows,
            'deleted_rows' => $deletedRows,
            'copied_files' => $copiedFiles,
            'deleted_files' => $deletedFiles,
            'staged_files' => $stagedFiles,
            'manifest' => $manifest,
        ];
    }

    private function fetchRows(string $table): array
    {
        $order = '';
        $primaryColumns = $this->primaryColumns($table);
        if ($primaryColumns) {
            $order = ' ORDER BY ' . implode(', ', array_map([self::class, 'quoteIdentifier'], $primaryColumns));
        }

        return $this->pdo->query('SELECT * FROM ' . self::quoteIdentifier($table) . $order)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function insertRows(string $table, array $rows, bool $ignoreDuplicates): int
    {
        if (!$rows) {
            return 0;
        }

        $targetColumns = array_flip($this->columns($table));
        $inserted = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $row = self::sanitizeRowForExport($table, $row);
            $row = array_intersect_key($row, $targetColumns);
            if (!$row) {
                continue;
            }

            $columns = array_keys($row);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $verb = $ignoreDuplicates ? 'INSERT IGNORE' : 'INSERT';
            $sql = $verb . ' INTO ' . self::quoteIdentifier($table)
                . ' (' . implode(', ', array_map([self::class, 'quoteIdentifier'], $columns)) . ')'
                . ' VALUES (' . $placeholders . ')';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($row));
            $inserted += $stmt->rowCount();
        }

        return $inserted;
    }

    private function currentUploadPaths(): array
    {
        $paths = [];

        foreach (self::CONTENT_TABLES as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach (self::mediaPathsForRows($table, $this->fetchRows($table)) as $path) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    private function addUploadFileToZip(ZipArchive $zip, string $relativePath): bool
    {
        $relativePath = self::normalizeRelativePath($relativePath);
        $absolutePath = $this->uploadsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!is_file($absolutePath)) {
            return false;
        }

        return $zip->addFile($absolutePath, 'files/' . $relativePath);
    }

    private function stageFilesFromZip(ZipArchive $zip, string $stagingPath): int
    {
        $count = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name) || str_ends_with($name, '/') || !str_starts_with(str_replace('\\', '/', $name), 'files/')) {
                continue;
            }

            $relativePath = self::normalizeArchivePath($name, 'files/');
            $destination = $stagingPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $this->ensureDirectory(dirname($destination));

            $source = $zip->getStream($name);
            if (!$source) {
                throw new RuntimeException("Could not read {$name} from migration package.");
            }

            $target = fopen($destination, 'wb');
            if (!$target) {
                fclose($source);
                throw new RuntimeException("Could not stage {$name}.");
            }

            stream_copy_to_stream($source, $target);
            fclose($source);
            fclose($target);
            $count++;
        }

        return $count;
    }

    private function copyStagedFiles(string $stagingPath): int
    {
        if (!is_dir($stagingPath)) {
            return 0;
        }

        $copied = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stagingPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $absoluteSource = $file->getPathname();
            $relativePath = str_replace('\\', '/', substr($absoluteSource, strlen($stagingPath) + 1));
            $relativePath = self::normalizeRelativePath($relativePath);
            $destination = $this->uploadsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $this->ensureDirectory(dirname($destination));

            if (!copy($absoluteSource, $destination)) {
                throw new RuntimeException("Could not copy uploaded media file {$relativePath}.");
            }
            $copied++;
        }

        return $copied;
    }

    private function deleteUploadFiles(array $paths): int
    {
        $deleted = 0;
        $uploadsRoot = realpath($this->uploadsPath);
        if (!$uploadsRoot) {
            return 0;
        }

        foreach (array_unique($paths) as $relativePath) {
            try {
                $relativePath = self::normalizeRelativePath($relativePath);
            } catch (InvalidArgumentException) {
                continue;
            }

            $absolutePath = $this->uploadsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $realPath = realpath($absolutePath);
            if (!$realPath || !is_file($realPath)) {
                continue;
            }

            if (!str_starts_with($realPath, $uploadsRoot . DIRECTORY_SEPARATOR)) {
                continue;
            }

            if (@unlink($realPath)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function deleteTableRows(string $table): int
    {
        $count = (int)$this->pdo->query('SELECT COUNT(*) FROM ' . self::quoteIdentifier($table))->fetchColumn();
        $this->pdo->exec('DELETE FROM ' . self::quoteIdentifier($table));
        return $count;
    }

    private function tableExists(string $table): bool
    {
        $quotedTable = $this->pdo->quote($table);
        $sql = "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = {$quotedTable}";
        return (int)$this->pdo->query($sql)->fetchColumn() > 0;
    }

    private function columns(string $table): array
    {
        $columns = [];
        foreach ($this->pdo->query('SHOW COLUMNS FROM ' . self::quoteIdentifier($table))->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[] = $column['Field'];
        }
        return $columns;
    }

    private function primaryColumns(string $table): array
    {
        $columns = [];
        $stmt = $this->pdo->query('SHOW KEYS FROM ' . self::quoteIdentifier($table) . " WHERE Key_name = 'PRIMARY'");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $key) {
            $columns[(int)$key['Seq_in_index']] = $key['Column_name'];
        }
        ksort($columns);
        return array_values($columns);
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException("Could not create directory {$path}.");
        }
    }

    private function removeTree(string $path): void
    {
        $root = realpath($this->tempPath);
        $target = realpath($path);
        if (!$root || !$target || !str_starts_with($target, $root . DIRECTORY_SEPARATOR)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($target);
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private static function safeBasename(string $filename): string
    {
        $filename = trim(str_replace('\\', '/', $filename));
        if ($filename === '') {
            return '';
        }
        return basename($filename);
    }

    private static function folderFile(string $folder, string $filename): string
    {
        $filename = self::safeBasename($filename);
        if ($filename === '') {
            return '';
        }
        return $folder . '/' . $filename;
    }

    private static function addOriginalCandidates(array &$paths, string $filePath): void
    {
        self::addPath($paths, 'original/' . $filePath);
        self::addPath($paths, 'originals/' . $filePath);

        $base = preg_match('/^(img_\d+_[a-f0-9]{16})_1200\.webp$/', $filePath, $current) ? $current[1] : pathinfo($filePath, PATHINFO_FILENAME);
        if ($base === '') {
            return;
        }

        foreach (self::IMAGE_EXTENSIONS as $extension) {
            self::addPath($paths, 'original/' . $base . '.' . $extension);
            self::addPath($paths, 'originals/' . $base . '.' . $extension);
        }
    }

    private static function addMaybeUploadReference(array &$paths, string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        if (preg_match('#^https?://#i', $value)) {
            return;
        }

        $normalized = str_replace('\\', '/', $value);
        $normalized = preg_replace('#^/?uploads/#', '', $normalized);

        if (!str_contains($normalized, '/')) {
            $normalized = 'display/' . $normalized;
        }

        self::addPath($paths, $normalized);
    }

    private static function addPath(array &$paths, string $path): void
    {
        $path = trim($path);
        if ($path === '') {
            return;
        }

        try {
            $paths[] = self::normalizeRelativePath($path);
        } catch (InvalidArgumentException) {
            return;
        }
    }

    private static function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, "\0") || str_contains($path, ':')) {
            throw new InvalidArgumentException('Invalid relative path.');
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Invalid relative path.');
            }
        }

        return implode('/', $segments);
    }
}
