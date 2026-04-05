<?php
$htmlUrl = 'https://praneethsaichunduru.github.io/ppcsofindia.github.io/list-pincode.html';
$ctx = stream_context_create([
    'http' => ['method' => 'GET', 'header' => "User-Agent: PostmarkAtlasImporter/3.0\r\n", 'timeout' => 45],
    'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
]);
$htmlContent = file_get_contents($htmlUrl, false, $ctx);

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($htmlContent);
libxml_clear_errors();
$xpath = new DOMXPath($dom);

$rows    = $xpath->query('//table//tbody//tr');
$pairs = [];

foreach ($rows as $index => $row) {
    $cells = $xpath->query('td', $row);
    if (!$cells || $cells->length < 7) continue;

    $cell  = fn(int $i) => trim(preg_replace('/\s+/', ' ', $cells->item($i)->textContent ?? ''));
    $pin   = preg_replace('/\D/', '', $cell(6));
    $po    = $cell(3);

    if (strlen($pin) !== 6 || empty($po)) continue;

    $pairs[] = "$pin|$po";
}

$counts = array_count_values($pairs);
$duplicates = array_filter($counts, fn($c) => $c > 1);

echo "Total rows: " . count($pairs) . "\n";
echo "Unique PIN+PO pairs: " . count($counts) . "\n";
if (!empty($duplicates)) {
    echo "Duplicates found:\n";
    print_r($duplicates);
}
