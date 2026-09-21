<?php
declare(strict_types=1);

final class RateLimiter {
    public static function consume(PDO $pdo, string $scope, string $key, int $limit, int $windowSeconds): array {
        if ($scope === '' || strlen($scope) > 64 || $limit < 1 || $windowSeconds < 1) {
            throw new InvalidArgumentException('Invalid rate limit configuration.');
        }
        $now = time();
        $start = intdiv($now, $windowSeconds) * $windowSeconds;
        $hash = hash('sha256', $key);
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO request_rate_limits (scope,key_hash,window_start,attempts,expires_at) VALUES (?,?,?,1,?) ON CONFLICT(scope,key_hash,window_start) DO UPDATE SET attempts=attempts+1'
            : 'INSERT INTO request_rate_limits (scope,key_hash,window_start,attempts,expires_at) VALUES (?,?,?,1,?) ON DUPLICATE KEY UPDATE attempts=attempts+1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$scope, $hash, $start, $start + $windowSeconds]);
        $stmt = $pdo->prepare('SELECT attempts FROM request_rate_limits WHERE scope = ? AND key_hash = ? AND window_start = ?');
        $stmt->execute([$scope, $hash, $start]);
        $attempts = (int)$stmt->fetchColumn();
        if ($driver !== 'sqlite' && random_int(1, 100) === 1) {
            $pdo->prepare('DELETE FROM request_rate_limits WHERE expires_at < ? LIMIT 100')->execute([$now]);
        }
        return ['allowed' => $attempts <= $limit, 'retry_after' => max(1, $start + $windowSeconds - $now)];
    }
}
