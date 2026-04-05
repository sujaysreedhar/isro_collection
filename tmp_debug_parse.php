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
echo "Total rows found in table body: " . $rows->length . "\n";

$valid = 0;
$invalid_length = 0;
$invalid_pin_po = 0;
$reasons = [];

foreach ($rows as $index => $row) {
    $cells = $xpath->query('td', $row);
    if (!$cells || $cells->length < 7) {
        $invalid_length++;
        $reasons[] = "Row $index: Not enough cells (" . ($cells ? $cells->length : 0) . ")";
        continue;
    }

    $cell  = fn(int $i) => trim(preg_replace('/\s+/', ' ', $cells->item($i)->textContent ?? ''));
    $name  = $cell(1);
    $po    = $cell(3);
    $dist  = $cell(4);
    $state = $cell(5);
    $pin   = preg_replace('/\D/', '', $cell(6));

    if (strlen($pin) !== 6 || empty($po)) {
        $invalid_pin_po++;
        $reasons[] = "Row $index: Invalid PIN ($pin) or empty PO ($po) - Name: $name";
        continue;
    }
    
    $valid++;
}

echo "Valid rows based on criteria: $valid\n";
echo "Invalid length: $invalid_length\n";
echo "Invalid PIN/PO: $invalid_pin_po\n";
if ($invalid_pin_po > 0) {
    echo "Reasons for invalid PIN/PO:\n";
    print_r(array_slice($reasons, 0, 10)); // print first 10
}
