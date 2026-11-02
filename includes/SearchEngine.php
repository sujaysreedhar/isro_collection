<?php

/**
 * Faceted Search Engine using PDO
 * Supports advanced filtering including taxonomy, tags, material, and date ranges.
 */
class SearchEngine {
    private PDO $db;

    // Only these fixed expressions may reach ORDER BY from public controls.
    public const SORT_ORDERS = [
        'recent' => 'i.id DESC',
        'oldest' => 'i.id ASC',
        'title_asc' => 'i.title ASC, i.id ASC',
        'title_desc' => 'i.title DESC, i.id DESC',
    ];

    public static function paginationPlan(int $itemCount, int $limit, int $offset): array {
        $itemLimit = max(0, min($limit, $itemCount - $offset));
        return [
            'item_limit' => $itemLimit,
            'item_offset' => $offset,
            'blog_limit' => $limit - $itemLimit,
            'blog_offset' => max(0, $offset - $itemCount),
        ];
    }

    public static function validateParams(array $params): ?string {
        foreach (['q', 'category', 'tag', 'page', 'year_start', 'year_end', 'has_images', 'exact', 'sort', 'view'] as $key) {
            if (isset($params[$key]) && !is_scalar($params[$key])) return "Invalid $key.";
        }
        if (isset($params['sort']) && !isset(self::SORT_ORDERS[(string)$params['sort']])) return 'Invalid sort.';
        if (isset($params['view']) && !in_array($params['view'], ['grid', 'list'], true)) return 'Invalid view.';
        $query = (string)($params['q'] ?? '');
        if ((function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query)) > 200
            || preg_match_all('/\S+/u', $query) > 10) return 'Search query is too long.';
        foreach (['category_ids', 'category_slugs', 'materials', 'tags'] as $key) {
            if (!isset($params[$key])) continue;
            if (!is_array($params[$key]) || count($params[$key]) > 50) return "Invalid $key.";
            foreach ($params[$key] as $value) if (!is_scalar($value) || strlen((string)$value) > 200) return "Invalid $key.";
        }
        if (isset($params['page']) && (!ctype_digit((string)$params['page']) || (int)$params['page'] < 1 || (int)$params['page'] > 10000)) return 'Invalid page.';
        if (isset($params['limit']) && (!is_int($params['limit']) || $params['limit'] < 1 || $params['limit'] > 100)) return 'Invalid limit.';
        if (isset($params['offset']) && (!is_int($params['offset']) || $params['offset'] < 0 || $params['offset'] > 1000000)) return 'Invalid offset.';
        return null;
    }

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    private ?array $vocabulary = null;
    private array $vocabularyMembership = [];
    private array $vocabularyByLength = [];

    private function buildBooleanSearchTerm(string $searchTerm): ?string {
        $searchTerm = trim($searchTerm);
        if ($searchTerm === '') return null;
        if (strpos($searchTerm, '"') !== false) return $searchTerm;

        preg_match_all('/[\p{L}\p{N}]+/u', $searchTerm, $matches);
        $words = array_values(array_filter($matches[0] ?? [], fn($w) => $w !== ''));
        if (empty($words)) return null;

        return implode('* ', $words) . '*';
    }

    public function rebuildVocabularyCache(): void {
        if (!is_dir(CACHE_DIR) && !mkdir(CACHE_DIR, 0755, true) && !is_dir(CACHE_DIR)) {
            throw new RuntimeException('Search cache directory unavailable.');
        }
        $lock = fopen(CACHE_DIR . '/vocabulary.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) fclose($lock);
            throw new RuntimeException('Search vocabulary rebuild already running.');
        }
        try {
            $this->writeVocabularyCache();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function writeVocabularyCache(): void {
        $vocab = [];
        $stmt = $this->db->query("SELECT name FROM categories");
        while ($row = $stmt->fetchColumn()) foreach (str_word_count(strtolower($row), 1) as $w) if (strlen($w) > 2) $vocab[$w] = true;
        
        $stmt = $this->db->query("SELECT title, physical_description, credit_line, production_date FROM items WHERE is_visible = 1");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) foreach (str_word_count(strtolower(strip_tags(implode(' ', array_filter($row)))), 1) as $w) if (strlen($w) > 2) $vocab[$w] = true;

        $stmt = $this->db->query("SELECT name FROM tags");
        while ($row = $stmt->fetchColumn()) foreach (str_word_count(strtolower($row), 1) as $w) if (strlen($w) > 2) $vocab[$w] = true;
        
        $vocabulary = array_keys($vocab);
        $cacheFile = CACHE_DIR . '/vocabulary.json';
        $temp = tempnam(CACHE_DIR, 'vocabulary-');
        if ($temp === false) throw new RuntimeException('Cannot stage search vocabulary.');
        try {
            if (file_put_contents($temp, json_encode($vocabulary, JSON_THROW_ON_ERROR), LOCK_EX) === false
                || !rename($temp, $cacheFile)) {
                throw new RuntimeException('Cannot publish search vocabulary.');
            }
        } finally {
            if (is_file($temp)) @unlink($temp);
        }
        $this->indexVocabulary($vocabulary);
    }

    private function indexVocabulary(array $words): void {
        $this->vocabulary = $words;
        $this->vocabularyMembership = array_fill_keys($words, true);
        $this->vocabularyByLength = [];
        foreach ($words as $word) {
            if (is_string($word)) $this->vocabularyByLength[strlen($word)][] = $word;
        }
    }

    private function getVocabulary(): array {
        if ($this->vocabulary !== null) return $this->vocabulary;

        $enableCache = AppConfig::get('enable_cache', '1') === '1';
        $cacheFile = CACHE_DIR . '/vocabulary.json';

        if ($enableCache && file_exists($cacheFile)) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached) && array_is_list($cached)) {
                $this->indexVocabulary(array_values(array_filter($cached, 'is_string')));
                return $this->vocabulary;
            }
        }

        // Search remains available without suggestions until the CLI job rebuilds the cache.
        $this->indexVocabulary([]);
        return [];
    }

    private function correctWord(string $word): string {
        $word = strtolower($word);
        if (strlen($word) <= 2) return $word;
        $vocab = $this->getVocabulary();
        if (isset($this->vocabularyMembership[$word])) return $word;
        
        $bestMatch = $word;
        $shortestDist = -1;
        $wordLen = strlen($word);
        $candidateCount = 0;
        foreach (range(max(1, $wordLen - 2), $wordLen + 2) as $length) {
        foreach ($this->vocabularyByLength[$length] ?? [] as $v) {
            if (++$candidateCount > 200) break 2;
            // Length pre-filter: skip words that can't possibly be within edit distance
            if (abs(strlen($v) - $wordLen) > 2) continue;
            $dist = levenshtein($word, $v);
            if ($dist === 0) return $v;
            $maxDist = ($wordLen > 5) ? 2 : 1;
            if ($dist <= $maxDist && ($dist < $shortestDist || $shortestDist < 0)) {
                $bestMatch = $v;
                $shortestDist = $dist;
            }
        }
        }
        return $bestMatch;
    }

    private function correctQuery(string $query): ?string {
        if (empty($query)) return null;
        $words = str_word_count($query, 1);
        $correctedWords = [];
        $changed = false;
        foreach ($words as $word) {
            $corrected = $this->correctWord($word);
            $correctedWords[] = $corrected;
            if ($corrected !== strtolower($word)) $changed = true;
        }
        return $changed ? implode(' ', $correctedWords) : null;
    }

    /** Helper to build where clauses to avoid repetition */
    private function buildWhere(array $params, bool $excludeHasImages = false): array {
        $where = [];
        $joinBindings = [];
        $whereBindings = [];
        $joinSql = "";

        // Enforce catalog visibility unless explicitly requested (e.g. by admin preview)
        if (empty($params['include_hidden'])) {
            $where[] = "i.is_visible = 1";
        }

        $searchTerm = $params['q'] ?? '';
        $categoryIds = $params['category_ids'] ?? [];
        $materials = $params['materials'] ?? [];
        $tags = $params['tags'] ?? [];
        $tagSlug = $params['tag'] ?? '';
        $yearStart = $params['year_start'] ?? null;
        $yearEnd = $params['year_end'] ?? null;
        $hasImages = !empty($params['has_images']);

        if ($hasImages && !$excludeHasImages) {
            $joinSql .= " INNER JOIN media m_filter ON i.id = m_filter.item_id ";
        }

        if (!empty($tagSlug)) {
            $tags[] = $tagSlug;
        }
        $tags = array_unique(array_filter($tags));

        if (!empty($tags)) {
            $placeholders = implode(',', array_fill(0, count($tags), '?'));
            $joinSql .= " INNER JOIN item_tag it_filter ON i.id = it_filter.item_id ";
            $joinSql .= " INNER JOIN tags t_filter ON it_filter.tag_id = t_filter.id AND t_filter.slug IN ({$placeholders}) ";
            foreach ($tags as $t) $joinBindings[] = $t;
        }

        if (!empty($searchTerm)) {
            $boolTerm = $this->buildBooleanSearchTerm($searchTerm);
            if ($boolTerm !== null) {
                $where[] = "(MATCH(i.title, i.physical_description) AGAINST(? IN BOOLEAN MODE) OR MATCH(i.title, i.physical_description) AGAINST(? IN NATURAL LANGUAGE MODE) OR i.title LIKE ? OR i.physical_description LIKE ? OR i.production_date LIKE ? OR i.credit_line LIKE ?)";
                array_push($whereBindings, $boolTerm, $searchTerm);
            } else {
                $where[] = "(MATCH(i.title, i.physical_description) AGAINST(? IN NATURAL LANGUAGE MODE) OR i.title LIKE ? OR i.physical_description LIKE ? OR i.production_date LIKE ? OR i.credit_line LIKE ?)";
                $whereBindings[] = $searchTerm;
            }
            $likeTerm = '%' . $searchTerm . '%';
            array_push($whereBindings, $likeTerm, $likeTerm, $likeTerm, $likeTerm);
        }

        if (!empty($categoryIds)) {
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $joinSql .= " INNER JOIN item_category ic_filter ON i.id = ic_filter.item_id AND ic_filter.category_id IN ({$placeholders}) ";
            foreach ($categoryIds as $cid) $joinBindings[] = $cid;
        }

        // Add support for category slugs (clean URLs)
        $categorySlug = $params['category'] ?? '';
        $categorySlugs = (array)($params['category_slugs'] ?? []);
        if (!empty($categorySlug)) $categorySlugs[] = $categorySlug;
        
        if (!empty($categorySlugs)) {
            $placeholders = implode(',', array_fill(0, count($categorySlugs), '?'));
            $joinSql .= " INNER JOIN item_category ic_slug_filter ON i.id = ic_slug_filter.item_id ";
            $joinSql .= " INNER JOIN categories c_slug_filter ON ic_slug_filter.category_id = c_slug_filter.id AND c_slug_filter.slug IN ({$placeholders}) ";
            foreach ($categorySlugs as $cs) $joinBindings[] = $cs;
        }

        if (!empty($materials)) {
            $placeholders = implode(',', array_fill(0, count($materials), '?'));
            $where[] = "i.material IN ({$placeholders})";
            foreach ($materials as $m) $whereBindings[] = $m;
        }

        if ($yearStart !== null) {
            $where[] = "i.year_start >= ?";
            $whereBindings[] = $yearStart;
        }

        if ($yearEnd !== null) {
            $where[] = "i.year_end <= ?";
            $whereBindings[] = $yearEnd;
        }

        return ['where' => $where, 'bindings' => array_merge($joinBindings, $whereBindings), 'join' => $joinSql];
    }


    public function search(array $params = []): array {
        if ($validation = self::validateParams($params)) throw new InvalidArgumentException($validation);
        $originalSearchTerm = trim($params['q'] ?? '');
        $isExact = !empty($params['exact']);
        
        $searchMeta = ['original_query' => $originalSearchTerm, 'corrected_query' => null, 'was_corrected' => false];
        
        if (!empty($originalSearchTerm) && !$isExact) {
            $correctedTerm = $this->correctQuery($originalSearchTerm);
            if ($correctedTerm && $correctedTerm !== strtolower($originalSearchTerm)) {
                $searchMeta['corrected_query'] = $correctedTerm;
                $searchMeta['was_corrected'] = true;
                $params['q'] = $correctedTerm;
            }
        }

        $baseParts = $this->buildWhere($params);

        // ── 1. Count total matching items (before limit) ────────────────
        $countSql = "SELECT COUNT(DISTINCT i.id) FROM items i " . $baseParts['join'];
        if ($baseParts['where']) $countSql .= " WHERE " . implode(" AND ", $baseParts['where']);
        
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($baseParts['bindings']);
        $itemCount = (int)$countStmt->fetchColumn();
        $providerCounts = ['blog' => 0, 'people' => 0];
        if (class_exists('HookRegistry')) {
            $providerCounts = HookRegistry::applyFilters('search_provider_counts', $providerCounts, $params);
        }
        $blogCount = max(0, (int)($providerCounts['blog'] ?? 0));
        $peopleCount = max(0, (int)($providerCounts['people'] ?? 0));
        $totalResults = $itemCount + $blogCount + $peopleCount;
        $pagePlan = self::paginationPlan($itemCount, (int)($params['limit'] ?? 20), (int)($params['offset'] ?? 0));
        $extraPlan = self::paginationPlan($blogCount, $pagePlan['blog_limit'], $pagePlan['blog_offset']);

        // ── 2. Items query with pagination ───────────────────────────────
        $hasIsPrimary = AppConfig::get('media_has_is_primary', '0') === '1';
        $orderClause = $hasIsPrimary ? "m.is_primary DESC, m.upload_date ASC" : "m.upload_date ASC";

        $sql = "SELECT DISTINCT i.*, "
             . "(SELECT m.file_path FROM media m WHERE m.item_id = i.id AND m.media_type = 'image' ORDER BY {$orderClause} LIMIT 1) AS preview_file_path, "
             . "(SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM categories c JOIN item_category ic ON c.id = ic.category_id WHERE ic.item_id = i.id) AS category_name "
             . "FROM items i ";
        $sql .= $baseParts['join'];
        if ($baseParts['where']) $sql .= " WHERE " . implode(" AND ", $baseParts['where']);
        
        // Add sorting and limiting
        $sql .= ' ORDER BY ' . self::SORT_ORDERS[$params['sort'] ?? 'recent'];
        
        if ($pagePlan['item_limit'] > 0) {
            $sql .= " LIMIT ? OFFSET ?";
        }
        $items = [];
        if ($pagePlan['item_limit'] > 0) {
            $stmt = $this->db->prepare($sql);
            foreach ($baseParts['bindings'] as $k => $v) $stmt->bindValue($k + 1, $v);
            $stmt->bindValue(count($baseParts['bindings']) + 1, $pagePlan['item_limit'], PDO::PARAM_INT);
            $stmt->bindValue(count($baseParts['bindings']) + 2, $pagePlan['item_offset'], PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // ── 3. Category facet counts ─────────────────────────────────────
        $facetSql = "SELECT c.id, c.name, COUNT(DISTINCT i.id) as facet_count FROM categories c INNER JOIN item_category ic ON c.id = ic.category_id INNER JOIN items i ON ic.item_id = i.id " . $baseParts['join'];
        if ($baseParts['where']) $facetSql .= " WHERE " . implode(" AND ", $baseParts['where']);
        $facetSql .= " GROUP BY c.id, c.name HAVING facet_count > 0 ORDER BY c.name ASC";
        
        $facetStmt = $this->db->prepare($facetSql);
        $facetStmt->execute($baseParts['bindings']);
        $categoryFacets = $facetStmt->fetchAll(PDO::FETCH_ASSOC);

        // ── 3. Material facet counts ─────────────────────────────────────
        $matWhere = $baseParts['where'];
        $matWhere[] = "i.material IS NOT NULL AND i.material != ''";
        
        $matSql = "SELECT i.material as name, COUNT(DISTINCT i.id) as facet_count FROM items i " . $baseParts['join'];
        $matSql .= " WHERE " . implode(" AND ", $matWhere);
        $matSql .= " GROUP BY i.material HAVING facet_count > 0 ORDER BY facet_count DESC";
        
        $matStmt = $this->db->prepare($matSql);
        $matStmt->execute($baseParts['bindings']);
        $materialFacets = $matStmt->fetchAll(PDO::FETCH_ASSOC);

        // ── 4. Has-images count ──────────────────────────────────────────
        $imgParts = $this->buildWhere($params, true); // exclude has_image join to get raw count
        $imgSql = "SELECT COUNT(DISTINCT i.id) FROM items i INNER JOIN media m ON i.id = m.item_id " . $imgParts['join'];
        if ($imgParts['where']) $imgSql .= " WHERE " . implode(" AND ", $imgParts['where']);
        $imgStmt = $this->db->prepare($imgSql);
        $imgStmt->execute($imgParts['bindings']);
        $hasImagesCount = $imgStmt->fetchColumn();

        // ── 5. Tag facet counts ─────────────────────────────────────────────
        $tagFacetSql = "SELECT t.id, t.name, t.slug, COUNT(DISTINCT it.item_id) AS facet_count FROM tags t INNER JOIN item_tag it ON t.id = it.tag_id INNER JOIN items i ON it.item_id = i.id " . $baseParts['join'];
        if ($baseParts['where']) $tagFacetSql .= " WHERE " . implode(" AND ", $baseParts['where']);
        $tagFacetSql .= " GROUP BY t.id, t.name, t.slug HAVING facet_count > 0 ORDER BY facet_count DESC, t.name ASC";
        $tagFacetStmt = $this->db->prepare($tagFacetSql);
        $tagFacetStmt->execute($baseParts['bindings']);
        $tagFacets = $tagFacetStmt->fetchAll(PDO::FETCH_ASSOC);

        if (class_exists('HookRegistry')) {
            $params['blog_limit'] = $extraPlan['item_limit'];
            $params['blog_offset'] = $extraPlan['item_offset'];
            $params['people_limit'] = $extraPlan['blog_limit'];
            $params['people_offset'] = $extraPlan['blog_offset'];
            $items = HookRegistry::applyFilters('search_results', $items, $params);
        }

        return [
            'results' => $items,
            'total_results' => $totalResults,
            'search_meta' => $searchMeta,
            'facets'  => [
                'categories' => $categoryFacets,
                'materials'  => $materialFacets,
                'has_images' => $hasImagesCount,
                'tags'       => $tagFacets,
                'year_min'   => AppConfig::get('year_range_min'),
                'year_max'   => AppConfig::get('year_range_max'),
            ],
        ];
    }
}
