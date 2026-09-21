<?php

require_once __DIR__ . '/AutoBackupService.php';

class AutoBackupModule extends BaseModule
{
    public function boot()
    {
        // Backups are run by scripts/run_auto_backup.php, outside web requests.
        // Add sidebar link to the admin panel.
        HookRegistry::addFilter('admin_sidebar_links', function (array $sections) {
            $sections['system']['links']['auto_backup'] = [
                'url' => SITE_URL . '/admin/module_page.php?m=' . $this->slug,
                'label' => 'Auto Backups',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />',
            ];
            return $sections;
        });

        // 3. Register the module admin page rendering action
        HookRegistry::addAction('admin_page_' . $this->slug, function () {
            require __DIR__ . '/admin_page.php';
        });

        // 4. Register AJAX action for saving settings
        HookRegistry::addFilter('admin_ajax_auto_backup_save_settings', function ($handled) {
            $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!verifyCsrfToken($csrfToken)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
                return true;
            }

            $allowed = $GLOBALS['admin_settings_whitelist'] ?? [];
            if (!in_array('auto_backup_enabled', $allowed, true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Setting key is not whitelisted.']);
                return true;
            }

            $enabled = ($_POST['auto_backup_enabled'] ?? '0') === '1' ? '1' : '0';
            $this->upsertSetting('auto_backup_enabled', $enabled);

            echo json_encode(['success' => true]);
            return true;
        });

        // 5. Register AJAX action for manual run
        HookRegistry::addFilter('admin_ajax_auto_backup_run', function ($handled) {
            $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!verifyCsrfToken($csrfToken)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
                return true;
            }

            $type = trim((string)($_POST['type'] ?? ''));
            $parts = [];
            if ($type === 'db' || $type === 'db_media') {
                $parts[] = 'db';
            }
            if ($type === 'media' || $type === 'db_media') {
                $parts[] = 'media';
            }

            $result = $this->getService()->run($parts, 'manual');
            if ($result['success']) {
                $this->getService()->cleanupOldBackups();
            }

            echo json_encode($result);
            return true;
        });

        // 6. Register AJAX action for delete
        HookRegistry::addFilter('admin_ajax_auto_backup_delete', function ($handled) {
            $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!verifyCsrfToken($csrfToken)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
                return true;
            }

            $folderName = trim((string)($_POST['folder_name'] ?? ''));
            $success = $this->getService()->deleteBackup($folderName);

            echo json_encode(['success' => $success]);
            return true;
        });
    }

    public function getStorageDir(): string
    {
        return ABSPATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'auto_backups';
    }

    private function getService(): AutoBackupService
    {
        return new AutoBackupService($this->pdo, ABSPATH, $this->getStorageDir());
    }

    private function upsertSetting(string $key, string $value): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:key_name, :value)
             ON DUPLICATE KEY UPDATE setting_value = :value_update'
        );
        $statement->execute([
            ':key_name' => $key,
            ':value' => $value,
            ':value_update' => $value,
        ]);

        $GLOBALS['appSettings'][$key] = $value;
        AppConfig::set($key, $value);
    }
}
