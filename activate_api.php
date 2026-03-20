<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/modules/api_export/module.php';

if (api_export_activate()) {
    echo "API Export activated.\n";
} else {
    echo "Failed to activate API Export.\n";
}
