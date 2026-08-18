<?php
// modules/virtual_museum/module.php

require_once __DIR__ . '/lib/ItemSelection.php';

class VirtualMuseumModule extends BaseModule
{
    /** Theme presets exposed in admin + consumed by the 3D renderer. */
    public const THEMES = ['classic', 'dark', 'white_cube'];
    public const LAYOUTS = ['grand_hall', 'l_gallery', 'courtyard_loop'];

    /** Focal-point centerpiece models. 'auto' picks the layout's default. */
    public const CENTERPIECES = ['auto', 'torus_knot', 'icosahedron', 'obelisk', 'globe', 'none'];

    /** @var string|null Cached media ORDER BY clause (is_primary-aware). */
    private $mediaOrder = null;

    /** @var bool|null Whether museum_exhibitions.centerpiece exists (lazily migrated). */
    private $hasCenterpieceColumn = null;

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
            layout VARCHAR(32) NOT NULL DEFAULT 'grand_hall',
            centerpiece VARCHAR(32) NOT NULL DEFAULT 'auto',
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ");

        ModuleDB::createTable($this->pdo, 'museum_exhibition_items', "
            exhibition_id INT NOT NULL,
            item_id INT NOT NULL,
            selection_state ENUM('include','exclude') NOT NULL DEFAULT 'include',
            sort_order INT NULL DEFAULT NULL,
            PRIMARY KEY (exhibition_id, item_id),
            KEY item_id (item_id),
            FOREIGN KEY (exhibition_id) REFERENCES museum_exhibitions(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ");

        ModuleDB::createTable($this->pdo, 'museum_exhibition_sources', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            exhibition_id INT NOT NULL,
            source_type ENUM('category','collection','tag') NOT NULL,
            source_id INT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            UNIQUE KEY exhibition_source (exhibition_id, source_type, source_id),
            KEY source_lookup (source_type, source_id),
            FOREIGN KEY (exhibition_id) REFERENCES museum_exhibitions(id) ON DELETE CASCADE
        ");
    }

    public function deactivate()
    {
        // Data is preserved on disable. Tables are only removed on explicit uninstall.
    }

    public function uninstall()
    {
        ModuleDB::dropTable($this->pdo, 'museum_exhibition_sources');
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
            if (preg_match('#^museum/preview/(\d+)/?$#', $uri, $m)) {
                require_once __DIR__ . '/../../admin/auth.php';
                $_GET['preview_id'] = (int) $m[1];
                require __DIR__ . '/room.php';
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
            header('Content-Type: application/json');
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
                    'layout'      => in_array($ex['layout'] ?? '', self::LAYOUTS, true) ? $ex['layout'] : 'grand_hall',
                    'centerpiece' => in_array($ex['centerpiece'] ?? '', self::CENTERPIECES, true) ? $ex['centerpiece'] : 'auto',
                    'item_count'  => count($items = $this->resolveItems($ex)),
                ],
                'items' => $items,
            ], JSON_UNESCAPED_SLASHES);
            return true;
        });

        // Admin AJAX: create / update / delete / reorder exhibitions
        HookRegistry::addFilter('admin_ajax_museum_save', [$this, 'ajaxSave']);
        HookRegistry::addFilter('admin_ajax_museum_delete', [$this, 'ajaxDelete']);
        HookRegistry::addFilter('admin_ajax_museum_duplicate', [$this, 'ajaxDuplicate']);
        HookRegistry::addFilter('admin_ajax_museum_item_search', [$this, 'ajaxItemSearch']);

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
        $limit = max(1, min(100, (int) ($ex['item_limit'] ?? 40)));
        $candidates = [];
        $sources = $this->getExhibitionSources((int) $ex['id'], $ex);

        foreach ($sources as $sourceOrder => $source) {
            foreach ($this->fetchSourceItems($source) as $nativeOrder => $row) {
                if (empty($row['img'])) continue;
                $row['source_order'] = (int) ($source['sort_order'] ?? $sourceOrder);
                $row['native_order'] = $nativeOrder;
                $candidates[] = $row;
            }
        }

        $overrides = $this->getItemOverrides((int) $ex['id']);
        $manualIds = [];
        foreach ($overrides as $itemId => $override) {
            if (($override['selection_state'] ?? 'include') === 'include') $manualIds[] = (int) $itemId;
        }
        foreach ($this->fetchItemsByIds($manualIds) as $nativeOrder => $row) {
            if (empty($row['img'])) continue;
            $row['source_order'] = PHP_INT_MAX - 1;
            $row['native_order'] = $nativeOrder;
            $candidates[] = $row;
        }

        return array_map([$this, 'formatItemPayload'], VirtualMuseumItemSelection::resolve($candidates, $overrides, $limit));
    }

    public function getExhibitionSources(int $exhibitionId, ?array $legacy = null): array
    {
        if ($this->tableExists('museum_exhibition_sources')) {
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT source_type, source_id, sort_order FROM museum_exhibition_sources
                     WHERE exhibition_id = ? ORDER BY sort_order, id"
                );
                $stmt->execute([$exhibitionId]);
                $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if ($sources) return $sources;
            } catch (\PDOException $e) {
            }
        }
        if ($legacy && in_array($legacy['source_type'] ?? '', ['category', 'collection', 'tag'], true) && !empty($legacy['source_id'])) {
            return [['source_type' => $legacy['source_type'], 'source_id' => (int) $legacy['source_id'], 'sort_order' => 0]];
        }
        return [];
    }

    public function getExhibitionOverrides(int $exhibitionId): array
    {
        return $this->getItemOverrides($exhibitionId);
    }

    private function fetchSourceItems(array $source): array
    {
        $type = $source['source_type'] ?? '';
        $sourceId = (int) ($source['source_id'] ?? 0);
        if ($sourceId <= 0) return [];
        $joins = '';
        $where = '';
        $order = 'i.id DESC';
        if ($type === 'category') {
            $where = 'i.category_id = ?';
        } elseif ($type === 'collection' && $this->tableExists('collection_items')) {
            $joins = ' JOIN collection_items ci ON ci.item_id = i.id';
            $where = 'ci.collection_id = ?';
            $order = 'ci.sort_order ASC, i.id DESC';
        } elseif ($type === 'tag' && $this->tableExists('item_tag')) {
            $joins = ' JOIN item_tag it ON it.item_id = i.id';
            $where = 'it.tag_id = ?';
        } else {
            return [];
        }
        return $this->fetchItemRows($joins, $where, [$sourceId], $order);
    }

    private function fetchItemsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->fetchItemRows('', "i.id IN ({$placeholders})", $ids, 'i.id DESC');
        $positions = array_flip($ids);
        usort($rows, static fn(array $a, array $b): int => ($positions[(int) $a['id']] ?? PHP_INT_MAX) <=> ($positions[(int) $b['id']] ?? PHP_INT_MAX));
        return $rows;
    }

    private function fetchItemRows(string $joins, string $where, array $params, string $order): array
    {
        $ord = $this->mediaOrderClause();
        $imgSub = "(SELECT file_path FROM media WHERE item_id = i.id AND media_type = 'image' ORDER BY {$ord} LIMIT 1)";
        $dimSub = "(SELECT dimensions FROM media WHERE item_id = i.id AND media_type = 'image' ORDER BY {$ord} LIMIT 1)";
        try {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT i.id, i.title, i.reg_number, i.production_date, i.credit_line, i.physical_description,
                        {$imgSub} AS img, {$dimSub} AS dims
                 FROM items i{$joins} WHERE i.is_visible = 1 AND {$where}
                 ORDER BY {$order} LIMIT 500"
            );
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    private function getItemOverrides(int $exhibitionId): array
    {
        $out = [];
        try {
            $stateSql = $this->columnExists('museum_exhibition_items', 'selection_state')
                ? 'selection_state' : "'include' AS selection_state";
            $stmt = $this->pdo->prepare("SELECT item_id, {$stateSql}, sort_order FROM museum_exhibition_items WHERE exhibition_id = ?");
            $stmt->execute([$exhibitionId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $out[(int) $row['item_id']] = [
                    'selection_state' => $row['selection_state'] === 'exclude' ? 'exclude' : 'include',
                    'sort_order' => $row['sort_order'] === null ? null : (int) $row['sort_order'],
                ];
            }
        } catch (\PDOException $e) {
        }
        return $out;
    }

    private function formatItemPayload(array $row): array
    {
        global $storage;
        $file = $row['img'];
        $display = isset($storage) ? $storage->url('display/' . $file) : SITE_URL . '/uploads/display/' . rawurlencode($file);
        $thumb = isset($storage) ? $storage->url('thumbnails/' . $file) : SITE_URL . '/uploads/thumbnails/' . rawurlencode($file);
        $ratio = null;
        if (!empty($row['dims']) && preg_match('/^(\d+)\s*[xX]\s*(\d+)$/', trim($row['dims']), $dm) && (int) $dm[2] > 0) {
            $ratio = round((int) $dm[1] / (int) $dm[2], 4);
        }
        $desc = trim(strip_tags($row['physical_description'] ?? ''));
        if (function_exists('mb_substr') && mb_strlen($desc) > 320) $desc = mb_substr($desc, 0, 317) . '…';
        elseif (strlen($desc) > 320) $desc = substr($desc, 0, 317) . '…';
        return [
            'id' => (int) $row['id'], 'title' => $row['title'], 'reg' => $row['reg_number'],
            'date' => $row['production_date'], 'credit' => $row['credit_line'], 'desc' => $desc,
            'display' => $display, 'thumb' => $thumb, 'ratio' => $ratio,
            'url' => SITE_URL . '/item/' . (int) $row['id'],
        ];
    }

    /**
     * Published exhibitions an item belongs to, resolved in a single query
     * (branches for optional tables are only included when those tables exist).
     * Request-cached: item detail pages call this once per view.
     */
    public function exhibitionsForItem(int $itemId): array
    {
        static $cache = [];
        if (isset($cache[$itemId])) return $cache[$itemId];
        $cache[$itemId] = [];
        try {
            $exhibitions = $this->pdo->query("SELECT * FROM museum_exhibitions WHERE is_published = 1 ORDER BY created_at DESC")
                ->fetchAll(PDO::FETCH_ASSOC);
            foreach ($exhibitions as $exhibition) {
                foreach ($this->resolveItems($exhibition) as $item) {
                    if ((int) $item['id'] === $itemId) {
                        $cache[$itemId][] = $exhibition;
                        break;
                    }
                }
            }
        } catch (\PDOException $e) {
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
        if (!$this->verifyCsrf()) return $this->jsonError('Invalid CSRF token.', 403);
        $stage = $_POST['stage'] ?? 'details';
        if (!in_array($stage, ['details', 'sources', 'curation', 'room_publish'], true)) {
            return $this->jsonError('Invalid editor stage.');
        }
        try {
            if ($stage === 'details') return $this->saveDetailsStage();
            $id = (int) ($_POST['id'] ?? 0);
            $ex = $this->getExhibitionById($id);
            if (!$ex) return $this->jsonError('Exhibition not found.', 404);
            if ($stage === 'sources') return $this->saveSourcesStage($id);
            if ($stage === 'curation') return $this->saveCurationStage($id);
            return $this->saveRoomStage($ex);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->jsonError('Database error while saving.');
        }
    }

    public function getExhibitionById(int $id): ?array
    {
        if ($id <= 0) return null;
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM museum_exhibitions WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    private function saveDetailsStage()
    {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $itemLimit = max(1, min(100, (int) ($_POST['item_limit'] ?? 40)));
        if ($title === '') return $this->jsonError('Title is required.');
        if ($id > 0) {
            $stmt = $this->pdo->prepare('UPDATE museum_exhibitions SET title = ?, description = ?, item_limit = ? WHERE id = ?');
            $stmt->execute([$title, $description, $itemLimit, $id]);
            $ex = $this->getExhibitionById($id);
            return $this->jsonSuccess(['id' => $id, 'slug' => $ex['slug']]);
        }
        $slug = $this->uniqueSlug($title);
        $stmt = $this->pdo->prepare(
            "INSERT INTO museum_exhibitions
             (title, slug, description, source_type, source_id, item_limit, theme, layout, is_published)
             VALUES (?, ?, ?, 'manual', NULL, ?, 'classic', 'grand_hall', 0)"
        );
        $stmt->execute([$title, $slug, $description, $itemLimit]);
        return $this->jsonSuccess(['id' => (int) $this->pdo->lastInsertId(), 'slug' => $slug]);
    }

    private function saveSourcesStage(int $id)
    {
        $sources = json_decode($_POST['sources_json'] ?? '[]', true);
        if (!is_array($sources)) return $this->jsonError('Invalid source payload.');
        $validated = [];
        foreach ($sources as $index => $source) {
            $type = $source['type'] ?? '';
            $sourceId = (int) ($source['id'] ?? 0);
            if (!in_array($type, ['category', 'collection', 'tag'], true) || $sourceId <= 0 || !$this->sourceExists($type, $sourceId)) {
                return $this->jsonError('One of the selected sources is no longer available.');
            }
            $validated[$type . ':' . $sourceId] = [$type, $sourceId, (int) ($source['sort_order'] ?? $index)];
        }
        $this->pdo->beginTransaction();
        $this->pdo->prepare('DELETE FROM museum_exhibition_sources WHERE exhibition_id = ?')->execute([$id]);
        $insert = $this->pdo->prepare(
            'INSERT INTO museum_exhibition_sources (exhibition_id, source_type, source_id, sort_order) VALUES (?, ?, ?, ?)'
        );
        foreach ($validated as [$type, $sourceId, $sortOrder]) $insert->execute([$id, $type, $sourceId, $sortOrder]);
        $this->pdo->commit();
        return $this->jsonSuccess(['source_count' => count($validated)]);
    }

    private function saveCurationStage(int $id)
    {
        $items = json_decode($_POST['items_json'] ?? '[]', true);
        if (!is_array($items)) return $this->jsonError('Invalid curation payload.');
        $this->pdo->beginTransaction();
        $this->pdo->prepare('DELETE FROM museum_exhibition_items WHERE exhibition_id = ?')->execute([$id]);
        $insert = $this->pdo->prepare(
            'INSERT INTO museum_exhibition_items (exhibition_id, item_id, selection_state, sort_order) VALUES (?, ?, ?, ?)'
        );
        $seen = [];
        foreach ($items as $item) {
            $itemId = (int) ($item['item_id'] ?? 0);
            if ($itemId <= 0 || isset($seen[$itemId])) continue;
            $seen[$itemId] = true;
            $state = ($item['selection_state'] ?? 'include') === 'exclude' ? 'exclude' : 'include';
            $sort = array_key_exists('sort_order', $item) && $item['sort_order'] !== null ? (int) $item['sort_order'] : null;
            $insert->execute([$id, $itemId, $state, $sort]);
        }
        $this->pdo->commit();
        return $this->jsonSuccess(['override_count' => count($seen)]);
    }

    private function saveRoomStage(array $ex)
    {
        $theme = in_array($_POST['theme'] ?? '', self::THEMES, true) ? $_POST['theme'] : null;
        $layout = in_array($_POST['layout'] ?? '', self::LAYOUTS, true) ? $_POST['layout'] : null;
        $centerpiece = in_array($_POST['centerpiece'] ?? '', self::CENTERPIECES, true) ? $_POST['centerpiece'] : 'auto';
        $published = !empty($_POST['is_published']) ? 1 : 0;
        if (!$theme || !$layout) return $this->jsonError('Choose a valid room theme and layout.');
        if ($published) {
            $sources = $this->getExhibitionSources((int) $ex['id'], $ex);
            $includes = array_filter($this->getItemOverrides((int) $ex['id']), static fn(array $row): bool => $row['selection_state'] === 'include');
            if (!$sources && !$includes) return $this->jsonError('Add at least one source or hand-picked item before publishing.');
            $preview = $ex;
            $preview['theme'] = $theme;
            $preview['layout'] = $layout;
            if (!$this->resolveItems($preview)) return $this->jsonError('This exhibition has no visible items with images.');
        }
        if ($this->ensureCenterpieceColumn()) {
            $stmt = $this->pdo->prepare('UPDATE museum_exhibitions SET theme = ?, layout = ?, centerpiece = ?, is_published = ? WHERE id = ?');
            $stmt->execute([$theme, $layout, $centerpiece, $published, (int) $ex['id']]);
        } else {
            $stmt = $this->pdo->prepare('UPDATE museum_exhibitions SET theme = ?, layout = ?, is_published = ? WHERE id = ?');
            $stmt->execute([$theme, $layout, $published, (int) $ex['id']]);
        }
        return $this->jsonSuccess(['published' => $published, 'layout' => $layout, 'centerpiece' => $centerpiece]);
    }

    public function ajaxDuplicate($handled)
    {
        header('Content-Type: application/json');
        if (!$this->verifyCsrf()) return $this->jsonError('Invalid CSRF token.', 403);
        $source = $this->getExhibitionById((int) ($_POST['id'] ?? 0));
        if (!$source) return $this->jsonError('Exhibition not found.', 404);
        try {
            $this->pdo->beginTransaction();
            $title = $source['title'] . ' Copy';
            $slug = $this->uniqueSlug($title);
            $stmt = $this->pdo->prepare(
                "INSERT INTO museum_exhibitions
                 (title, slug, description, source_type, source_id, item_limit, theme, layout, is_published)
                 VALUES (?, ?, ?, 'manual', NULL, ?, ?, ?, 0)"
            );
            $stmt->execute([$title, $slug, $source['description'], (int) $source['item_limit'], $source['theme'], $source['layout'] ?? 'grand_hall']);
            $id = (int) $this->pdo->lastInsertId();
            if ($this->ensureCenterpieceColumn()) {
                $this->pdo->prepare('UPDATE museum_exhibitions SET centerpiece = ? WHERE id = ?')
                    ->execute([$source['centerpiece'] ?? 'auto', $id]);
            }
            $this->pdo->prepare(
                'INSERT INTO museum_exhibition_sources (exhibition_id, source_type, source_id, sort_order)
                 SELECT ?, source_type, source_id, sort_order FROM museum_exhibition_sources WHERE exhibition_id = ?'
            )->execute([$id, (int) $source['id']]);
            $this->pdo->prepare(
                'INSERT INTO museum_exhibition_items (exhibition_id, item_id, selection_state, sort_order)
                 SELECT ?, item_id, selection_state, sort_order FROM museum_exhibition_items WHERE exhibition_id = ?'
            )->execute([$id, (int) $source['id']]);
            $this->pdo->commit();
            return $this->jsonSuccess(['id' => $id, 'slug' => $slug]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->jsonError('Could not duplicate the exhibition.');
        }
    }

    public function ajaxItemSearch($handled)
    {
        header('Content-Type: application/json');
        $query = trim($_REQUEST['q'] ?? '');
        $page = max(1, (int) ($_REQUEST['page'] ?? 1));
        $offset = ($page - 1) * 24;
        $ord = $this->mediaOrderClause();
        $imgSub = "(SELECT file_path FROM media WHERE item_id = i.id AND media_type = 'image' ORDER BY {$ord} LIMIT 1)";
        $params = [];
        $where = 'i.is_visible = 1';
        if ($query !== '') {
            $where .= ' AND (i.title LIKE ? OR i.reg_number LIKE ?)';
            $params[] = '%' . $query . '%';
            $params[] = '%' . $query . '%';
        }
        try {
            $stmt = $this->pdo->prepare(
                "SELECT i.id, i.title, i.reg_number, {$imgSub} AS img FROM items i
                 WHERE {$where} HAVING img IS NOT NULL ORDER BY i.id DESC LIMIT 24 OFFSET {$offset}"
            );
            $stmt->execute($params);
            $items = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $items[] = [
                    'id' => (int) $row['id'], 'title' => $row['title'], 'reg' => $row['reg_number'],
                    'thumb' => $this->mediaUrl('thumbnails', $row['img']),
                ];
            }
            return $this->jsonSuccess(['items' => $items, 'page' => $page, 'has_more' => count($items) === 24]);
        } catch (\PDOException $e) {
            return $this->jsonError('Could not search items.');
        }
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

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Installs created before the centerpiece feature lack the column;
     * add it on first use instead of requiring a disable/enable cycle.
     */
    private function ensureCenterpieceColumn(): bool
    {
        if ($this->hasCenterpieceColumn !== null) return $this->hasCenterpieceColumn;
        if ($this->columnExists('museum_exhibitions', 'centerpiece')) {
            return $this->hasCenterpieceColumn = true;
        }
        try {
            $this->pdo->exec("ALTER TABLE museum_exhibitions ADD COLUMN centerpiece VARCHAR(32) NOT NULL DEFAULT 'auto' AFTER layout");
            return $this->hasCenterpieceColumn = true;
        } catch (\PDOException $e) {
            return $this->hasCenterpieceColumn = false;
        }
    }

    private function jsonSuccess(array $data = [])
    {
        echo json_encode(['success' => true] + $data, JSON_UNESCAPED_SLASHES);
        return true;
    }

    private function jsonError(string $message, int $status = 200)
    {
        if ($status !== 200) http_response_code($status);
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
        return true;
    }

    private function sourceExists(string $type, int $id): bool
    {
        $tables = ['category' => ['categories', 'id'], 'collection' => ['collections', 'id'], 'tag' => ['tags', 'id']];
        if (!isset($tables[$type]) || !$this->tableExists($tables[$type][0])) return false;
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM `{$tables[$type][0]}` WHERE `{$tables[$type][1]}` = ?");
            $stmt->execute([$id]);
            return (bool) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            return false;
        }
    }

    private function mediaUrl(string $variant, string $file): string
    {
        global $storage;
        return isset($storage)
            ? $storage->url($variant . '/' . $file)
            : SITE_URL . '/uploads/' . $variant . '/' . rawurlencode($file);
    }

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

    private function columnExists(string $table, string $column): bool
    {
        static $known = [];
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $known)) {
            try {
                $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $this->pdo->quote($column));
                $known[$key] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                $known[$key] = false;
            }
        }
        return $known[$key];
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
