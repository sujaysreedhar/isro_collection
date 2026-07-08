<?php

require_once __DIR__ . '/MigrateService.php';

class MigrateModule extends BaseModule
{
    private ?array $lastResult = null;

    protected function registerAdminMenu()
    {
        HookRegistry::addFilter('admin_sidebar_links', function (array $sections) {
            $sections['system']['links']['migrate'] = [
                'url' => SITE_URL . '/admin/module_page.php?m=' . $this->slug,
                'label' => 'Migrate',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 16a4 4 0 01-.88-7.903A5 5 0 0115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />',
            ];
            return $sections;
        });

        HookRegistry::addAction('admin_init_' . $this->slug, [$this, 'handleAdminInit']);
        HookRegistry::addAction('admin_page_' . $this->slug, [$this, 'renderAdminPage']);
    }

    public function handleAdminInit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = $_POST['migrate_action'] ?? '';
        if (!in_array($action, ['export', 'import'], true)) {
            return;
        }

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $this->lastResult = [
                'success' => false,
                'message' => 'Invalid security token. Please reload the page and try again.',
            ];
            return;
        }

        $service = $this->makeService();

        try {
            if ($action === 'export') {
                $service->streamExport();
                exit;
            }

            $replace = isset($_POST['replace_current_data']) && $_POST['replace_current_data'] === '1';
            if ($replace) {
                $confirmation = trim((string)($_POST['replace_confirmation'] ?? ''));
                if ($confirmation !== 'DELETE CURRENT CONTENT') {
                    throw new RuntimeException('Type DELETE CURRENT CONTENT to confirm replacing current content.');
                }
            }

            if (empty($_FILES['migration_file']) || ($_FILES['migration_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Choose a migration zip file to import.');
            }

            $this->lastResult = $service->importPackage($_FILES['migration_file']['tmp_name'], $replace);
            $this->lastResult['success'] = true;
        } catch (Throwable $e) {
            $this->lastResult = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function renderAdminPage(): void
    {
        $service = $this->makeService();
        $result = $this->lastResult;
        require __DIR__ . '/admin_page.php';
    }

    private function makeService(): MigrateService
    {
        $root = defined('ABSPATH') ? ABSPATH : dirname(__DIR__, 2);
        return new MigrateService($this->pdo, $root);
    }
}
