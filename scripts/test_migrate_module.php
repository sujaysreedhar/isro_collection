<?php
declare(strict_types=1);

require_once __DIR__ . '/../modules/migrate/MigrateService.php';
require_once __DIR__ . '/../includes/SafePDO.php';

function migrate_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function migrate_test_assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$contentTables = MigrateService::contentTables();
$excludedUserTables = [
    'admins',
    'api_keys',
    'settings',
    'subscribers',
    'trade_requests',
    'item_comments',
    'user_galleries',
    'user_gallery_items',
];

foreach ($excludedUserTables as $table) {
    migrate_test_assert(!in_array($table, $contentTables, true), "User or credential table {$table} must not be migrated.");
}

$blogRow = MigrateService::sanitizeRowForExport('blog_posts', [
    'id' => 7,
    'title' => 'Launch Notes',
    'author_id' => 2,
]);
migrate_test_assert_same(null, $blogRow['author_id'], 'Blog author_id must be nulled so admin accounts are not migrated.');

$mediaPaths = MigrateService::mediaPathsForRows('media', [
    [
        'file_path' => 'img_42_sample.webp',
        'media_type' => 'image',
    ],
    [
        'file_path' => 'catalogue.pdf',
        'media_type' => 'pdf',
    ],
]);

migrate_test_assert(in_array('thumbnails/img_42_sample.webp', $mediaPaths, true), 'Image thumbnails must use uploads/thumbnails.');
migrate_test_assert(!in_array('thumbs/img_42_sample.webp', $mediaPaths, true), 'Legacy uploads/thumbs must not be used.');
migrate_test_assert(in_array('display/img_42_sample.webp', $mediaPaths, true), 'Image display variant should be included.');
migrate_test_assert(in_array('pdfs/catalogue.pdf', $mediaPaths, true), 'PDF media should be included.');

$safePath = MigrateService::normalizeArchivePath('files/display/example.webp', 'files/');
migrate_test_assert_same('display/example.webp', $safePath, 'Safe archive paths should normalize relative to files/.');

$blocked = false;
try {
    MigrateService::normalizeArchivePath('files/../config.php', 'files/');
} catch (InvalidArgumentException $e) {
    $blocked = true;
}
migrate_test_assert($blocked, 'Archive paths must not be able to escape the files directory.');

$pdo = new SafePDO(
    'mysql:host=localhost;dbname=eish;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
$service = new MigrateService($pdo, dirname(__DIR__));
$overview = $service->overview();
migrate_test_assert(($overview['tables']['categories'] ?? 0) >= 0, 'Overview should load table counts through SafePDO without SQL syntax errors.');

echo "Migrate module tests passed.\n";
