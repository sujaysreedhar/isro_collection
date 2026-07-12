<?php
// modules/virtual_museum/module.php

class VirtualMuseumModule extends BaseModule
{
    /** Theme presets exposed in admin + consumed by the 3D renderer. */
    public const THEMES = ['classic', 'dark', 'white_cube'];

    /** @var string|null Cached media ORDER BY clause (is_primary-aware). */
    private $mediaOrder = null;

    // ── Lifecycle ────────────────────────────────────────────────────────────

    public function activate()
    {
        ModuleDB::createTable($this->pdo, 'museum_exhibitions', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            description TEXT,
            source_type ENUM('category','collection','tag','manual') NOT NULL DEFAULT 'category',
            source_id INT DEFAULT NULL,
            item_limit INT NOT NULL DEFAULT 40,
            theme VARCHAR(32) NOT NULL DEFAULT 'classic',
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ");

        ModuleDB::createTable($this->pdo, 'museum_exhibition_items', "
            exhibition_id INT NOT NULL,
            item_id INT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (exhibition_id, item_id),
            KEY item_id (item_id),
            FOREIGN KEY (exhibition_id) REFERENCES museum_exhibitions(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ");
    }

    public function deactivate()
    {
        // Data is preserved on disable. Tables are only removed on explicit uninstall.
    }

    public function uninstall()
    {
        ModuleDB::dropTable($this->pdo, 'museum_exhibition_items');
        ModuleDB::dropTable($this->pdo, 'museum_exhibitions');
    }

    // ── Boot (auto-dispatched sub-methods) ───────────────────────────────────

    protected function registerRoutes()
    {
        HookRegistry::addFilter('route_request', function ($handled, $uri) {
            if ($handled) {
                return $handled;
            }
            if ($uri === 'museum' || $uri === 'museum/') {
                require __DIR__ . '/lobby.php';
                return true;
            }
            if (preg_match('#^museum/([a-zA-Z0-9_-]+)/?$#', $uri, $m)) {
                $_GET['slug'] = $m[1];
                require __DIR__ . '/room.php';
                return true;
            }
            return $handled;
        }, 10, 2);

        HookRegistry::addFilter('frontend_nav_links', function ($links) {
            $links['museum'] = ['url' => SITE_URL . '/museum', 'label' => '3D Museum'];
            return $links;
        });
    }

    protected function registerAdminMenu()
    {
        HookRegistry::addFilter('admin_sidebar_links', function (array $sections) {
            $sections['virtual_museum'] = [
                'label' => 'Virtual Museum',
                'links' => [
                    'exhibitions' => [
                        'url'   => SITE_URL . '/admin/module_page.php?m=virtual_museum',
                        'label' => 'Exhibitions',
                        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 21h18M4 21V9l8-5 8 5v12M9 21v-6h6v6" />',
                    ],
                ],
            ];
            return $sections;
        });

        HookRegistry::addAction('admin_page_virtual_museum', function () {
            require_once __DIR__ . '/admin_exhibitions.php';
        });
    }

    protected function registerHooks()
    {
        // Frontend AJAX: item payload for a given exhibition slug (consumed by museum.js)
        HookRegistry::addFilter('frontend_ajax_museum_data', function ($handled) {
            $slug = trim($_REQUEST['slug'] ?? '');
            $ex = $this->getExhibitionBySlug($slug, true);
            if (!$ex) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Exhibition not found.']);
                return true;
            }
            echo json_encode([
                'success'    => true,
                'exhibition' => [
                    'title'       => $ex['title'],
                    'description' => $ex['description'],
                    'theme'       => in_array($ex['theme'], self::THEMES, true) ? $ex['theme'] : 'classic',
                ],
                'items' => $this->resolveItems($ex),
            ], JSON_UNESCAPED_SLASHES);
            return true;
        });

        // Admin AJAX: create / update / delete / reorder exhibitions
        HookRegistry::addFilter('admin_ajax_museum_save', [$this, 'ajaxSave']);
        HookRegistry::addFilter('admin_ajax_museum_delete', [$this, 'ajaxDelete']);

        // Cross-link from an item's detail page into any published exhibition it belongs to
        HookRegistry::addAction('item_after_content', [$this, 'renderItemCrossLink'], 20, 1);
    }

    // ── Data access ──────────────────────────────────────────────────────────

    public function getExhibitionBySlug(string $slug, bool $publishedOnly = false): ?array
    {
        if ($slug === '') {
            return null;
        }
        $sql = "SELECT * FROM museum_exhibitions WHERE slug = ?" . ($publishedOnly ? " AND is_published = 1" : "");
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Resolve the concrete item payload for an exhibition, ready for JSON output.
     * Dynamic sources (category/collection/tag) resolve live, so new items appear
     * automatically; only 'manual' reads the pivot table.
     */
    public function resolveItems(array $ex): array
    {
        global $storage;

        $limit = max(1, min(100, (int) $ex['item_limit']));
        $ord = $this->mediaOrderClause();
        $imgSub = "(SELECT file_path FROM media WHERE item_id = i.id AND media_type = 'image' ORDER BY {$ord} LIMIT 1)";
        $dimSub = "(SELECT dimensions FROM media WHERE item_id = i.id AND media_type = 'image' ORDER BY {$ord} LIMIT 1)";

        $base = "SELECT i.id, i.title, i.reg_number, i.production_date, i.credit_line, i.physical_description,
                        {$imgSub} AS img, {$dimSub} AS dims
                 FROM items i";
        $where = " WHERE i.is_visible = 1";
        $order = " ORDER BY i.id DESC";
        $params = [];

        switch ($ex['source_type']) {
            case 'category':
                $where .= " AND i.category_id = ?";
                $params[] = (int) $ex['source_id'];
                break;
            case 'collection':
                $base .= " JOIN collection_items ci ON ci.item_id = i.id AND ci.collection_id = ?";
                $params[] = (int) $ex['source_id'];
                $order = " ORDER BY ci.sort_order ASC";
                break;
            case 'tag':
                $base .= " JOIN item_tag it ON it.item_id = i.id AND it.tag_id = ?";
                $params[] = (int) $ex['source_id'];
                break;
            case 'manual':
                $base .= " JOIN museum_exhibition_items mei ON mei.item_id = i.id AND mei.exhibition_id = ?";
                $params[] = (int) $ex['id'];
                $order = " ORDER BY mei.sort_order ASC";
                break;
        }

        try {
            $stmt = $this->pdo->prepare($base . $where . $order . " LIMIT " . $limit);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            if (empty($r['img'])) {
                continue; // no image to hang on the wall
            }
            $file = $r['img'];
            $display = isset($storage)
                ? $storage->url('display/' . $file)
                : SITE_URL . '/uploads/display/' . rawurlencode($file);
            $thumb = isset($storage)
                ? $storage->url('thumbnails/' . $file)
                : SITE_URL . '/uploads/thumbnails/' . rawurlencode($file);

            $ratio = null;
            if (!empty($r['dims']) && preg_match('/^(\d+)\s*[xX]\s*(\d+)$/', trim($r['dims']), $dm)) {
                $h = (int) $dm[2];
                if ($h > 0) {
                    $ratio = round((int) $dm[1] / $h, 4);
                }
            }

            $desc = trim(strip_tags($r['physical_description'] ?? ''));
            if (function_exists('mb_substr') && mb_strlen($desc) > 320) {
                $desc = mb_substr($desc, 0, 317) . '…';
            } elseif (strlen($desc) > 320) {
                $desc = substr($desc, 0, 317) . '…';
            }

            $out[] = [
                'id'      => (int) $r['id'],
                'title'   => $r['title'],
                'reg'     => $r['reg_number'],
                'date'    => $r['production_date'],
                'credit'  => $r['credit_line'],
                'desc'    => $desc,
                'display' => $display,
                'thumb'   => $thumb,
                'ratio'   => $ratio,
                'url'     => SITE_URL . '/item/' . (int) $r['id'],
            ];
        }
        return $out;
    }

    /**
     * Published exhibitions an item belongs to, resolved in a single query
     * (branches for optional tables are only included when those tables exist).
     * Request-cached: item detail pages call this once per view.
     */
    public function exhibitionsForItem(int $itemId): array
    {
        static $cache = [];
        if (isset($cache[$itemId])) {
            return $cache[$itemId];
        }

        $branches = [
            "(e.source_type = 'category' AND EXISTS (SELECT 1 FROM items i WHERE i.id = ? AND i.category_id = e.source_id AND i.is_visible = 1))",
            "(e.source_type = 'manual' AND EXISTS (SELECT 1 FROM museum_exhibition_items mei WHERE mei.item_id = ? AND mei.exhibition_id = e.id))",
        ];
        $params = [$itemId, $itemId];

        if ($this->tableExists('collection_items')) {
            $branches[] = "(e.source_type = 'collection' AND EXISTS (SELECT 1 FROM collection_items ci WHERE ci.item_id = ? AND ci.collection_id = e.source_id))";
            $params[] = $itemId;
        }
        if ($this->tableExists('item_tag')) {
            $branches[] = "(e.source_type = 'tag' AND EXISTS (SELECT 1 FROM item_tag it WHERE it.item_id = ? AND it.tag_id = e.source_id))";
            $params[] = $itemId;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT e.* FROM museum_exhibitions e
                 WHERE e.is_published = 1 AND (" . implode(' OR ', $branches) . ")"
            );
            $stmt->execute($params);
            $cache[$itemId] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $cache[$itemId] = [];
        }
        return $cache[$itemId];
    }

    // ── Frontend injection ───────────────────────────────────────────────────

    public function renderItemCrossLink($item)
    {
        if (empty($item['id'])) {
            return;
        }
        $exhibitions = $this->exhibitionsForItem((int) $item['id']);
        if (empty($exhibitions)) {
            return;
        }
        $ex = $exhibitions[0];
        $url = SITE_URL . '/museum/' . rawurlencode($ex['slug']);
        echo '<div class="mt-6 flex justify-center">';
        echo '<a href="' . htmlspecialchars($url) . '" '
            . 'class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-slate-900 text-white text-sm font-semibold shadow-lg hover:bg-slate-700 transition-colors">';
        echo '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 21h18M4 21V9l8-5 8 5v12M9 21v-6h6v6"/></svg>';
        echo 'Walk through this in the 3D exhibition &ldquo;' . htmlspecialchars($ex['title']) . '&rdquo;';
        echo '</a></div>';
    }

    // ── Admin AJAX ───────────────────────────────────────────────────────────

    public function ajaxSave($handled)
    {
        header('Content-Type: application/json');
        if (!$this->verifyCsrf()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
            return true;
        }

        $id          = (int) ($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sourceType  = $_POST['source_type'] ?? 'category';
        $sourceId    = ($_POST['source_id'] ?? '') === '' ? null : (int) $_POST['source_id'];
        $itemLimit   = max(1, min(100, (int) ($_POST['item_limit'] ?? 40)));
        $theme       = in_array($_POST['theme'] ?? '', self::THEMES, true) ? $_POST['theme'] : 'classic';
        $published   = !empty($_POST['is_published']) ? 1 : 0;

        if ($title === '') {
            echo json_encode(['success' => false, 'error' => 'Title is required.']);
            return true;
        }
        if (!in_array($sourceType, ['category', 'collection', 'tag', 'manual'], true)) {
            echo json_encode(['success' => false, 'error' => 'Invalid source type.']);
            return true;
        }
        if ($sourceType !== 'manual') {
            if ($sourceId === null) {
                echo json_encode(['success' => false, 'error' => 'Please choose a source for this exhibition.']);
                return true;
            }
            $srcTable = ['category' => 'categories', 'collection' => 'collections', 'tag' => 'tags'][$sourceType];
            try {
                $chk = $this->pdo->prepare("SELECT 1 FROM `{$srcTable}` WHERE id = ?");
                $chk->execute([$sourceId]);
                if (!$chk->fetchColumn()) {
                    echo json_encode(['success' => false, 'error' => 'The selected source no longer exists. Refresh and pick another.']);
                    return true;
                }
            } catch (\PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'That source type is not available on this install.']);
                return true;
            }
        }

        $slug = $this->uniqueSlug($title, $id);

        try {
            if ($id > 0) {
                $stmt = $this->pdo->prepare(
                    "UPDATE museum_exhibitions
                     SET title=?, slug=?, description=?, source_type=?, source_id=?, item_limit=?, theme=?, is_published=?
                     WHERE id=?"
                );
                $stmt->execute([$title, $slug, $description, $sourceType, $sourceId, $itemLimit, $theme, $published, $id]);
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO museum_exhibitions (title, slug, description, source_type, source_id, item_limit, theme, is_published)
                     VALUES (?,?,?,?,?,?,?,?)"
                );
                $stmt->execute([$title, $slug, $description, $sourceType, $sourceId, $itemLimit, $theme, $published]);
                $id = (int) $this->pdo->lastInsertId();
            }

            if ($sourceType === 'manual') {
                $this->saveManualItems($id, $_POST['item_ids'] ?? []);
            }

            echo json_encode(['success' => true, 'id' => $id, 'slug' => $slug]);
        } catch (\PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error while saving.']);
        }
        return true;
    }

