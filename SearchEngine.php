<?php

/**
 * Faceted Search Engine using PDO
 * Supports multi-category filtering via category_ids[] array.
 */
class SearchEngine {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Build token vocabulary for spelling correction from items and categories.
     * Caches in-memory per request to avoid multiple queries.
     */
    private ?array $vocabulary = null;

    /**
     * Build a safe boolean-mode fulltext query from user input.
     * Returns null when no searchable tokens are found.
     */
    private function buildBooleanSearchTerm(string $searchTerm): ?string {
        $searchTerm = trim($searchTerm);
        if ($searchTerm === '') {
            return null;
        }

        if (strpos($searchTerm, '"') !== false) {
            return $searchTerm;
        }

        preg_match_all('/[\p{L}\p{N}]+/u', $searchTerm, $matches);
        $words = array_values(array_filter($matches[0] ?? [], fn($w) => $w !== ''));
        if (empty($words)) {
            return null;
        }

        return implode('* ', $words) . '*';
    }

    private function getVocabulary(): array {
        if ($this->vocabulary !== null) {
            return $this->vocabulary;
        }
        
        $vocab = [];
        
        // Load from categories
        $stmt = $this->db->query("SELECT name FROM categories");
        while ($row = $stmt->fetchColumn()) {
            $words = str_word_count(strtolower($row), 1);
            foreach ($words as $w) {
                if (strlen($w) > 2) $vocab[$w] = true;
            }
        }
        
        // Load from items (title, description, credit, dates)
        $stmt = $this->db->query("SELECT title, physical_description, credit_line, production_date FROM items WHERE is_visible = 1");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $text = implode(' ', array_filter($row));
            $words = str_word_count(strtolower(strip_tags($text)), 1);
            foreach ($words as $w) {
                if (strlen($w) > 2) $vocab[$w] = true;
            }
        }

        // Load from tags
        $stmt = $this->db->query("SELECT name FROM tags");
        while ($row = $stmt->fetchColumn()) {
            $words = str_word_count(strtolower($row), 1);
            foreach ($words as $w) {
                if (strlen($w) > 2) $vocab[$w] = true;
            }
        }
        
