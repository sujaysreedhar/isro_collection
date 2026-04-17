<?php
/**
 * ajax.php
 * 
 * Central frontend AJAX handler.
 * Allows modules to register custom AJAX actions.
 */
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');

// Validate we have an action parameter
$action = $_REQUEST['action'] ?? '';

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Action parameter is required.']);
    exit;
}

// Give modules a chance to handle their own custom AJAX actions.
if (class_exists('HookRegistry')) {
    $handled = HookRegistry::applyFilters('frontend_ajax_' . $action, false);
    if ($handled) {
        exit; // A module processed the request and echoed its JSON.
    }
}

// If no module handled it, return 400
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown action.']);