    public function ajaxDelete($handled)
    {
        header('Content-Type: application/json');
        if (!$this->verifyCsrf()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
            return true;
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid exhibition ID.']);
            return true;
        }
        try {
            $this->pdo->prepare("DELETE FROM museum_exhibitions WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (\PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error while deleting.']);
        }
        return true;
    }

    private function saveManualItems(int $exhibitionId, $itemIds): void
    {
        $this->pdo->prepare("DELETE FROM museum_exhibition_items WHERE exhibition_id = ?")->execute([$exhibitionId]);
        if (!is_array($itemIds) || empty($itemIds)) {
            return;
        }
        $ins = $this->pdo->prepare(
            "INSERT IGNORE INTO museum_exhibition_items (exhibition_id, item_id, sort_order) VALUES (?,?,?)"
        );
        $order = 0;
        foreach ($itemIds as $itemId) {
            $itemId = (int) $itemId;
            if ($itemId > 0) {
                $ins->execute([$exhibitionId, $itemId, $order++]);
            }
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Match the core site's primary-image preference when the column exists. */
    private function mediaOrderClause(): string
    {
        if ($this->mediaOrder === null) {
            $this->mediaOrder = 'upload_date ASC, id ASC';
            try {
                if ($this->pdo->query("SHOW COLUMNS FROM media LIKE 'is_primary'")->fetch()) {
                    $this->mediaOrder = 'is_primary DESC, upload_date ASC, id ASC';
                }
            } catch (\PDOException $e) {
                // keep fallback ordering
            }
        }
        return $this->mediaOrder;
    }

    private function tableExists(string $table): bool
    {
        static $known = [];
        if (!isset($known[$table])) {
            try {
                $known[$table] = (bool) $this->pdo->query("SHOW TABLES LIKE " . $this->pdo->quote($table))->fetchColumn();
            } catch (\PDOException $e) {
                $known[$table] = false;
            }
        }
        return $known[$table];
    }

    private function uniqueSlug(string $title, int $ignoreId = 0): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'exhibition';
        }
        $slug = $base;
        $n = 2;
        while (true) {
            $stmt = $this->pdo->prepare("SELECT id FROM museum_exhibitions WHERE slug = ? AND id <> ?");
            $stmt->execute([$slug, $ignoreId]);
            if (!$stmt->fetch()) {
                return $slug;
            }
            $slug = $base . '-' . $n++;
        }
    }

    private function verifyCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (function_exists('verifyCsrfToken')) {
            return verifyCsrfToken($token);
        }
        return true; // No core CSRF helper present — do not hard-block.
    }
}
