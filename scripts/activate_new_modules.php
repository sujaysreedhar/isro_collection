<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/modules/item_comments/module.php';

if (item_comments_activate()) {
    echo "Item Comments activated and table created.\n";
} else {
    echo "Failed to activate.\n";
}
