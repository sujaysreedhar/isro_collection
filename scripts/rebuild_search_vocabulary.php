<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
try {
    (new SearchEngine($pdo))->rebuildVocabularyCache();
    echo "Search vocabulary published.\n";
} catch (Throwable $error) {
    $id = bin2hex(random_bytes(8));
    error_log('[' . $id . '] Search vocabulary rebuild failed: ' . $error->getMessage());
    fwrite(STDERR, "Search vocabulary rebuild failed (reference $id).\n");
    exit(1);
}
