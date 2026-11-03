<?php
/** Admin-only collection inventory services. Never join these private tables into public records. */
class CollectionInventory {
    private PDO $pdo;
    public const BULK_FIELDS = ['category_id', 'is_visible', 'album', 'page_number', 'sleeve', 'box_id', 'shelf', 'location_notes', 'private_notes'];
    public const DIMENSIONS = ['country' => 'Country', 'year' => 'Year', 'series' => 'Series', 'theme' => 'Theme'];

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function ready(): bool {
        try {
            foreach (['module_storage', 'module_valuations', 'collection_private_details', 'collection_checklists', 'collection_checklist_entries'] as $table) {
                $this->pdo->query("SELECT 1 FROM {$table} LIMIT 0");
            }
            return true;
        } catch (Throwable $e) { return false; }
    }

    public static function text($value, int $limit, string $label): string {
        if (!is_scalar($value) && $value !== null) throw new InvalidArgumentException("{$label} must be text.");
        $value = trim((string)$value);
        if (strlen($value) > $limit) throw new InvalidArgumentException("{$label} is too long (maximum {$limit} bytes).");
        return $value;
    }

    public static function amount($value, string $label): ?string {
        $value = self::text($value, 30, $label);
        if ($value === '') return null;
        if (!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D', $value)) throw new InvalidArgumentException("{$label} must be a non-negative amount with at most two decimal places.");
        $parts = explode('.', $value);
        $whole = ltrim($parts[0], '0');
        return ($whole === '' ? '0' : $whole) . '.' . str_pad($parts[1] ?? '', 2, '0');
    }

    public static function normalizePrivate(array $data): array {
        $result = [];
        foreach (['album' => 100, 'page_number' => 50, 'box_id' => 100, 'sleeve' => 100, 'shelf' => 100, 'location_notes' => 10000, 'private_notes' => 20000] as $key => $limit) {
            $result[$key] = self::text($data[$key] ?? '', $limit, ucwords(str_replace('_', ' ', $key)));
        }
        $result['purchase_price'] = self::amount($data['purchase_price'] ?? '', 'Purchase price');
        $result['current_value'] = self::amount($data['current_value'] ?? '', 'Valuation');
        $result['currency'] = strtoupper(self::text($data['currency'] ?? 'INR', 3, 'Currency'));
        if (!preg_match('/^[A-Z]{3}$/D', $result['currency'])) throw new InvalidArgumentException('Use a three-letter currency code, such as INR, USD, or GBP.');
        $date = self::text($data['purchase_date'] ?? '', 10, 'Purchase date');
        if ($date !== '') {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Purchase date must be a valid date.');
        }
        $result['purchase_date'] = $date === '' ? null : $date;
        return $result;
    }

    public static function normalizeIds(array $ids): array {
        if (!$ids || count($ids) > 100) throw new InvalidArgumentException('Select between 1 and 100 items.');
        $normalized = [];
        foreach ($ids as $id) {
            if (!is_scalar($id) || !preg_match('/^[1-9]\d*$/D', (string)$id) || (int)$id > 2147483647) throw new InvalidArgumentException('Invalid item selection.');
            $normalized[] = (int)$id;
        }
        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_NUMERIC);
        return $normalized;
    }

