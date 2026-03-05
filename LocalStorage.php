<?php
require_once __DIR__ . '/StorageInterface.php';

/**
 * LocalStorage — File storage using the local filesystem (uploads/ directory).
 *
 * This is the default backend and preserves the existing behaviour.
 */
class LocalStorage implements StorageInterface {

    private string $basePath;  // e.g. /path/to/collection/uploads/
    private string $baseUrl;   // e.g. http://localhost/collection/uploads/

    public function __construct(string $basePath, string $baseUrl) {
        $this->basePath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR;
        $this->baseUrl  = rtrim($baseUrl, '/') . '/';
    }

    public function put(string $storagePath, string $localFile, string $contentType = ''): bool {
        $dest = $this->basePath . str_replace('/', DIRECTORY_SEPARATOR, $storagePath);
        $dir  = dirname($dest);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // If source and destination are the same, nothing to do
        if (realpath($localFile) === realpath($dest)) {
            return true;
        }

        return copy($localFile, $dest);
    }

    public function delete(string $storagePath): bool {
        $file = $this->basePath . str_replace('/', DIRECTORY_SEPARATOR, $storagePath);
        if (is_file($file)) {
            return @unlink($file);
        }
        return true; // Already gone
    }

    public function exists(string $storagePath): bool {
        return file_exists($this->basePath . str_replace('/', DIRECTORY_SEPARATOR, $storagePath));
    }

    public function url(string $storagePath): string {
        return $this->baseUrl . str_replace(DIRECTORY_SEPARATOR, '/', $storagePath);
    }

    public function driverName(): string {
        return 'local';
    }
}
