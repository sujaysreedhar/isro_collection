<?php

class ThematicTaxonomyModule extends BaseModule
{
    protected function registerAdminMenu()
    {
        HookRegistry::addFilter('admin_sidebar_links', function (array $sections) {
            $sections['catalog']['links']['thematic_taxonomy'] = [
                'url' => SITE_URL . '/admin/module_page.php?m=' . $this->slug,
                'label' => 'Subjects',
                'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />'
            ];
            return $sections;
        });

        HookRegistry::addAction('admin_init_' . $this->slug, function () {
            require_once __DIR__ . '/admin/manage_init.php';
        });

        HookRegistry::addAction('admin_page_' . $this->slug, function () {
            require_once __DIR__ . '/admin/manage.php';
        });
    }

    protected function registerRoutes()
    {
        HookRegistry::addFilter('route_request', function ($handled, $uri) {
            if ($handled) {
                return $handled;
            }

            if ($uri === 'subjects' || $uri === 'subjects.php' || $uri === 'themes' || $uri === 'themes.php') {
                require __DIR__ . '/subjects.php';
                return true;
            }

            if (preg_match('#^(?:subject|theme)/([a-zA-Z0-9_-]+)/?$#', $uri, $matches)) {
                $_GET['slug'] = $matches[1];
                require __DIR__ . '/subject.php';
                return true;
            }

            if (($uri === 'subject.php' || $uri === 'theme.php') && isset($_GET['slug'])) {
                require __DIR__ . '/subject.php';
                return true;
            }

            return $handled;
        }, 10, 2);
    }

    protected function registerHooks()
    {
        $navInjector = function (array $links) {
            foreach ($links as $link) {
                $url = (string)($link['url'] ?? '');
                $normalizedUrl = trim($url, './');
                if (
                    ($link['slug'] ?? '') === 'subjects' ||
                    $normalizedUrl === 'subjects' ||
                    str_ends_with($normalizedUrl, '/subjects')
                ) {
                    return $links;
                }
            }

            $links['subjects'] = [
                'url' => 'subjects',
                'label' => 'Subjects',
                'slug' => 'subjects'
            ];

            return $links;
        };

        HookRegistry::addFilter('frontend_nav_header_links', $navInjector);
        HookRegistry::addFilter('frontend_nav_links', $navInjector);

        HookRegistry::addAction('admin_item_edit_after_fields', function ($id, $item) {
            $themeOptions = $this->getThemeOptions(false);
            $selectedThemeIds = $this->getItemThemeIds((int)$id);
            require __DIR__ . '/views/admin_fields.php';
        }, 17, 2);

        HookRegistry::addAction('item_saved', function ($id) {
            if (!isset($_POST['thematic_taxonomy_present'])) {
                return;
            }

            $this->syncItemThemes((int)$id, $_POST['thematic_taxonomy_theme_ids'] ?? []);
        });

        HookRegistry::addAction('item_after_content', function ($item) {
            $themeTrails = $this->getItemThemeTrails((int)($item['id'] ?? 0));
            if (!$themeTrails) {
                return;
            }

            require __DIR__ . '/views/frontend_display.php';
        });
    }

