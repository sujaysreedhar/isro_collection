<?php

/**
 * Faceted Search Engine using PDO
 * Supports advanced filtering including taxonomy, tags, material, and date ranges.
 */
class SearchEngine {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    private ?array $vocabulary = null;

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
        $vocab = [];
        $stmt = $this->db->query("SELECT name FROM categories");
        while ($row = $stmt->fetchColumn()) foreach (str_word_count(strtolower($row), 1) as $w) if (strlen($w) > 2) $vocab[$w] = true;
        
        $stmt = $this->db->query("SELECT title, physical_description, credit_line, production_date FROM items WHERE is_visible = 1");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) foreach (str_word_count(strtolower(strip_tags(implode(' ', array_filter($row)))), 1) as $w) if (strlen($w) > 2) $vocab[$w] = true;

        $stmt = $this->db->query("SELECT name FROM tags");
        while ($row = $stmt->fetchColumn()) foreach (str_word_count(strtolower($row), 1) as $w) if (strlen($w) > 2) $vocab[$w] = true;
        
        $vocabulary = array_keys($vocab);
        $cacheFile = CACHE_DIR . '/vocabulary.json';
        file_put_contents($cacheFile, json_encode($vocabulary));
        $this->vocabulary = $vocabulary;
    }

    private function getVocabulary(): array {
        if ($this->vocabulary !== null) return $this->vocabulary;

        $enableCache = AppConfig::get('enable_cache', '1') === '1';
        $cacheFile = CACHE_DIR . '/vocabulary.json';

        if ($enableCache && file_exists($cacheFile)) {
            $this->vocabulary = json_decode(file_get_contents($cacheFile), true);
            if (is_array($this->vocabulary)) return $this->vocabulary;
        }

        $this->rebuildVocabularyCache();
        return $this->vocabulary;
    }

    private function correctWord(string $word): string {
        $word = strtolower($word);
        if (strlen($word) <= 2) return $word;
        $vocab = $this->getVocabulary();
        if (in_array($word, $vocab)) return $word;
        
        $bestMatch = $word;
        $shortestDist = -1;
        foreach ($vocab as $v) {
            $dist = levenshtein($word, $v);
            if ($dist === 0) return $v;
            $maxDist = (strlen($word) > 5) ? 2 : 1;
            if ($dist <= $maxDist && ($dist < $shortestDist || $shortestDist < 0)) {
                $bestMatch = $v;
                $shortestDist = $dist;
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
        $bindings = [];
        $joinSql = "";

        $searchTerm = $params['q'] ?? '';
        $categoryIds = $params['category_ids'] ?? [];
        $materials = $params['materials'] ?? [];
        $tagSlug = $params['tag'] ?? '';
        $yearStart = $params['year_start'] ?? null;
        $yearEnd = $params['year_end'] ?? null;
        $hasImages = !empty($params['has_images']);

        if ($hasImages && !$excludeHasImages) {
            $joinSql .= " INNER JOIN media m_filter ON i.id = m_filter.item_id ";
        }

        if (!empty($tagSlug)) {
            $joinSql .= " INNER JOIN item_tag it_filter ON i.id = it_filter.item_id ";
            $joinSql .= " INNER JOIN tags t_filter ON it_filter.tag_id = t_filter.id AND t_filter.slug = ? ";
            $bindings[] = $tagSlug;
        }

        if (!empty($searchTerm)) {
            $boolTerm = $this->buildBooleanSearchTerm($searchTerm);
            if ($boolTerm !== null) {
                $where[] = "(MATCH(i.title, i.physical_description) AGAINST(? IN BOOLEAN MODE) OR MATCH(i.title, i.physical_description) AGAINST(? IN NATURAL LANGUAGE MODE) OR i.title LIKE ? OR i.physical_description LIKE ? OR i.production_date LIKE ? OR i.credit_line LIKE ?)";
                array_push($bindings, $boolTerm, $searchTerm);
            } else {
                $where[] = "(MATCH(i.title, i.physical_description) AGAINST(? IN NATURAL LANGUAGE MODE) OR i.title LIKE ? OR i.physical_description LIKE ? OR i.production_date LIKE ? OR i.credit_line LIKE ?)";
                $bindings[] = $searchTerm;
            }
            $likeTerm = '%' . $searchTerm . '%';
            array_push($bindings, $likeTerm, $likeTerm, $likeTerm, $likeTerm);
        }

        if (!empty($categoryIds)) {
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $where[] = "i.category_id IN ({$placeholders})";
            foreach ($categoryIds as $cid) $bindings[] = $cid;
        }

        if (!empty($materials)) {
            $placeholders = implode(',', array_fill(0, count($materials), '?'));
            $where[] = "i.material IN ({$placeholders})";
            foreach ($materials as $m) $bindings[] = $m;
        }

        if ($yearStart !== null) {
            $where[] = "i.year_start >= ?";
            $bindings[] = $yearStart;
        }

        if ($yearEnd !== null) {
            $where[] = "i.year_end <= ?";
            $bindings[] = $yearEnd;
        }

        return ['where' => $where, 'bindings' => $bindings, 'join' => $joinSql];
    }


    public function search(array $params = []): array {
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
        $totalResults = (int)$countStmt->fetchColumn();

        // ── 2. Items query with pagination ───────────────────────────────
        $hasIsPrimary = false;
        try {
            $columnStmt = $this->db->query("SHOW COLUMNS FROM media LIKE 'is_primary'");
            $hasIsPrimary = (bool) $columnStmt->fetch();
        } catch (\PDOException $e) {}
        
        $orderClause = $hasIsPrimary ? "m.is_primary DESC, m.upload_date ASC" : "m.upload_date ASC";

        $sql = "SELECT DISTINCT i.*, "
             . "(SELECT m.file_path FROM media m WHERE m.item_id = i.id AND m.media_type = 'image' ORDER BY {$orderClause} LIMIT 1) AS preview_file_path "
             . "FROM items i ";
        $sql .= $baseParts['join'];
        if ($baseParts['where']) $sql .= " WHERE " . implode(" AND ", $baseParts['where']);
        
        // Add sorting and limiting
        $sql .= " ORDER BY i.id DESC"; // Default sort
        
        if (isset($params['limit'])) {
            $sql .= " LIMIT ? OFFSET ?";
        }

        $stmt = $this->db->prepare($sql);
        
        // Bind original bindings
        foreach ($baseParts['bindings'] as $k => $v) {
            $stmt->bindValue($k + 1, $v);
        }
        
        // Bind limit/offset if present as positional parameters
        if (isset($params['limit'])) {
            $stmt->bindValue(count($baseParts['bindings']) + 1, (int)$params['limit'], PDO::PARAM_INT);
            $stmt->bindValue(count($baseParts['bindings']) + 2, (int)($params['offset'] ?? 0), PDO::PARAM_INT);
        }

        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── 3. Category facet counts ─────────────────────────────────────
        $facetSql = "SELECT c.id, c.name, COUNT(DISTINCT i.id) as facet_count FROM categories c LEFT JOIN items i ON c.id = i.category_id " . $baseParts['join'];
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
                'year_min'   => $this->db->query("SELECT MIN(year_start) FROM items WHERE year_start IS NOT NULL")->fetchColumn(),
                'year_max'   => $this->db->query("SELECT MAX(year_end) FROM items WHERE year_end IS NOT NULL")->fetchColumn(),
            ],
        ];
    }
}
