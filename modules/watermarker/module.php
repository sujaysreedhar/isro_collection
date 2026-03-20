<?php
// modules/watermarker/module.php

function watermarker_activate() { return true; }
function watermarker_deactivate() { return true; }

// Only watermark images meant for 'display', not thumbnails 'thumbs'
HookRegistry::addFilter('after_image_resize', function($img, $folder) {
    if ($folder !== 'display') {
        return $img; // skip thumbnails
    }

    $watermarkText = defined('SITE_TITLE') ? SITE_TITLE : 'Archival Collection';
    $watermarkText = '© ' . date('Y') . ' ' . $watermarkText;

    // We use the largest built-in font (5)
    $font = 5;
    $width = imagesx($img);
    $height = imagesy($img);

    // Calculate dimensions of the text
    $textWidth = imagefontwidth($font) * strlen($watermarkText);
    $textHeight = imagefontheight($font);

    // Calculate position - Bottom Right corner with 20px padding
    $x = $width - $textWidth - 20;
    $y = $height - $textHeight - 20;
    
    if ($x < 0) $x = 10;
    if ($y < 0) $y = 10;

    // Create semi-transparent white text with dark shadow for visibility anywhere
    // allocate colors with alpha channel (0 = opaque, 127 = completely transparent)
    $shadowColor = imagecolorallocatealpha($img, 0, 0, 0, 60); // Dark shadow, moderate alpha
    $textColor = imagecolorallocatealpha($img, 255, 255, 255, 50); // White text, moderate alpha
    
    // Disable alpha blending temporarily to stamp the exact alpha pixel
    imagealphablending($img, true);

    // Draw shadow offset by 1px
    imagestring($img, $font, $x + 1, $y + 1, $watermarkText, $shadowColor);
    // Draw text
    imagestring($img, $font, $x, $y, $watermarkText, $textColor);

    return $img;
}, 10, 2);