        $this->vocabulary = array_keys($vocab);
        return $this->vocabulary;
    }

    /**
     * Find closest word in vocabulary using Levenshtein distance.
     */
    private function correctWord(string $word): string {
        $word = strtolower($word);
        if (strlen($word) <= 2) return $word; // Too short to correct safely
        
        $vocab = $this->getVocabulary();
        if (in_array($word, $vocab)) {
            return $word; // Word is spelled correctly
        }
        
        $bestMatch = $word;
        $shortestDist = -1;
        
        foreach ($vocab as $v) {
            $dist = levenshtein($word, $v);
            if ($dist === 0) return $v;
            
            // Only consider reasonable corrections (max distance 2 for avg words)
            $maxDist = (strlen($word) > 5) ? 2 : 1;
            
            if ($dist <= $maxDist && ($dist < $shortestDist || $shortestDist < 0)) {
                $bestMatch = $v;
                $shortestDist = $dist;
            }
        }
        
        return $bestMatch;
    }

    /**
     * Attempt spelling correction on the entire query.
     */
    private function correctQuery(string $query): ?string {
        if (empty($query)) return null;
        
        $words = str_word_count($query, 1);
        $correctedWords = [];
        $changed = false;
        
        foreach ($words as $word) {
            $corrected = $this->correctWord($word);
            $correctedWords[] = $corrected;
            if ($corrected !== strtolower($word)) {
                $changed = true;
            }
        }
        
        return $changed ? implode(' ', $correctedWords) : null;
    }

    /**
     * Search items and return results along with dynamic facet counts and search metadata.
     *
     * @param array $params  Keys: q, category_ids (int[]), has_images (bool)
     * @return array         ['results' => [...], 'facets' => [...], 'search_meta' => [...]]
     */
    public function search(array $params = []): array {
        $originalSearchTerm = trim($params['q'] ?? '');
        $categoryIds = array_values(array_filter(array_map('intval', (array)($params['category_ids'] ?? []))));
        $hasImages   = !empty($params['has_images']);
        $tagSlug     = trim($params['tag'] ?? '');
        
        $searchMeta = [
            'original_query' => $originalSearchTerm,
            'corrected_query' => null,
            'was_corrected' => false
        ];
        
        // Apply spelling correction if there's a search term and we aren't bypassing
        $searchTerm = $originalSearchTerm;
        $isExact = !empty($params['exact']);
        if (!empty($originalSearchTerm) && !$isExact) {
            $correctedTerm = $this->correctQuery($originalSearchTerm);
            if ($correctedTerm && $correctedTerm !== strtolower($originalSearchTerm)) {
                $searchMeta['corrected_query'] = $correctedTerm;
                $searchMeta['was_corrected'] = true;
                $searchTerm = $correctedTerm;
            }
        }


        // ── 1. Items query ───────────────────────────────────────────────
        $sql      = "SELECT DISTINCT i.*, pm.file_path AS preview_file_path FROM items i ";
        $sql     .= "LEFT JOIN ("
                .  "SELECT m.item_id, m.file_path "
                .  "FROM media m "
                .  "INNER JOIN ("
                .      "SELECT item_id, MIN(id) AS min_media_id "
                .      "FROM media "
                .      "WHERE media_type = 'image' "
                .      "GROUP BY item_id"
                .  ") first_media ON first_media.min_media_id = m.id"
                .  ") pm ON pm.item_id = i.id ";
        $where    = [];
        $bindings = [];

        if ($hasImages) {
            $sql .= "INNER JOIN media m ON i.id = m.item_id ";
        }

        if (!empty($searchTerm)) {
            $where[] = "MATCH(i.title, i.physical_description) AGAINST(:search IN BOOLEAN MODE)";
            $bindings[':search'] = $searchTerm . '*';
        }

        if (!empty($categoryIds)) {
            // Build safe IN() placeholders
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $where[] = "i.category_id IN ({$placeholders})";
            // These go in as positional bindings executed separately
        }

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $this->db->prepare($sql);

        // Bind named params first, then positional category IDs
        $pos = 1;
        foreach ($bindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        // Positional bindings for IN() are after named ones in the SQL
        // Actually, mix of named+positional is not allowed in PDO — rebuild properly:
        $stmt = $this->buildAndRun($searchTerm, $categoryIds, $hasImages, $tagSlug, false);

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── 2. Category facet counts ─────────────────────────────────────
        // Intentionally does NOT filter by category_ids so counts stay stable
        $facetSql  = "SELECT c.id, c.name, COUNT(DISTINCT i.id) as facet_count FROM categories c LEFT JOIN items i ON c.id = i.category_id ";
        $facetWhere = [];
        $facetBind  = [];

        if ($hasImages) {
            $facetSql .= " INNER JOIN media m2 ON i.id = m2.item_id ";
        }

        if (!empty($searchTerm)) {
            $boolTerm = $this->buildBooleanSearchTerm($searchTerm);
            if ($boolTerm !== null) {
                $facetWhere[] = "(MATCH(i.title, i.physical_description) AGAINST(:search IN BOOLEAN MODE) OR MATCH(i.title, i.physical_description) AGAINST(:search_nl IN NATURAL LANGUAGE MODE) OR i.title LIKE :sl1 OR i.physical_description LIKE :sl2 OR i.production_date LIKE :sl3 OR i.credit_line LIKE :sl4)";
                $facetBind[':search'] = $boolTerm;
            } else {
                $facetWhere[] = "(MATCH(i.title, i.physical_description) AGAINST(:search_nl IN NATURAL LANGUAGE MODE) OR i.title LIKE :sl1 OR i.physical_description LIKE :sl2 OR i.production_date LIKE :sl3 OR i.credit_line LIKE :sl4)";
            }
            $facetBind[':search_nl'] = $searchTerm;
            $likeTerm = '%' . $searchTerm . '%';
            $facetBind[':sl1'] = $likeTerm;
            $facetBind[':sl2'] = $likeTerm;
            $facetBind[':sl3'] = $likeTerm;
            $facetBind[':sl4'] = $likeTerm;
        }

        if ($facetWhere) {
            $facetSql .= " WHERE " . implode(" AND ", $facetWhere);
        }

        $facetSql .= " GROUP BY c.id, c.name HAVING facet_count > 0 ORDER BY c.name ASC";

        $facetStmt = $this->db->prepare($facetSql);
        foreach ($facetBind as $k => $v) $facetStmt->bindValue($k, $v);
        $facetStmt->execute();
        $categoryFacets = $facetStmt->fetchAll(PDO::FETCH_ASSOC);

        // ── 3. Has-images facet count ────────────────────────────────────
        $imgSql   = "SELECT COUNT(DISTINCT i.id) FROM items i INNER JOIN media m ON i.id = m.item_id ";
        $imgWhere = [];
        $imgBind  = [];

        if (!empty($searchTerm)) {
            $boolTerm = $this->buildBooleanSearchTerm($searchTerm);
            if ($boolTerm !== null) {
                $imgWhere[] = "(MATCH(i.title, i.physical_description) AGAINST(? IN BOOLEAN MODE) OR MATCH(i.title, i.physical_description) AGAINST(? IN NATURAL LANGUAGE MODE) OR i.title LIKE ? OR i.physical_description LIKE ? OR i.production_date LIKE ? OR i.credit_line LIKE ?)";
                $imgBind[] = $boolTerm;
                $imgBind[] = $searchTerm;
            } else {
                $imgWhere[] = "(MATCH(i.title, i.physical_description) AGAINST(? IN NATURAL LANGUAGE MODE) OR i.title LIKE ? OR i.physical_description LIKE ? OR i.production_date LIKE ? OR i.credit_line LIKE ?)";
                $imgBind[] = $searchTerm;
            }
            $likeTerm = '%' . $searchTerm . '%';
            $imgBind[] = $likeTerm;
            $imgBind[] = $likeTerm;
            $imgBind[] = $likeTerm;
            $imgBind[] = $likeTerm;
        }

        if (!empty($categoryIds)) {
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $imgWhere[] = "i.category_id IN ({$placeholders})";
        }

        if ($imgWhere) {
            $imgSql .= " WHERE " . implode(" AND ", $imgWhere);
        }

        $imgStmt = $this->db->prepare($imgSql);
        $pi = 1;
        foreach ($imgBind as $v) { $imgStmt->bindValue($pi++, $v); }
        foreach ($categoryIds as $cid)  { $imgStmt->bindValue($pi++, $cid, PDO::PARAM_INT); }
        $imgStmt->execute();
        $hasImagesCount = $imgStmt->fetchColumn();

        // ── 4. Tag facet counts ─────────────────────────────────────────────
        $tagFacetSql = "SELECT t.id, t.name, t.slug, COUNT(DISTINCT it.item_id) AS facet_count
            FROM tags t
            INNER JOIN item_tag it ON t.id = it.tag_id
            INNER JOIN items i ON it.item_id = i.id";
        $tagFacetWhere = [];
        $tagFacetBind  = [];

        if (!empty($searchTerm)) {
            $tagFacetWhere[] = "(i.title LIKE ? OR i.physical_description LIKE ?)";
            $likeTerm = '%' . $searchTerm . '%';
            $tagFacetBind[] = $likeTerm;
            $tagFacetBind[] = $likeTerm;
        }
        if (!empty($categoryIds)) {
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $tagFacetWhere[] = "i.category_id IN ({$placeholders})";
        }
        if ($tagFacetWhere) {
            $tagFacetSql .= " WHERE " . implode(" AND ", $tagFacetWhere);
        }
        $tagFacetSql .= " GROUP BY t.id, t.name, t.slug HAVING facet_count > 0 ORDER BY facet_count DESC, t.name ASC";

        $tagFacetStmt = $this->db->prepare($tagFacetSql);
        $tpi = 1;
        foreach ($tagFacetBind as $v) { $tagFacetStmt->bindValue($tpi++, $v); }
        foreach ($categoryIds as $cid) { $tagFacetStmt->bindValue($tpi++, $cid, PDO::PARAM_INT); }
        $tagFacetStmt->execute();
        $tagFacets = $tagFacetStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'results' => $items,
            'search_meta' => $searchMeta,
            'facets'  => [
                'categories' => $categoryFacets,
                'has_images' => $hasImagesCount,
                'tags'       => $tagFacets,
            ],
        ];
    }

    /**
     * Build and execute the main items query cleanly avoiding PDO named+positional mixing.
     */
    private function buildAndRun(string $searchTerm, array $categoryIds, bool $hasImages, string $tagSlug, bool $dummy) {
        $sql      = "SELECT DISTINCT i.*, pm.file_path AS preview_file_path FROM items i ";
        $sql     .= "LEFT JOIN ("
                .  "SELECT m.item_id, m.file_path "
                .  "FROM media m "
                .  "INNER JOIN ("
                .      "SELECT item_id, MIN(id) AS min_media_id "
                .      "FROM media "
                .      "WHERE media_type = 'image' "
                .      "GROUP BY item_id"
                .  ") first_media ON first_media.min_media_id = m.id"
                .  ") pm ON pm.item_id = i.id ";
        $parts    = [];
        $values   = [];   // positional only

        if ($hasImages) {
            $sql .= "INNER JOIN media m ON i.id = m.item_id ";
        }

        if (!empty($tagSlug)) {
            $sql .= "INNER JOIN item_tag it_filter ON i.id = it_filter.item_id ";
            $sql .= "INNER JOIN tags t_filter ON it_filter.tag_id = t_filter.id AND t_filter.slug = ? ";
            $values[] = $tagSlug;
        }

        if (!empty($searchTerm)) {
            $boolTerm = $this->buildBooleanSearchTerm($searchTerm);
            if ($boolTerm !== null) {
                $parts[]  = "(MATCH(i.title, i.physical_description) AGAINST(? IN BOOLEAN MODE) OR MATCH(i.title, i.physical_description) AGAINST(? IN NATURAL LANGUAGE MODE) OR i.title LIKE ? OR i.physical_description LIKE ? OR i.production_date LIKE ? OR i.credit_line LIKE ?)";
                $values[] = $boolTerm;
            } else {
                $parts[]  = "(MATCH(i.title, i.physical_description) AGAINST(? IN NATURAL LANGUAGE MODE) OR i.title LIKE ? OR i.physical_description LIKE ? OR i.production_date LIKE ? OR i.credit_line LIKE ?)";
            }
            $values[] = $searchTerm;
            $values[] = '%' . $searchTerm . '%';
            $values[] = '%' . $searchTerm . '%';
            $values[] = '%' . $searchTerm . '%';
            $values[] = '%' . $searchTerm . '%';
        }

        if (!empty($categoryIds)) {
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $parts[]      = "i.category_id IN ({$placeholders})";
            foreach ($categoryIds as $cid) $values[] = $cid;
        }

        if ($parts) {
            $sql .= " WHERE " . implode(" AND ", $parts);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        return $stmt;
    }
}
