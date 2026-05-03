<?php

require_once __DIR__ . '/BackupManagerService.php';

class BackupManagerModule extends BaseModule
{
    private const WEBHOOK_SETTING_KEY = 'backup_manager_webhook_key';

    public function boot()
    {
        HookRegistry::addFilter('admin_sidebar_links', function (array $sections) {
            $sections['system']['links']['backup_manager'] = [
                'url' => SITE_URL . '/admin/module_page.php?m=' . $this->slug,
                'label' => 'Backup Manager',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5M4.5 18.75h15a.75.75 0 00.75-.75V8.25a2.25 2.25 0 00-2.25-2.25h-1.379a1.5 1.5 0 01-1.06-.44l-.621-.62A1.5 1.5 0 0013.879 4.5H10.12a1.5 1.5 0 00-1.06.44l-.62.62A1.5 1.5 0 017.378 6H6a2.25 2.25 0 00-2.25 2.25V18a.75.75 0 00.75.75z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 11.25v6m0 0l-2.25-2.25M12 17.25l2.25-2.25" />',
            ];

            return $sections;
        });

        HookRegistry::addAction('admin_page_' . $this->slug, function () {
            require __DIR__ . '/admin_page.php';
        });

        HookRegistry::addFilter('route_request', function ($handled, $uri) {
            if ($uri !== 'runback') {
                return $handled;
            }

            header('Content-Type: text/plain; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

            $key = trim((string) ($_REQUEST['key'] ?? ''));
            $type = trim((string) ($_REQUEST['type'] ?? ''));

            if ($key === '' || !hash_equals($this->getWebhookKey(), $key)) {
                http_response_code(403);
                echo 'NOT';
                return true;
            }

            $result = $this->runBackup($type, 'webhook');
            http_response_code($result['success'] ? 200 : 500);
            echo $result['success'] ? 'OK' : 'NOT';
            return true;
        }, 10, 2);

        HookRegistry::addFilter('admin_ajax_backup_manager_save_settings', function ($handled) {
            $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!verifyCsrfToken($csrfToken)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
                return true;
            }

            $allowed = $GLOBALS['admin_settings_whitelist'] ?? [];
            $keyName = self::WEBHOOK_SETTING_KEY;
            if (!in_array($keyName, $allowed, true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Setting key is not whitelisted.']);
                return true;
            }

            $value = trim((string) ($_POST[$keyName] ?? ''));
            if ($value === '') {
                $value = $this->generateWebhookKey();
            }

            $this->upsertSetting($keyName, $value);

            echo json_encode([
                'success' => true,
                'key' => $value,
            ]);
            return true;
        });

        HookRegistry::addFilter('admin_ajax_backup_manager_run', function ($handled) {
            $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!verifyCsrfToken($csrfToken)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
                return true;
            }

            $type = trim((string) ($_POST['type'] ?? ''));
            $result = $this->runBackup($type, 'admin');

            if (!$result['success']) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $result['message'] ?? 'Backup failed.',
                ]);
                return true;
            }

            echo json_encode([
                'success' => true,
                'parts' => $result['parts'] ?? [],
                'run_id' => $result['run_id'] ?? null,
            ]);
            return true;
        });
    }

    public function activate()
    {
        if (($GLOBALS['appSettings'][self::WEBHOOK_SETTING_KEY] ?? '') === '') {
            $this->upsertSetting(self::WEBHOOK_SETTING_KEY, $this->generateWebhookKey());
        }
    }

    public function getWebhookKey(): string
    {
        $current = trim((string) ($GLOBALS['appSettings'][self::WEBHOOK_SETTING_KEY] ?? ''));
        if ($current !== '') {
            return $current;
        }

        $generated = $this->generateWebhookKey();
        $this->upsertSetting(self::WEBHOOK_SETTING_KEY, $generated);
        return $generated;
    }

    public function getStorageDir(): string
    {
        return ABSPATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'backups';
    }

    public function getLatestStatus(): array
    {
        return $this->getService()->readLatestStatus();
    }

    public function runBackup(string $type, string $trigger = 'manual'): array
    {
        return $this->getService()->run($type, $trigger);
    }

    private function getService(): BackupManagerService
    {
        return new BackupManagerService($this->pdo, ABSPATH, $this->getStorageDir());
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

    private function generateWebhookKey(): string
    {
        return bin2hex(random_bytes(24));
    }
}
