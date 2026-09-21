<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$dsn = getenv('AUDIT_BENCH_DSN');
$user = getenv('AUDIT_BENCH_USER');
if (!$dsn || $user === false) { fwrite(STDERR, "Set AUDIT_BENCH_DSN and AUDIT_BENCH_USER for read-only measurements.\n"); exit(2); }
$runs = 20;
foreach ($argv as $arg) if (str_starts_with($arg, '--runs=')) $runs = max(5, min(100, (int)substr($arg, 7)));
$pdo = new PDO($dsn, $user, getenv('AUDIT_BENCH_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
require_once __DIR__ . '/../includes/AppConfig.php';
require_once __DIR__ . '/../includes/SearchEngine.php';
define('CACHE_DIR', sys_get_temp_dir() . '/audit-benchmark-no-cache');
$engine = new SearchEngine($pdo);

$cases = [
    'browse' => ['limit' => 20, 'offset' => 0],
    'substring' => ['q' => 'postmark', 'limit' => 20, 'offset' => 0, 'exact' => 1],
];
$category = $pdo->query('SELECT id FROM categories ORDER BY id LIMIT 1')->fetchColumn();
if ($category !== false) $cases['filtered'] = ['q' => 'postmark', 'category_ids' => [(int)$category], 'has_images' => 1, 'limit' => 20, 'offset' => 0, 'exact' => 1];
foreach ($cases as $name => $params) {
    $times = [];
    $peakBefore = memory_get_peak_usage(true);
    $total = $returned = 0;
    for ($i = 0; $i < $runs; $i++) {
        $start = hrtime(true);
        $result = $engine->search($params);
        $times[] = (hrtime(true) - $start) / 1e6;
        $total = $result['total_results'];
        $returned = count($result['results']);
    }
    sort($times);
    echo json_encode(['case' => $name, 'runs' => $runs, 'query_count_per_run' => 6,
        'total' => $total, 'returned' => $returned, 'p50_ms' => $times[(int)floor(($runs - 1) * .5)],
        'p95_ms' => $times[(int)floor(($runs - 1) * .95)],
        'peak_memory_delta_bytes' => max(0, memory_get_peak_usage(true) - $peakBefore)], JSON_UNESCAPED_SLASHES) . "\n";
}
$explain = $pdo->prepare('EXPLAIN SELECT COUNT(DISTINCT i.id) FROM items i WHERE i.is_visible = 1 AND (MATCH(i.title, i.physical_description) AGAINST(? IN BOOLEAN MODE) OR MATCH(i.title, i.physical_description) AGAINST(? IN NATURAL LANGUAGE MODE) OR i.title LIKE ? OR i.physical_description LIKE ? OR i.production_date LIKE ? OR i.credit_line LIKE ?)');
$explain->execute(['postmark*', 'postmark', '%postmark%', '%postmark%', '%postmark%', '%postmark%']);
echo json_encode(['explain' => $explain->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_SLASHES) . "\n";
