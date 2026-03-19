<?php
// scripts/test_webp.php
$source = __DIR__ . '/../uploads/original/img_15_aa30fb13fb50c895.webp'; // From previous dir output
$dest = __DIR__ . '/../uploads/thumbnails/test_webp.jpg';

if (!file_exists($source)) {
    die("Source file not found: $source\n");
}

$info = getimagesize($source);
print_r($info);

if ($info['mime'] === 'image/webp') {
    echo "Mime type is correct.\n";
    $srcImg = @imagecreatefromwebp($source);
    if ($srcImg) {
        echo "Successfully created image from WebP.\n";
        $thumb = imagecreatetruecolor(100, 100);
        imagecopyresampled($thumb, $srcImg, 0, 0, 0, 0, 100, 100, $info[0], $info[1]);
        if (imagejpeg($thumb, $dest)) {
            echo "Successfully saved JPEG thumbnail.\n";
        } else {
            echo "Failed to save JPEG thumbnail.\n";
        }
        imagedestroy($thumb);
        imagedestroy($srcImg);
    } else {
        echo "Failed to create image from WebP. Error: " . error_get_last()['message'] . "\n";
    }
} else {
    echo "Unexpected mime type: " . $info['mime'] . "\n";
}
