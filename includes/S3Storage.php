<?php
require_once __DIR__ . '/StorageInterface.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * S3Storage — File storage using AWS S3 (or compatible services).
 *
 * Requires: composer require aws/aws-sdk-php
 */
class S3Storage implements StorageInterface {

    private S3Client $client;
    private string   $bucket;
    private string   $prefix;   // e.g. "collection/uploads"
    private string   $region;

    /**
     * @param array $config Keys: bucket, region, access_key, secret_key, endpoint (optional), path_prefix
     */
    public function __construct(array $config) {
        $this->bucket = $config['bucket'] ?? '';
        $this->region = $config['region'] ?? 'us-east-1';
        $this->prefix = trim($config['path_prefix'] ?? 'collection/uploads', '/');

        $sdkConfig = [
            'version'     => 'latest',
            'region'      => $this->region,
            'credentials' => [
                'key'    => $config['access_key'] ?? '',
                'secret' => $config['secret_key'] ?? '',
            ],
        ];

        // Support custom endpoint (e.g. MinIO, DigitalOcean Spaces, etc.)
        if (!empty($config['endpoint'])) {
            $sdkConfig['endpoint']                = $config['endpoint'];
            $sdkConfig['use_path_style_endpoint']  = true;
        }

        $this->client = new S3Client($sdkConfig);
    }

    public function put(string $storagePath, string $localFile, string $contentType = ''): bool {
        try {
            $params = [
                'Bucket'     => $this->bucket,
                'Key'        => $this->fullKey($storagePath),
                'SourceFile' => $localFile,
                'ACL'        => 'public-read',
            ];

            if ($contentType !== '') {
                $params['ContentType'] = $contentType;
            }

            $this->client->putObject($params);
            return true;
        } catch (AwsException $e) {
            error_log('S3Storage::put failed: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(string $storagePath): bool {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->fullKey($storagePath),
            ]);
            return true;
        } catch (AwsException $e) {
            error_log('S3Storage::delete failed: ' . $e->getMessage());
            return false;
        }
    }

    public function exists(string $storagePath): bool {
        try {
            return $this->client->doesObjectExist($this->bucket, $this->fullKey($storagePath));
        } catch (AwsException $e) {
            return false;
        }
    }

    public function url(string $storagePath): string {
        $key = $this->fullKey($storagePath);

        // Build standard S3 public URL
        return "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$key}";
    }

    public function driverName(): string {
        return 's3';
    }

    /**
     * Build the full S3 object key by prepending the configured prefix.
     */
    private function fullKey(string $path): string {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        return $this->prefix !== '' ? $this->prefix . '/' . $path : $path;
    }

    /**
     * Test the S3 connection by listing the bucket.
     * Returns true on success, or an error message string on failure.
     *
     * @return true|string
     */
    public function testConnection() {
        try {
            $this->client->headBucket(['Bucket' => $this->bucket]);
            return true;
        } catch (AwsException $e) {
            return 'S3 Error: ' . $e->getAwsErrorMessage();
        } catch (\Exception $e) {
            return 'Connection Error: ' . $e->getMessage();
        }
    }
}
