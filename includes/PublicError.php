<?php
declare(strict_types=1);

final class PublicError {
    public static function payload(Throwable $error): array {
        $id = bin2hex(random_bytes(8));
        error_log('[' . $id . '] ' . get_class($error) . ': ' . $error->getMessage());
        return ['success' => false, 'error' => 'Service temporarily unavailable.', 'error_id' => $id];
    }
}
