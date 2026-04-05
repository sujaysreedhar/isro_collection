<?php
require_once __DIR__ . '/config/config.php';
$path = ABSPATH . '/uploads/panoramics/test.txt';
$res = file_put_contents($path, 'test');
if ($res !== false) {
    echo "Successfully wrote to $path\n";
    unlink($path);
} else {
    echo "FAILED to write to $path\n";
    $error = error_get_last();
    print_r($error);
}
