<?php
require_once 'config/config.php';

// 1. Create a test key
$testKey = 'test_789_sec_key';
$pdo->prepare("INSERT IGNORE INTO api_keys (key_value, client_name) VALUES (?, ?)")->execute([$testKey, 'Test Client']);

function testApi($url, $key = null) {
    echo "Testing URL: $url\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($key) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-API-KEY: $key"]);
    }
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "Status Code: $code\n";
    return ['code' => $code, 'body' => $response];
}

$baseUrl = SITE_URL . '/api.php?action=items&limit=1';

echo "--- Test 1: No Key ---\n";
$r1 = testApi($baseUrl);
if ($r1['code'] === 401) echo "CORRECT: Got 401 Unauthorized.\n\n";
else echo "FAILED: Expected 401, got " . $r1['code'] . "\n\n";

echo "--- Test 2: Valid Key (Header) ---\n";
$r2 = testApi($baseUrl, $testKey);
if ($r2['code'] === 200) echo "CORRECT: Got 200 OK.\n\n";
else echo "FAILED: Expected 200, got " . $r2['code'] . "\n\n";

echo "--- Test 3: Valid Key (Query Param) ---\n";
$r3 = testApi($baseUrl . '&api_key=' . $testKey);
if ($r3['code'] === 200) echo "CORRECT: Got 200 OK.\n\n";
else echo "FAILED: Expected 200, got " . $r3['code'] . "\n\n";

echo "--- Test 4: Invalid Key ---\n";
$r4 = testApi($baseUrl, 'wrong_key');
if ($r4['code'] === 401) echo "CORRECT: Got 401 Unauthorized.\n\n";
else echo "FAILED: Expected 401, got " . $r4['code'] . "\n\n";

// Cleanup test key
$pdo->prepare("DELETE FROM api_keys WHERE key_value = ?")->execute([$testKey]);
?>
