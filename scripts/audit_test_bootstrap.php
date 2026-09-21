<?php
declare(strict_types=1);

function auditCheck(bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

/** Refuse all fixture work unless the operator explicitly names a separate database. */
function auditFixturePdo(array $arguments): PDO {
    $dsn = getenv('AUDIT_TEST_DSN');
    $user = getenv('AUDIT_TEST_USER');
    $appDb = getenv('DB_NAME') ?: 'eish';
    auditCheck(in_array('--allow-disposable-mutations', $arguments, true), 'Unconfigured: explicit disposable test mode required.');
    auditCheck(is_string($dsn) && $dsn !== '' && is_string($user) && $user !== '', 'Unconfigured: AUDIT_TEST_DSN and AUDIT_TEST_USER required.');
    $memory = $dsn === 'sqlite::memory:';
    auditCheck($memory || preg_match('/(?:^|;)dbname=([^;]+)/i', $dsn, $matches) === 1, 'Unconfigured: test DSN must name a database or SQLite memory.');
    auditCheck($memory || strcasecmp($matches[1], $appDb) !== 0, 'Refusing to use application database for fixtures.');
    $pdo = new PDO($dsn, $user, getenv('AUDIT_TEST_PASSWORD') ?: '');
    if (!$memory) {
        $actual = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        auditCheck($actual !== '' && strcasecmp($actual, $appDb) !== 0 && strcasecmp($actual, $matches[1]) === 0, 'Refusing unsafe or mismatched test database.');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}
