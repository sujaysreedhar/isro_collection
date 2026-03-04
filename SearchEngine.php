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
     * Search items and return results along with dynamic facet counts.
     *
     * @param array $params  Keys: q, category_ids (int[]), has_images (bool)
     * @return array         ['results' => [...], 'facets' => [...]]
     */
    public function search(array $params = []): array {
        $searchTerm  = trim($params['q'] ?? '');
        $categoryIds = array_values(array_filter(array_map('intval', (array)($params['category_ids'] ?? []))));
        $hasImages   = !empty($params['has_images']);

        // ── 1. Items query ───────────────────────────────────────────────
        $sql      = "SELECT DISTINCT i.* FROM items i ";
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
        $stmt = $this->buildAndRun($searchTerm, $categoryIds, $hasImages, false);

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
            $facetWhere[] = "MATCH(i.title, i.physical_description) AGAINST(:search IN BOOLEAN MODE)";
            $facetBind[':search'] = $searchTerm . '*';
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
            $imgWhere[] = "MATCH(i.title, i.physical_description) AGAINST(:search IN BOOLEAN MODE)";
            $imgBind[':search'] = $searchTerm . '*';
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
        foreach ($imgBind as $k => $v) { $imgStmt->bindValue($k, $v); }
        foreach ($categoryIds as $cid)  { $imgStmt->bindValue($pi++, $cid, PDO::PARAM_INT); }
        $imgStmt->execute();
        $hasImagesCount = $imgStmt->fetchColumn();

        return [
            'results' => $items,
            'facets'  => [
                'categories' => $categoryFacets,
                'has_images' => $hasImagesCount,
            ],
        ];
    }

    /**
     * Build and execute the main items query cleanly avoiding PDO named+positional mixing.
     */
    private function buildAndRun(string $searchTerm, array $categoryIds, bool $hasImages, bool $dummy) {
        $sql      = "SELECT DISTINCT i.* FROM items i ";
        $parts    = [];
        $values   = [];   // positional only

        if ($hasImages) {
            $sql .= "INNER JOIN media m ON i.id = m.item_id ";
        }

        if (!empty($searchTerm)) {
            $parts[]  = "MATCH(i.title, i.physical_description) AGAINST(? IN BOOLEAN MODE)";
            $values[] = $searchTerm . '*';
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