    public function categories(): array { return $this->pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC); }

    public function search(string $query, int $page = 1): array {
        $query = self::text($query, 200, 'Search');
        $page = max(1, min(100000, $page));
        $where = '';
        $params = [];
        if ($query !== '') {
            $where = ' WHERE (i.title LIKE ? OR i.reg_number LIKE ? OR s.album LIKE ? OR s.box_id LIKE ? OR p.shelf LIKE ? OR p.sleeve LIKE ?)';
            $params = array_fill(0, 6, '%' . $query . '%');
        }
        $base = ' FROM items i LEFT JOIN module_storage s ON s.item_id = i.id LEFT JOIN collection_private_details p ON p.item_id = i.id';
        $count = $this->pdo->prepare('SELECT COUNT(*)' . $base . $where);
        $count->execute($params);
        $stmt = $this->pdo->prepare('SELECT i.id, i.title, i.reg_number, i.is_visible, s.album, s.page_number, s.box_id, p.sleeve, p.shelf' . $base . $where . ' ORDER BY i.id DESC LIMIT 100 OFFSET ' . (($page - 1) * 100));
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => (int)$count->fetchColumn(), 'page' => $page];
    }

    public function selectedItems(array $ids): array {
        $ids = self::normalizeIds($ids);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT i.id, i.title, i.reg_number, i.is_visible, s.album, s.page_number, s.box_id, p.sleeve, p.shelf FROM items i LEFT JOIN module_storage s ON s.item_id = i.id LEFT JOIN collection_private_details p ON p.item_id = i.id WHERE i.id IN ({$marks}) ORDER BY i.id");
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function privateDetails(int $id): array {
        $rows = $this->snapshot([$id]);
        if (!$rows) throw new InvalidArgumentException('Item not found.');
        return $rows[0];
    }

    private function writeRow(string $table, int $id, array $data): void {
        $exists = $this->pdo->prepare("SELECT item_id FROM {$table} WHERE item_id = ?");
        $exists->execute([$id]);
        if ($exists->fetchColumn() !== false) {
            $columns = array_map(static fn($key) => "{$key} = ?", array_keys($data));
            $this->pdo->prepare("UPDATE {$table} SET " . implode(', ', $columns) . ' WHERE item_id = ?')->execute(array_merge(array_values($data), [$id]));
        } else {
            $columns = implode(', ', array_keys($data));
            $marks = implode(', ', array_fill(0, count($data) + 1, '?'));
            $this->pdo->prepare("INSERT INTO {$table} (item_id, {$columns}) VALUES ({$marks})")->execute(array_merge([$id], array_values($data)));
        }
    }

    public function savePrivate(int $id, array $data): void {
        $data = self::normalizePrivate($data);
        $own = !$this->pdo->inTransaction();
        if ($own) $this->pdo->beginTransaction();
        try {
            if (!$this->snapshot([$id], true)) throw new InvalidArgumentException('Item not found.');
            $this->writeRow('module_storage', $id, array_intersect_key($data, array_flip(['album', 'page_number', 'box_id', 'location_notes'])));
            $this->writeRow('module_valuations', $id, array_intersect_key($data, array_flip(['purchase_price', 'current_value', 'currency', 'purchase_date'])));
            $this->writeRow('collection_private_details', $id, array_intersect_key($data, array_flip(['sleeve', 'shelf', 'private_notes'])));
            if ($own) $this->pdo->commit();
        } catch (Throwable $e) {
            if ($own && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function saveExtraFields(int $id, array $data): void {
        $extra = [];
        foreach (['sleeve' => 100, 'shelf' => 100, 'private_notes' => 20000] as $key => $limit) $extra[$key] = self::text($data[$key] ?? '', $limit, $key);
        $this->writeRow('collection_private_details', $id, $extra);
    }

    public function normalizeChanges(array $input): array {
        $changes = [];
        foreach ($input as $key => $value) {
            if (!in_array($key, self::BULK_FIELDS, true)) throw new InvalidArgumentException('Unsupported bulk field.');
            if ($key === 'category_id') {
                if (!is_scalar($value) || !preg_match('/^[1-9]\d*$/D', (string)$value)) throw new InvalidArgumentException('Choose a valid category.');
                $stmt = $this->pdo->prepare('SELECT id FROM categories WHERE id = ?');
                $stmt->execute([(int)$value]);
                if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Category no longer exists.');
                $changes[$key] = (int)$value;
            } elseif ($key === 'is_visible') {
                if (!is_scalar($value) || !in_array((string)$value, ['0', '1'], true)) throw new InvalidArgumentException('Invalid visibility.');
                $changes[$key] = (int)$value;
            } else {
                $limit = $key === 'private_notes' ? 20000 : ($key === 'location_notes' ? 10000 : ($key === 'page_number' ? 50 : 100));
                $changes[$key] = self::text($value, $limit, $key);
            }
        }
        if (!$changes) throw new InvalidArgumentException('Choose at least one field to change.');
        return $changes;
    }

    private function snapshot(array $ids, bool $lock = false): array {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        if ($lock && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $stmt = $this->pdo->prepare("SELECT id FROM items WHERE id IN ({$marks}) ORDER BY id FOR UPDATE");
            $stmt->execute($ids);
            foreach (['module_storage', 'module_valuations', 'collection_private_details', 'item_category'] as $table) {
                $stmt = $this->pdo->prepare("SELECT item_id FROM {$table} WHERE item_id IN ({$marks}) ORDER BY item_id FOR UPDATE");
                $stmt->execute($ids);
            }
        }
        $stmt = $this->pdo->prepare("SELECT i.id, i.reg_number, i.title, i.category_id, i.is_visible, s.album, s.page_number, s.box_id, s.location_notes, p.sleeve, p.shelf, p.private_notes, v.purchase_price, v.current_value, v.currency, v.purchase_date FROM items i LEFT JOIN module_storage s ON s.item_id = i.id LEFT JOIN collection_private_details p ON p.item_id = i.id LEFT JOIN module_valuations v ON v.item_id = i.id WHERE i.id IN ({$marks}) ORDER BY i.id");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $categories = $this->pdo->prepare('SELECT category_id FROM item_category WHERE item_id = ? ORDER BY category_id');
        foreach ($rows as &$row) {
            $categories->execute([$row['id']]);
            $row['category_ids'] = array_map('intval', $categories->fetchAll(PDO::FETCH_COLUMN));
        }
        return $rows;
    }

    public static function fingerprint(array $rows): string { return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)); }

    public function preview(array $ids, array $changes): array {
        $ids = self::normalizeIds($ids);
        $changes = $this->normalizeChanges($changes);
        $rows = $this->snapshot($ids);
        if (count($rows) !== count($ids)) throw new InvalidArgumentException('One or more selected items no longer exist.');
        return ['ids' => $ids, 'changes' => $changes, 'rows' => $rows, 'fingerprint' => self::fingerprint($rows), 'created' => time(), 'token' => bin2hex(random_bytes(32))];
    }

    public function applyPreview(array $preview): int {
        if (!isset($preview['created'], $preview['ids'], $preview['changes'], $preview['fingerprint']) || time() - (int)$preview['created'] > 900) throw new InvalidArgumentException('This preview expired. Generate a new preview.');
        $ids = self::normalizeIds($preview['ids']);
        $changes = $this->normalizeChanges($preview['changes']);
        $this->pdo->beginTransaction();
        try {
            $current = $this->snapshot($ids, true);
            if (!hash_equals($preview['fingerprint'], self::fingerprint($current))) throw new InvalidArgumentException('These records changed since preview. Nothing was saved; generate a fresh preview.');
            foreach ($current as $row) {
                $id = (int)$row['id'];
                $core = array_intersect_key($changes, array_flip(['category_id', 'is_visible']));
                if ($core) {
                    $set = implode(', ', array_map(static fn($key) => "{$key} = ?", array_keys($core)));
                    $this->pdo->prepare("UPDATE items SET {$set} WHERE id = ?")->execute(array_merge(array_values($core), [$id]));
                }
                if (isset($changes['category_id'])) {
                    $this->pdo->prepare('DELETE FROM item_category WHERE item_id = ?')->execute([$id]);
                    $this->pdo->prepare('INSERT INTO item_category (item_id, category_id) VALUES (?, ?)')->execute([$id, $changes['category_id']]);
                }
                foreach (['module_storage' => ['album', 'page_number', 'box_id', 'location_notes'], 'collection_private_details' => ['sleeve', 'shelf', 'private_notes']] as $table => $keys) {
                    $patch = array_intersect_key($changes, array_flip($keys));
                    if ($patch) {
                        $existing = [];
                        foreach ($keys as $key) $existing[$key] = $row[$key] ?? '';
                        $this->writeRow($table, $id, array_replace($existing, $patch));
                    }
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        if (defined('CACHE_DIR')) foreach (['vocabulary.json', 'year_range.json'] as $file) if (is_file(CACHE_DIR . '/' . $file)) @unlink(CACHE_DIR . '/' . $file);
        return count($ids);
    }

    public function checklists(string $dimension = ''): array {
        $sql = 'SELECT c.id, c.name, c.dimension, c.group_value, COUNT(e.id) AS expected_count, SUM(CASE WHEN i.id IS NOT NULL THEN 1 ELSE 0 END) AS owned_count FROM collection_checklists c LEFT JOIN collection_checklist_entries e ON e.checklist_id = c.id LEFT JOIN items i ON i.id = e.item_id';
        $params = [];
        if ($dimension !== '' && isset(self::DIMENSIONS[$dimension])) { $sql .= ' WHERE c.dimension = ?'; $params[] = $dimension; }
        $sql .= ' GROUP BY c.id, c.name, c.dimension, c.group_value ORDER BY c.dimension, c.group_value, c.name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function checklist(int $id): array {
        $stmt = $this->pdo->prepare('SELECT * FROM collection_checklists WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new InvalidArgumentException('Checklist not found.');
        return $row;
    }

    public function entries(int $id): array {
        $stmt = $this->pdo->prepare('SELECT e.id, e.label, e.catalogue_reference, e.item_id, i.id AS owned_item_id, i.reg_number, i.title FROM collection_checklist_entries e LEFT JOIN items i ON i.id = e.item_id WHERE e.checklist_id = ? ORDER BY e.id');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createChecklist(array $data): int {
        $name = self::text($data['name'] ?? '', 255, 'Checklist name');
        $dimension = self::text($data['dimension'] ?? '', 20, 'Group by');
        $value = self::text($data['group_value'] ?? '', 255, 'Group value');
        if ($name === '' || $value === '' || !isset(self::DIMENSIONS[$dimension])) throw new InvalidArgumentException('Provide a checklist name, grouping, and group value.');
        if ($dimension === 'year' && !preg_match('/^\d{4}$/D', $value)) throw new InvalidArgumentException('Use a four-digit year for a year checklist.');
        $stmt = $this->pdo->prepare('INSERT INTO collection_checklists (name, dimension, group_value) VALUES (?, ?, ?)');
        $stmt->execute([$name, $dimension, $value]);
        return (int)$this->pdo->lastInsertId();
    }

    private function validItem($id): ?int {
        if ($id === '' || $id === null || $id === '0' || $id === 0) return null;
        $ids = self::normalizeIds([$id]);
        $stmt = $this->pdo->prepare('SELECT id FROM items WHERE id = ?');
        $stmt->execute([$ids[0]]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('The linked collection item does not exist.');
        return $ids[0];
    }

    public function saveEntry(int $checklistId, int $entryId, array $data): void {
        $this->checklist($checklistId);
        $label = self::text($data['label'] ?? '', 255, 'Expected item');
        $reference = self::text($data['catalogue_reference'] ?? '', 120, 'Catalogue reference');
        $itemId = $this->validItem($data['item_id'] ?? null);
        if ($label === '') throw new InvalidArgumentException('Enter the expected item name.');
        if ($itemId !== null) {
            $duplicate = $this->pdo->prepare('SELECT id FROM collection_checklist_entries WHERE checklist_id = ? AND item_id = ? AND id <> ?');
            $duplicate->execute([$checklistId, $itemId, $entryId]);
            if ($duplicate->fetchColumn() !== false) throw new InvalidArgumentException('This collection record already fills another expected item in this checklist. Choose a different record.');
        }
        if ($entryId > 0) {
            $stmt = $this->pdo->prepare('UPDATE collection_checklist_entries SET label = ?, catalogue_reference = ?, item_id = ? WHERE id = ? AND checklist_id = ?');
            $stmt->execute([$label, $reference, $itemId, $entryId, $checklistId]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO collection_checklist_entries (checklist_id, label, catalogue_reference, item_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$checklistId, $label, $reference, $itemId]);
        }
    }

    public function removeEntry(int $checklistId, int $entryId): void {
        $this->pdo->prepare('DELETE FROM collection_checklist_entries WHERE checklist_id = ? AND id = ?')->execute([$checklistId, $entryId]);
    }

    public function legacySets(): array {
        try { return $this->pdo->query('SELECT id, name FROM module_sets ORDER BY name')->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $e) { return []; }
    }

    public function importSet(int $setId, array $data): int {
        $stmt = $this->pdo->prepare('SELECT label, id FROM module_set_structure WHERE set_id = ? ORDER BY sort_order, id');
        $stmt->execute([$setId]);
        $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$structure) throw new InvalidArgumentException('This set has no expected-item structure. Add expected items in Sets / Checklists first.');
        $this->pdo->beginTransaction();
        try {
            $id = $this->createChecklist($data);
            $owned = $this->pdo->prepare('SELECT item_id FROM module_set_items WHERE set_id = ? AND structure_id = ? ORDER BY item_id LIMIT 1');
            foreach ($structure as $row) {
                $owned->execute([$setId, $row['id']]);
                $this->saveEntry($id, 0, ['label' => $row['label'], 'item_id' => $owned->fetchColumn() ?: null]);
            }
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
