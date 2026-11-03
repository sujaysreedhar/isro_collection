<?php
class CollectionWorkbenchModule extends BaseModule {
    private ?CollectionInventory $inventory = null;
    private string $error = '';
    private string $success = '';

    private function inventory(): CollectionInventory {
        if ($this->inventory === null) $this->inventory = new CollectionInventory($this->pdo);
        return $this->inventory;
    }

    public function boot() {
        HookRegistry::addFilter('admin_sidebar_links', function(array $sections) {
            $sections['catalog']['links']['collection_workbench'] = [
                'url' => SITE_URL . '/admin/module_page.php?m=collection_workbench',
                'label' => 'Collection Workbench',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />'
            ];
            return $sections;
        });
        HookRegistry::addAction('admin_init_collection_workbench', function() { $this->submit(); });
        HookRegistry::addAction('admin_page_collection_workbench', function() {
            if (empty($_SESSION['admin_id'])) { http_response_code(403); return; }
            $inventory = $this->inventory();
            $error = $this->error;
            $success = $this->success;
            require __DIR__ . '/admin_page.php';
        });
        HookRegistry::addAction('admin_item_edit_after_fields', function($id, $item) {
            if (empty($_SESSION['admin_id']) || !$this->inventory()->ready()) return;
            $details = $id ? $this->inventory()->privateDetails((int)$id) : [];
            require __DIR__ . '/item_fields.php';
        }, 20, 2);
        HookRegistry::addAction('item_saved', function($id) {
            if (empty($_SESSION['admin_id']) || !isset($_POST['collection_private']) || !$this->inventory()->ready()) return;
            if (!Csrf::verify(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) throw new RuntimeException('Security check failed.');
            if (!is_array($_POST['collection_private'])) throw new InvalidArgumentException('Invalid private item fields.');
            $this->inventory()->saveExtraFields((int)$id, $_POST['collection_private']);
        }, 20);
    }

    // Schema updates are explicit, repeatable SQL in update.sql, rather than hidden page-load migrations.
    public function activate() {}

    private function submit(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
        if (empty($_SESSION['admin_id']) || !Csrf::verify(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            http_response_code(403);
            $this->error = 'Security check failed. Reload the page and try again.';
            return;
        }
        if (!$this->inventory()->ready()) { $this->error = 'Run the database updates before using the workbench.'; return; }
        try {
            $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
            if ($action === 'save_private') {
                $this->inventory()->savePrivate((int)($_POST['item_id'] ?? 0), $_POST);
                $this->success = 'Private storage and financial details saved.';
            } elseif ($action === 'bulk_preview') {
                $changes = [];
                foreach (CollectionInventory::BULK_FIELDS as $key) {
                    if (isset($_POST['apply'][$key]) && $_POST['apply'][$key] === '1') $changes[$key] = $_POST['change'][$key] ?? '';
                }
                $preview = $this->inventory()->preview(is_array($_POST['item_ids'] ?? null) ? $_POST['item_ids'] : [], $changes);
                $preview['admin_id'] = (int)$_SESSION['admin_id'];
                $_SESSION['collection_bulk_preview'] = $preview;
            } elseif ($action === 'bulk_confirm') {
                $preview = $_SESSION['collection_bulk_preview'] ?? null;
                if (!is_array($preview) || (int)($preview['admin_id'] ?? 0) !== (int)$_SESSION['admin_id'] || !is_string($_POST['preview_token'] ?? null) || !hash_equals($preview['token'], $_POST['preview_token'])) throw new InvalidArgumentException('Preview not found. Generate a new preview before saving.');
                $count = $this->inventory()->applyPreview($preview);
                unset($_SESSION['collection_bulk_preview']);
                $this->success = "{$count} items updated from your preview.";
            } elseif ($action === 'bulk_cancel') {
                unset($_SESSION['collection_bulk_preview']);
                $this->success = 'Preview cancelled. No changes saved.';
            } elseif ($action === 'create_checklist' || $action === 'import_set') {
                $id = $action === 'import_set' ? $this->inventory()->importSet((int)($_POST['set_id'] ?? 0), $_POST) : $this->inventory()->createChecklist($_POST);
                header('Location: ' . SITE_URL . '/admin/module_page.php?m=collection_workbench&tab=completeness&checklist=' . $id . '&saved=1');
                exit;
            } elseif ($action === 'save_entry') {
                $this->inventory()->saveEntry((int)($_POST['checklist_id'] ?? 0), (int)($_POST['entry_id'] ?? 0), $_POST);
                $this->success = 'Expected item saved. Linking a collection record marks it as owned.';
            } elseif ($action === 'remove_entry') {
                $this->inventory()->removeEntry((int)($_POST['checklist_id'] ?? 0), (int)($_POST['entry_id'] ?? 0));
                $this->success = 'Expected item removed from this checklist.';
            } else { throw new InvalidArgumentException('Choose a supported action.'); }
        } catch (InvalidArgumentException $e) { $this->error = $e->getMessage(); }
        catch (Throwable $e) {
            error_log('Collection workbench: ' . $e->getMessage());
            $this->error = 'Unable to save these changes. Nothing was confirmed; please reload and try again.';
        }
    }
}
