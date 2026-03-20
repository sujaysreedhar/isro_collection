<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/modules/newsletter/module.php';

if (newsletter_activate()) {
    echo "Newsletter activated.\n";
} else {
    echo "Failed to activate Newsletter.\n";
}
