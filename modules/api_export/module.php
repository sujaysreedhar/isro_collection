<?php
// modules/api_export/module.php

if (!class_exists('BaseModule')) {
    require_once __DIR__ . '/../../includes/BaseModule.php';
}

class ApiExportModule extends BaseModule {

    public function registerRoutes() {
        // Register export route in admin panel before layout renders
        HookRegistry::addAction('admin_init_api_export', function() {
            if (isset($_GET['action']) && $_GET['action'] === 'download_csv') {
                require __DIR__ . '/export_csv.php';
                exit;
            }
        });
    }

    public function registerAdminMenu() {
        // Inject CSV export button into admin items list
        HookRegistry::addAction('admin_items_list_actions', function() {
            $url = SITE_URL . '/admin/module_page.php?m=api_export&action=download_csv';
            echo '<a href="' . $url . '" class="bg-blue-600 text-white font-medium px-4 py-2 rounded-md hover:bg-blue-700 transition inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Export CSV
                  </a>';
        });

        // Render module page if visited directly in admin panel
        HookRegistry::addAction('admin_page_api_export', function() {
            $downloadUrl = SITE_URL . '/admin/module_page.php?m=api_export&action=download_csv';
            echo '<div class="space-y-4">
                    <p class="text-sm text-gray-600">Export your entire artifact catalog and metadata into a UTF-8 CSV spreadsheet compatible with Microsoft Excel and Google Sheets.</p>
                    <a href="' . $downloadUrl . '" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium text-sm rounded-lg hover:bg-blue-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Download CSV Export
                    </a>
                  </div>';
        });
    }

    public function activate() {
        return true;
    }

    public function deactivate() {
        return true;
    }
}

// Procedural backward compatibility wrappers
function api_export_activate() { return true; }
function api_export_deactivate() { return true; }
