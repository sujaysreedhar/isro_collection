<?php
/**
 * StorageInterface — Abstraction for file storage backends.
 *
 * Both LocalStorage and S3Storage implement this interface so
 * MediaProcessor can work transparently with either backend.
 */
interface StorageInterface {

    /**
     * Store a file at the given path.
     *
     * @param string $storagePath  Relative path within the storage (e.g. "display/img_1_abc.webp")
     * @param string $localFile    Absolute path to the local temp/source file
     * @param string $contentType  Optional MIME type hint
     * @return bool  True on success
     */
    public function put(string $storagePath, string $localFile, string $contentType = ''): bool;

    /**
     * Delete a file from storage.
     *
     * @param string $storagePath  Relative path within the storage
     * @return bool  True on success (or if file didn't exist)
     */
    public function delete(string $storagePath): bool;

    /**
     * Check if a file exists in storage.
     *
     * @param string $storagePath  Relative path within the storage
     * @return bool
     */
    public function exists(string $storagePath): bool;

    /**
     * Get the public URL for a stored file.
     *
     * @param string $storagePath  Relative path within the storage
     * @return string  Absolute URL
     */
    public function url(string $storagePath): string;

    /**
     * Return a human-readable label for this storage backend.
     */
    public function driverName(): string;
}