    public function activate()
    {
        ModuleDB::createTable($this->pdo, 'module_themes', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            description TEXT,
            sort_order INT DEFAULT 0,
            is_public TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_module_themes_parent (parent_id),
            INDEX idx_module_themes_public_sort (is_public, sort_order, name),
            CONSTRAINT fk_module_themes_parent FOREIGN KEY (parent_id) REFERENCES module_themes(id) ON DELETE SET NULL
        ");

        ModuleDB::createTable($this->pdo, 'module_theme_item', "
            theme_id INT NOT NULL,
            item_id INT NOT NULL,
            PRIMARY KEY (theme_id, item_id),
            INDEX idx_module_theme_item_item (item_id),
            CONSTRAINT fk_module_theme_item_theme FOREIGN KEY (theme_id) REFERENCES module_themes(id) ON DELETE CASCADE,
            CONSTRAINT fk_module_theme_item_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
        ");
    }

    public function uninstall()
    {
        ModuleDB::dropTable($this->pdo, 'module_theme_item');
        ModuleDB::dropTable($this->pdo, 'module_themes');
    }

    public function getTheme(int $id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM module_themes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getThemeBySlug(string $slug, bool $publicOnly = false)
    {
        $sql = "SELECT * FROM module_themes WHERE slug = ?";
        if ($publicOnly) {
            $sql .= " AND is_public = 1";
        }
        $stmt = $this->pdo->prepare($sql . " LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function getAllThemes(bool $publicOnly = false): array
    {
        $sql = "SELECT * FROM module_themes";
        if ($publicOnly) {
            $sql .= " WHERE is_public = 1";
        }
        $sql .= " ORDER BY sort_order ASC, name ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getChildThemes(?int $parentId, bool $publicOnly = false): array
    {
        if ($parentId === null) {
            $sql = "SELECT * FROM module_themes WHERE parent_id IS NULL";
            if ($publicOnly) {
                $sql .= " AND is_public = 1";
            }
            $sql .= " ORDER BY sort_order ASC, name ASC";
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }

        $sql = "SELECT * FROM module_themes WHERE parent_id = ?";
        if ($publicOnly) {
            $sql .= " AND is_public = 1";
        }
        $sql .= " ORDER BY sort_order ASC, name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getThemeTree(bool $publicOnly = false): array
    {
        return $this->buildThemeTree($this->getAllThemes($publicOnly), null);
    }

    public function getThemeOptions(bool $publicOnly = false, int $excludeId = 0): array
    {
        $options = [];
        $this->flattenThemeTree($this->getThemeTree($publicOnly), 0, $options, $excludeId);
        return $options;
    }

    public function getItemThemeIds(int $itemId): array
    {
        if ($itemId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare("SELECT theme_id FROM module_theme_item WHERE item_id = ? ORDER BY theme_id ASC");
        $stmt->execute([$itemId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function syncItemThemes(int $itemId, array $themeIds): void
    {
        if ($itemId <= 0) {
            return;
        }

        $themeIds = array_values(array_unique(array_filter(array_map('intval', $themeIds))));

        if ($themeIds) {
            $placeholders = implode(',', array_fill(0, count($themeIds), '?'));
            $stmt = $this->pdo->prepare("SELECT id FROM module_themes WHERE id IN ($placeholders)");
            $stmt->execute($themeIds);
            $themeIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $this->pdo->prepare("DELETE FROM module_theme_item WHERE item_id = ?")->execute([$itemId]);

        if (!$themeIds) {
            return;
        }

        $insert = $this->pdo->prepare("INSERT INTO module_theme_item (theme_id, item_id) VALUES (?, ?)");
        foreach ($themeIds as $themeId) {
            $insert->execute([$themeId, $itemId]);
        }
    }

    public function getItemThemes(int $itemId, bool $publicOnly = false): array
    {
        if ($itemId <= 0) {
            return [];
        }

        $sql = "
            SELECT t.*
            FROM module_themes t
            INNER JOIN module_theme_item mti ON mti.theme_id = t.id
            WHERE mti.item_id = ?
        ";
        if ($publicOnly) {
            $sql .= " AND t.is_public = 1";
        }
        $sql .= " ORDER BY t.sort_order ASC, t.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$itemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getItemThemeTrails(int $itemId): array
    {
        $themes = $this->getItemThemes($itemId, true);
        if (!$themes) {
            return [];
        }

        $trailMap = [];
        foreach ($themes as $theme) {
            $trail = $this->getThemeLineage((int)$theme['id'], true);
            if (!$trail) {
                continue;
            }

            $key = implode('>', array_column($trail, 'id'));
            $trailMap[$key] = $trail;
        }

        return array_values($trailMap);
    }

    public function getThemeLineage(int $themeId, bool $publicOnly = false): array
    {
        $themesById = $this->getThemeMap($publicOnly);
        if (!isset($themesById[$themeId])) {
            return [];
        }

        $lineage = [];
        $cursor = $themeId;
        $guard = 0;

        while ($cursor && isset($themesById[$cursor]) && $guard < 50) {
            $lineage[] = $themesById[$cursor];
            $cursor = (int)($themesById[$cursor]['parent_id'] ?? 0);
            $guard++;
        }

        return array_reverse($lineage);
    }

    public function getDescendantIds(int $themeId, bool $publicOnly = false): array
    {
        $themes = $this->getAllThemes($publicOnly);
        $childrenByParent = [];
        foreach ($themes as $theme) {
            $parent = $theme['parent_id'] === null ? 0 : (int)$theme['parent_id'];
            $childrenByParent[$parent][] = (int)$theme['id'];
        }

        $ids = [];
        $stack = [$themeId];
        while ($stack) {
            $current = array_pop($stack);
            if (in_array($current, $ids, true)) {
                continue;
            }

            $ids[] = $current;
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    public function countItemsForThemes(array $themeIds): int
    {
        $themeIds = array_values(array_unique(array_filter(array_map('intval', $themeIds))));
        if (!$themeIds) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($themeIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT i.id)
            FROM items i
            INNER JOIN module_theme_item mti ON mti.item_id = i.id
            WHERE i.is_visible = 1 AND mti.theme_id IN ($placeholders)
        ");
        $stmt->execute($themeIds);
        return (int)$stmt->fetchColumn();
    }

    public function getItemsForThemes(array $themeIds, int $limit = 24, int $offset = 0): array
    {
        $themeIds = array_values(array_unique(array_filter(array_map('intval', $themeIds))));
        if (!$themeIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($themeIds), '?'));
        $hasIsPrimary = AppConfig::get('media_has_is_primary', '0') === '1';
        $orderClause = $hasIsPrimary ? "m.is_primary DESC, m.upload_date ASC" : "m.upload_date ASC";

        $sql = "
            SELECT DISTINCT i.*,
                (
                    SELECT m.file_path
                    FROM media m
                    WHERE m.item_id = i.id AND m.media_type = 'image'
                    ORDER BY $orderClause
                    LIMIT 1
                ) AS preview_file_path
            FROM items i
            INNER JOIN module_theme_item mti ON mti.item_id = i.id
            WHERE i.is_visible = 1
              AND mti.theme_id IN ($placeholders)
            ORDER BY i.id DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($themeIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getThemeAggregateCounts(bool $publicOnly = false): array
    {
        $themes = $this->getAllThemes($publicOnly);
        if (!$themes) {
            return [];
        }

        $directCounts = [];
        $sql = "
            SELECT mti.theme_id, COUNT(DISTINCT i.id) AS item_count
            FROM module_theme_item mti
            INNER JOIN items i ON i.id = mti.item_id
        ";
        if ($publicOnly) {
            $sql .= " WHERE i.is_visible = 1";
        }
        $sql .= " GROUP BY mti.theme_id";
        foreach ($this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $directCounts[(int)$row['theme_id']] = (int)$row['item_count'];
        }

        $aggregate = [];
        $this->calculateAggregateCounts($this->buildThemeTree($themes, null), $directCounts, $aggregate);
        return $aggregate;
    }

    public function isThemeSlugAvailable(string $slug, int $ignoreId = 0): bool
    {
        $sql = "SELECT id FROM module_themes WHERE slug = ?";
        $params = [$slug];
        if ($ignoreId > 0) {
            $sql .= " AND id != ?";
            $params[] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        return !$stmt->fetchColumn();
    }

    public function wouldCreateCycle(int $themeId, ?int $parentId): bool
    {
        if ($themeId <= 0 || $parentId === null || $parentId <= 0) {
            return false;
        }

        $cursor = $parentId;
        $guard = 0;
        while ($cursor > 0 && $guard < 50) {
            if ($cursor === $themeId) {
                return true;
            }

            $stmt = $this->pdo->prepare("SELECT parent_id FROM module_themes WHERE id = ?");
            $stmt->execute([$cursor]);
            $next = $stmt->fetchColumn();
            if ($next === false || $next === null) {
                return false;
            }

            $cursor = (int)$next;
            $guard++;
        }

        return false;
    }

    public function deleteTheme(int $themeId): void
    {
        if ($themeId <= 0) {
            return;
        }

        $this->pdo->prepare("UPDATE module_themes SET parent_id = NULL WHERE parent_id = ?")->execute([$themeId]);
        $this->pdo->prepare("DELETE FROM module_themes WHERE id = ?")->execute([$themeId]);
    }

    public function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim((string)$text, '-');
    }

    private function getThemeMap(bool $publicOnly = false): array
    {
        $map = [];
        foreach ($this->getAllThemes($publicOnly) as $theme) {
            $map[(int)$theme['id']] = $theme;
        }
        return $map;
    }

    private function buildThemeTree(array $themes, ?int $parentId): array
    {
        $branch = [];
        foreach ($themes as $theme) {
            $themeParent = $theme['parent_id'] === null ? null : (int)$theme['parent_id'];
            if ($themeParent !== $parentId) {
                continue;
            }

            $theme['children'] = $this->buildThemeTree($themes, (int)$theme['id']);
            $branch[] = $theme;
        }

        return $branch;
    }

    private function flattenThemeTree(array $tree, int $depth, array &$options, int $excludeId): void
    {
        foreach ($tree as $theme) {
            if ((int)$theme['id'] !== $excludeId) {
                $options[] = [
                    'id' => (int)$theme['id'],
                    'name' => $theme['name'],
                    'slug' => $theme['slug'],
                    'depth' => $depth,
                    'trail_label' => str_repeat('-- ', $depth) . $theme['name'],
                    'is_public' => (int)$theme['is_public']
                ];
            }

            $this->flattenThemeTree($theme['children'] ?? [], $depth + 1, $options, $excludeId);
        }
    }

    private function calculateAggregateCounts(array $tree, array $directCounts, array &$aggregate): int
    {
        $sum = 0;
        foreach ($tree as $theme) {
            $themeId = (int)$theme['id'];
            $count = $directCounts[$themeId] ?? 0;
            $count += $this->calculateAggregateCounts($theme['children'] ?? [], $directCounts, $aggregate);
            $aggregate[$themeId] = $count;
            $sum += $count;
        }

        return $sum;
    }
}
