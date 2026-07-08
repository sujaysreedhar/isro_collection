<?php

/**
 * Variants Module
 *
 * Lets you group items that are variants of the same underlying object —
 * e.g. the same cancellation issued in different years, or one coin struck at
 * different mints. Grouped items surface a "variant switcher" on their detail
 * page so a visitor can jump between the variants without leaving the context.
 *
 * Data model:
 *   module_variant_groups   — one row per "thing" that has variants
 *   module_variant_members  — links an item to a group (an item belongs to at
 *                             most one group) with a per-item variant label
 */
class VariantsModule extends BaseModule
{
    /** Create tables when the module is enabled. */
    public function activate()
    {
        ModuleDB::createTable($this->pdo, 'module_variant_groups', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            group_type VARCHAR(100) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_slug (slug)
        ");

        ModuleDB::createTable($this->pdo, 'module_variant_members', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL,
            item_id INT NOT NULL,
            variant_label VARCHAR(255) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_item (item_id),
            KEY idx_group (group_id),
            FOREIGN KEY (group_id) REFERENCES module_variant_groups(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ");
    }

    /** Guards against rendering the switcher twice for the same item. */
    private $rendered = [];

    /** Frontend + shared hooks. */
    protected function registerHooks()
    {
        // Show the variant switcher near the top of an item detail page.
        // Prefer item_before_content; fall back to item_after_content for themes
        // (e.g. glass, modern_blue) that don't fire the "before" hook. The
        // render guard makes sure it only appears once when a theme fires both.
        HookRegistry::addAction('item_before_content', [$this, 'renderSwitcher'], 10, 1);
        HookRegistry::addAction('item_after_content', [$this, 'renderSwitcher'], 10, 1);

        // Inject the group picker into the item editor.
        HookRegistry::addAction('admin_item_edit_after_fields', [$this, 'renderEditorField'], 10, 2);

        // Persist the group picker when an item is saved.
        HookRegistry::addAction('item_saved', [$this, 'handleItemSaved'], 10, 1);

        // Belt-and-suspenders cleanup (the FK ON DELETE CASCADE already handles this).
        HookRegistry::addAction('before_bulk_delete', [$this, 'handleBulkDelete'], 10, 1);
    }

    /** Admin sidebar link + page routing. */
    protected function registerAdminMenu()
    {
        HookRegistry::addFilter('admin_sidebar_links', function (array $sections) {
            $sections['catalog']['links']['variants'] = [
                'url'   => SITE_URL . '/admin/module_page.php?m=variants',
                'label' => 'Variants',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12M8 12h12M8 17h12M4 7h.01M4 12h.01M4 17h.01"/>',
            ];
            return $sections;
        });

        // Legacy sidebar fallback.
        HookRegistry::addAction('admin_menu', function () {
            echo '<a href="' . SITE_URL . '/admin/module_page.php?m=variants" class="sidebar-link text-slate-300">Variants</a>';
        });

        // Handle POST (create / update / delete groups) before any output.
        HookRegistry::addAction('admin_init_variants', [$this, 'handleAdminPost']);

        // Render the admin management page.
        HookRegistry::addAction('admin_page_variants', function () {
            require __DIR__ . '/admin_page.php';
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Item editor integration
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Renders the "Variant Group" picker inside the item edit form.
     * Because this fires inside the <form>, the fields submit with the item.
     */
    public function renderEditorField($id, $item)
    {
        $groups = $this->pdo->query(
            "SELECT id, title, group_type FROM module_variant_groups ORDER BY title ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $current = null;
        if ($id) {
            $stmt = $this->pdo->prepare(
                "SELECT group_id, variant_label FROM module_variant_members WHERE item_id = ?"
            );
            $stmt->execute([$id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $currentGroup = $current['group_id'] ?? '';
        $currentLabel = $current['variant_label'] ?? '';

        ?>
        <div class="mt-4 border-t border-gray-200 pt-5" id="variant-group-block">
            <label class="label">
                Variant Group
                <span class="text-gray-400 font-normal">(link this item to others that are variants of the same thing — e.g. same cancellation, different years)</span>
            </label>

            <select name="variant_group_id" id="variant-group-select" class="w-full input">
                <option value="">— Not a variant —</option>
                <?php foreach ($groups as $g): ?>
                    <option value="<?= (int) $g['id'] ?>" <?= ((string) $currentGroup === (string) $g['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['group_type'] ? $g['group_type'] . ' · ' : '') ?><?= htmlspecialchars($g['title']) ?>
                    </option>
                <?php endforeach; ?>
                <option value="__new__">＋ Create a new group…</option>
            </select>

            <div id="variant-new-group" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3" style="display:none;">
                <input type="text" name="variant_new_group_title" class="input w-full"
                    placeholder="New group name (e.g. 'Melbourne 1956 Olympics postmark')">
                <input type="text" name="variant_new_group_type" class="input w-full"
                    placeholder="Type (optional, e.g. Cancellation, Coin)">
            </div>

            <div class="mt-3" id="variant-label-wrap" style="<?= $currentGroup ? '' : 'display:none;' ?>">
                <label class="label text-sm">
                    This item's variant label
                    <span class="text-gray-400 font-normal">(what makes this one different — e.g. "1980 · Denver Mint")</span>
                </label>
                <input type="text" name="variant_label" class="input w-full"
                    value="<?= htmlspecialchars($currentLabel) ?>"
                    placeholder="e.g. 1878 issue / Denver Mint / Type II die">
            </div>
        </div>

        <script>
        (function () {
            var sel = document.getElementById('variant-group-select');
            if (!sel) return;
            var newBlock = document.getElementById('variant-new-group');
            var labelWrap = document.getElementById('variant-label-wrap');
            function sync() {
                var v = sel.value;
                newBlock.style.display = (v === '__new__') ? '' : 'none';
                labelWrap.style.display = (v === '') ? 'none' : '';
            }
            sel.addEventListener('change', sync);
            sync();
        })();
        </script>
        <?php
    }

    /** Persist the variant-group picker when an item is saved. */
    public function handleItemSaved($itemId)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0 || !isset($_POST['variant_group_id'])) {
            return;
        }

        $groupRaw = trim((string) $_POST['variant_group_id']);
        $label    = trim((string) ($_POST['variant_label'] ?? ''));

        // Cleared → remove any existing membership.
        if ($groupRaw === '') {
            $this->pdo->prepare("DELETE FROM module_variant_members WHERE item_id = ?")->execute([$itemId]);
            return;
        }

        // Inline "create a new group" path.
        if ($groupRaw === '__new__') {
            $newTitle = trim((string) ($_POST['variant_new_group_title'] ?? ''));
            if ($newTitle === '') {
                return; // Nothing to attach to.
            }
            $newType = trim((string) ($_POST['variant_new_group_type'] ?? '')) ?: null;
            $groupId = $this->createGroup($newTitle, $newType);
            if (!$groupId) {
                return;
            }
        } else {
            $groupId = (int) $groupRaw;
            // Make sure the group actually exists.
            $chk = $this->pdo->prepare("SELECT id FROM module_variant_groups WHERE id = ?");
            $chk->execute([$groupId]);
            if (!$chk->fetchColumn()) {
                return;
            }
        }

        // Upsert membership (an item belongs to exactly one group).
        $stmt = $this->pdo->prepare("
            INSERT INTO module_variant_members (group_id, item_id, variant_label, sort_order)
            VALUES (:g, :i, :l, 0)
            ON DUPLICATE KEY UPDATE group_id = VALUES(group_id), variant_label = VALUES(variant_label)
        ");
        $stmt->execute([':g' => $groupId, ':i' => $itemId, ':l' => ($label !== '' ? $label : null)]);
    }

    public function handleBulkDelete($ids)
    {
        if (!is_array($ids) || empty($ids)) {
            return;
        }
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (!$ids) {
            return;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $this->pdo->prepare("DELETE FROM module_variant_members WHERE item_id IN ($ph)")->execute($ids);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Frontend switcher
    // ─────────────────────────────────────────────────────────────────────

    /** Renders the sibling-variant switcher on an item's detail page. */
    public function renderSwitcher($item)
    {
        $itemId = (int) ($item['id'] ?? 0);
        if ($itemId <= 0 || isset($this->rendered[$itemId])) {
            return;
        }
        $this->rendered[$itemId] = true;

        // Which group is this item in?
        $stmt = $this->pdo->prepare("
            SELECT g.id, g.title, g.group_type, g.description
            FROM module_variant_members m
            JOIN module_variant_groups g ON g.id = m.group_id
            WHERE m.item_id = ?
        ");
        $stmt->execute([$itemId]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$group) {
            return;
        }

        // Fetch all members of that group (visible items only), plus a preview image.
        $mStmt = $this->pdo->prepare("
            SELECT i.id, i.title, i.reg_number, i.production_date, m.variant_label,
                   (SELECT mm.file_path FROM media mm
                     WHERE mm.item_id = i.id AND mm.media_type = 'image'
                     ORDER BY mm.id ASC LIMIT 1) AS preview
            FROM module_variant_members m
            JOIN items i ON i.id = m.item_id
            WHERE m.group_id = ? AND i.is_visible = 1
            ORDER BY m.sort_order ASC, i.production_date ASC, i.id ASC
        ");
        $mStmt->execute([(int) $group['id']]);
        $members = $mStmt->fetchAll(PDO::FETCH_ASSOC);

        // Only worth showing if there's more than one variant.
        if (count($members) < 2) {
            return;
        }

        $typeLabel = $group['group_type'] ? htmlspecialchars($group['group_type']) : 'Variants';
        ?>
        <div class="variant-switcher mb-6 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <span class="inline-block text-[11px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400"><?= $typeLabel ?></span>
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100"><?= htmlspecialchars($group['title']) ?></h3>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400"><?= count($members) ?> variants</span>
            </div>
            <div class="flex gap-3 overflow-x-auto pb-1">
                <?php foreach ($members as $mem):
                    $isCurrent = ((int) $mem['id'] === $itemId);
                    $url = SITE_URL . '/item/' . (int) $mem['id'];
                    $img = $this->imageUrl($mem['preview'] ?? '');
                    $label = $mem['variant_label'] ?: ($mem['production_date'] ?: $mem['reg_number']);
                    ?>
                    <a href="<?= $isCurrent ? '#' : htmlspecialchars($url) ?>"
                       class="group flex-shrink-0 w-32 rounded-xl border <?= $isCurrent
                            ? 'border-blue-500 ring-2 ring-blue-500/30 bg-white dark:bg-gray-900'
                            : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:border-blue-400 hover:shadow-md' ?> overflow-hidden transition"
                       <?= $isCurrent ? 'aria-current="page"' : '' ?>>
                        <div class="h-24 bg-gray-100 dark:bg-gray-800 flex items-center justify-center overflow-hidden">
                            <?php if ($img): ?>
                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($mem['title']) ?>"
                                     class="w-full h-full object-cover <?= $isCurrent ? '' : 'group-hover:scale-105' ?> transition-transform" loading="lazy">
                            <?php else: ?>
                                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="p-2">
                            <div class="text-xs font-semibold text-gray-900 dark:text-gray-100 truncate" title="<?= htmlspecialchars((string) $label) ?>">
                                <?= htmlspecialchars((string) $label) ?>
                            </div>
                            <?php if ($isCurrent): ?>
                                <div class="text-[10px] font-bold uppercase tracking-wide text-blue-600 dark:text-blue-400 mt-0.5">Viewing</div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Admin POST handling (runs before output via admin_init_variants)
    // ─────────────────────────────────────────────────────────────────────

    public function handleAdminPost()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        if (!function_exists('verifyCsrfToken') || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            return; // Ignore forged / stale requests silently; page just reloads.
        }

        $action = $_POST['variant_action'] ?? '';
        $redirect = SITE_URL . '/admin/module_page.php?m=variants';

        switch ($action) {
            case 'create_group':
                $title = trim((string) ($_POST['title'] ?? ''));
                if ($title !== '') {
                    $this->createGroup($title, trim((string) ($_POST['group_type'] ?? '')) ?: null, trim((string) ($_POST['description'] ?? '')) ?: null);
                }
                break;

            case 'update_group':
                $gid = (int) ($_POST['group_id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                if ($gid > 0 && $title !== '') {
                    $stmt = $this->pdo->prepare("UPDATE module_variant_groups SET title = ?, group_type = ?, description = ? WHERE id = ?");
                    $stmt->execute([
                        $title,
                        trim((string) ($_POST['group_type'] ?? '')) ?: null,
                        trim((string) ($_POST['description'] ?? '')) ?: null,
                        $gid,
                    ]);
                }
                $redirect .= '&group=' . $gid;
                break;

            case 'delete_group':
                $gid = (int) ($_POST['group_id'] ?? 0);
                if ($gid > 0) {
                    // Members are removed automatically by ON DELETE CASCADE.
                    $this->pdo->prepare("DELETE FROM module_variant_groups WHERE id = ?")->execute([$gid]);
                }
                break;

            case 'add_member':
                $gid = (int) ($_POST['group_id'] ?? 0);
                $iid = (int) ($_POST['item_id'] ?? 0);
                if ($gid > 0 && $iid > 0) {
                    $label = trim((string) ($_POST['variant_label'] ?? '')) ?: null;
                    $stmt = $this->pdo->prepare("
                        INSERT INTO module_variant_members (group_id, item_id, variant_label, sort_order)
                        VALUES (:g, :i, :l, 0)
                        ON DUPLICATE KEY UPDATE group_id = VALUES(group_id), variant_label = VALUES(variant_label)
                    ");
                    $stmt->execute([':g' => $gid, ':i' => $iid, ':l' => $label]);
                }
                $redirect .= '&group=' . $gid;
                break;

            case 'update_member':
                $gid = (int) ($_POST['group_id'] ?? 0);
                $mid = (int) ($_POST['member_id'] ?? 0);
                if ($mid > 0) {
                    $stmt = $this->pdo->prepare("UPDATE module_variant_members SET variant_label = ?, sort_order = ? WHERE id = ?");
                    $stmt->execute([
                        trim((string) ($_POST['variant_label'] ?? '')) ?: null,
                        (int) ($_POST['sort_order'] ?? 0),
                        $mid,
                    ]);
                }
                $redirect .= '&group=' . $gid;
                break;

            case 'remove_member':
                $gid = (int) ($_POST['group_id'] ?? 0);
                $mid = (int) ($_POST['member_id'] ?? 0);
                if ($mid > 0) {
                    $this->pdo->prepare("DELETE FROM module_variant_members WHERE id = ?")->execute([$mid]);
                }
                $redirect .= '&group=' . $gid;
                break;
        }

        header('Location: ' . $redirect);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────

    /** Creates a group with a unique slug and returns its id (or 0 on failure). */
    private function createGroup(string $title, ?string $type = null, ?string $description = null): int
    {
        $base = $this->slugify($title);
        if ($base === '') {
            $base = 'variant-group';
        }
        $slug = $base;
        $n = 2;
        while (true) {
            $chk = $this->pdo->prepare("SELECT id FROM module_variant_groups WHERE slug = ?");
            $chk->execute([$slug]);
            if (!$chk->fetchColumn()) {
                break;
            }
            $slug = $base . '-' . $n++;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO module_variant_groups (title, slug, group_type, description) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$title, $slug, $type, $description]);
        return (int) $this->pdo->lastInsertId();
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    /** Builds a display URL for an uploaded image path, S3-aware. */
    private function imageUrl(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        global $storage;
        if (isset($storage) && is_object($storage) && method_exists($storage, 'url')) {
            return $storage->url('display/' . $path);
        }
        return SITE_URL . '/uploads/display/' . rawurlencode($path);
    }
}
