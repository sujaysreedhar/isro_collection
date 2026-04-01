<?php
// index.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Router.php';

// Dispatch the request
Router::dispatch();
