<?php
require_once __DIR__ . '/../config/config.php';

function fetchJson($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ISROPostmarkAtlas/1.0 (internal admin validator)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp ? json_decode($resp, true) : null;
}

$queries = [
    'Hikkim, Lahaul and Spiti, India',
    'Kee Gompa, Lahaul and Spiti, India',
    'Kibber, Lahaul and Spiti, India',
    'Bengaluru GPO, Bengaluru, India',
    'High Court, Bengaluru, India',
];

foreach ($queries as $q) {
    $url = 'https://nominatim.openstreetmap.org/search?q=' . urlencode($q) . '&format=json&limit=1';
    $res = fetchJson($url);
    if (!empty($res)) {
        echo "Query: $q\n  -> Lat: {$res[0]['lat']}, Lng: {$res[0]['lon']}, Name: {$res[0]['display_name']}\n";
    } else {
        echo "Query: $q\n  -> NOT FOUND\n";
    }
    sleep(1);
}
