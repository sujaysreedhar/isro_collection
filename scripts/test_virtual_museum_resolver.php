<?php

$classFile = __DIR__ . '/../modules/virtual_museum/lib/ItemSelection.php';
if (!is_file($classFile)) {
    fwrite(STDERR, "FAIL: ItemSelection.php does not exist yet.\n");
    exit(1);
}
require_once $classFile;

function vmAssertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . json_encode($expected) . "\nActual: " . json_encode($actual) . "\n");
        exit(1);
    }
}

$candidates = [
    ['id' => 10, 'source_order' => 1, 'native_order' => 2],
    ['id' => 11, 'source_order' => 0, 'native_order' => 1],
    ['id' => 10, 'source_order' => 0, 'native_order' => 3],
    ['id' => 12, 'source_order' => 1, 'native_order' => 1],
    ['id' => 13, 'source_order' => 2, 'native_order' => 0],
];
$overrides = [
    10 => ['selection_state' => 'include', 'sort_order' => 1],
    11 => ['selection_state' => 'exclude', 'sort_order' => null],
    13 => ['selection_state' => 'include', 'sort_order' => 0],
];

$resolved = VirtualMuseumItemSelection::resolve($candidates, $overrides, 100);
vmAssertSame([13, 10, 12], array_column($resolved, 'id'), 'explicit order wins, excluded items disappear, and duplicate sources collapse');

$many = [];
for ($id = 1; $id <= 140; $id++) {
    $many[] = ['id' => $id, 'source_order' => 0, 'native_order' => $id];
}
$limited = VirtualMuseumItemSelection::resolve($many, [], 100);
vmAssertSame(100, count($limited), 'resolver enforces the 100 item hard cap');
vmAssertSame(1, $limited[0]['id'], 'live source order is deterministic');
vmAssertSame(100, $limited[99]['id'], 'limit keeps the first 100 ordered candidates');

echo "PASS: virtual museum item selection\n";
