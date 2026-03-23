<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/modules/watermarker/module.php';

if (watermarker_activate()) {
    echo "Watermarker activated.\n";
} else {
    echo "Failed to activate Watermarker.\n";
}
