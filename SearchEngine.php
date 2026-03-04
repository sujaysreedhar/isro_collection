<?php

/**
 * Faceted Search Engine Engine using PDO
 * Handles complex dynamic count generation via GROUP BY.
 */
class SearchEngine {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Search items and return results along with dynamic facet counts.
     * 
     * @param array $params Contains filters (q, category_id, has_images, date_range)
     * @return array Returns matching items and facet aggregations
     */
    public function search(array $params = []): array {
        $searchTerm = $params['q'] ?? '';
        $categoryId = $params['category_id'] ?? null;
        $hasImages = $params['has_images'] ?? false;
        
        // 1. Fetch Matching Items Query
        $sql = "SELECT DISTINCT i.* FROM items i ";
        $where = [];
        $bindings = [];

        // Dynamic Joins
        if ($hasImages) {
            $sql .= "INNER JOIN media m ON i.id = m.item_id ";
        }

        // FULLTEXT Search
        if (!empty($searchTerm)) {
            $where[] = "MATCH(i.title, i.physical_description) AGAINST(:search IN BOOLEAN MODE)";
            $bindings[':search'] = $searchTerm . '*';
        }

        // Category Filter
        if (!empty($categoryId)) {
            $where[] = "i.category_id = :category_id";
            $bindings[':category_id'] = $categoryId;
        }

        if (count($where) > 0) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $this->db->prepare($sql);
        foreach ($bindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch Dynamic Facet Counts (Categories)
        $facetSql = "
            SELECT c.id, c.name, COUNT(DISTINCT i.id) as facet_count 
            FROM categories c
            LEFT JOIN items i ON c.id = i.category_id
        ";
        
        $facetWhere = [];
        $facetBindings = [];
        
        if (!empty($searchTerm)) {
            $facetWhere[] = "MATCH(i.title, i.physical_description) AGAINST(:search IN BOOLEAN MODE)";
            $facetBindings[':search'] = $searchTerm . '*';
        }
        
        if ($hasImages) {
            $facetSql .= " INNER JOIN media m ON i.id = m.item_id";
        }

        if (count($facetWhere) > 0) {
            $facetSql .= " WHERE " . implode(" AND ", $facetWhere);
        }

        $facetSql .= " GROUP BY c.id, c.name HAVING facet_count > 0";
        
        $facetStmt = $this->db->prepare($facetSql);
        foreach ($facetBindings as $key => $val) {
            $facetStmt->bindValue($key, $val);
        }
        $facetStmt->execute();
        $categoryFacets = $facetStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Fetch Have Images Facet Count dynamically
        $imagesFacetSql = "
            SELECT COUNT(DISTINCT i.id) as count 
            FROM items i 
            INNER JOIN media m ON i.id = m.item_id
        ";
        $imgWhere = [];
        $imgBindings = [];
        
        if (!empty($searchTerm)) {
            $imgWhere[] = "MATCH(i.title, i.physical_description) AGAINST(:search IN BOOLEAN MODE)";
            $imgBindings[':search'] = $searchTerm . '*';
        }
        
        if (!empty($categoryId)) {
            $imgWhere[] = "i.category_id = :category_id";
            $imgBindings[':category_id'] = $categoryId;
        }

        if (count($imgWhere) > 0) {
            $imagesFacetSql .= " WHERE " . implode(" AND ", $imgWhere);
        }

        $imgStmt = $this->db->prepare($imagesFacetSql);
        foreach ($imgBindings as $key => $val) {
            $imgStmt->bindValue($key, $val);
        }
        $imgStmt->execute();
        $hasImagesCount = $imgStmt->fetchColumn();

        return [
            'results' => $items,
            'facets' => [
                'categories' => $categoryFacets, // Returns array of ['id' => X, 'name' => 'Engineering', 'facet_count' => 42]
                'has_images' => $hasImagesCount
            ]
        ];
    }
}
