<?php
$html = file_get_contents('http://localhost/collection/');
$hasStyle = strpos($html, '--color-primary:') !== false;
echo "Has --color-primary in HTML? " . ($hasStyle ? "Yes" : "No") . "\n";
if ($hasStyle) {
    preg_match('/<style>.*?--color-primary:.*?</s', $html, $matches);
    echo substr($matches[0], 0, 200);
}
