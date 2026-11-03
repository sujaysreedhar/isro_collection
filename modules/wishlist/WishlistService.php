<?php
declare(strict_types=1);

/** Private acquisition planning and dated seller observations. Never exposed by public hooks. */
final class WishlistService {
    public const STATUSES = ['searching' => 'Searching', 'shortlisted' => 'Shortlisted', 'ordered' => 'Ordered', 'acquired' => 'Acquired'];
    public const AVAILABILITY = ['available' => 'Available', 'unknown' => 'Not confirmed', 'unavailable' => 'Unavailable', 'sold' => 'Sold'];
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public static function status(string $status): string {
        return ['wanted' => 'searching', 'buying' => 'ordered', 'collected' => 'acquired'][$status] ?? $status;
    }

    private static function text($value, string $label, int $max, bool $required = false): string {
        if (!is_scalar($value) && $value !== null) throw new DomainException($label . ' is invalid.');
        $value = trim((string)$value);
        if (($required && $value === '') || strlen($value) > $max) throw new DomainException($label . ($value === '' ? ' is required.' : ' is too long.'));
        return $value;
    }

    public static function money($value, string $label): ?string {
        $value = self::text($value, $label, 20);
        if ($value === '') return null;
        if (!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $value)) throw new DomainException($label . ' must be a non-negative amount with at most two decimal places.');
        return number_format((float)$value, 2, '.', '');
    }

    public static function currency($value, bool $required): ?string {
        $value = strtoupper(self::text($value, 'Currency', 3));
        if ($value === '' && !$required) return null;
        if (!preg_match('/^[A-Z]{3}$/D', $value)) throw new DomainException('Enter a three-letter currency code, such as INR, USD, or EUR.');
        return $value;
    }

    public static function validateItem(array $input): array {
        $status = self::status(self::text($input['status'] ?? 'searching', 'Status', 20));
        if (!isset(self::STATUSES[$status])) throw new DomainException('Choose a valid collection status.');
        $type = self::text($input['item_type'] ?? 'stamp', 'Item type', 16);
        if (!in_array($type, ['stamp', 'cover', 'other'], true)) throw new DomainException('Choose stamp, cover, or other.');
        $target = self::money($input['target_price'] ?? '', 'Target price');
        $priority = filter_var($input['priority'] ?? 0, FILTER_VALIDATE_INT);
        if ($priority === false || $priority < 0 || $priority > 5) throw new DomainException('Priority must be between 0 and 5.');
        $themeId = self::text($input['theme_id'] ?? '', 'Theme', 12);
        if ($themeId !== '' && (!ctype_digit($themeId) || (int)$themeId < 1)) throw new DomainException('Choose a valid theme.');
        return [
            'name' => self::text($input['name'] ?? '', 'Item name', 255, true),
            'description' => self::text($input['description'] ?? '', 'Notes', 20000),
            'theme_id' => $themeId === '' ? null : (int)$themeId,
            'status' => $status, 'item_type' => $type, 'priority' => $priority,
            'catalogue_ref' => self::text($input['catalogue_ref'] ?? '', 'Catalogue reference', 120),
            'target_price' => $target,
            'target_currency' => self::currency($input['target_currency'] ?? '', $target !== null),
        ];
    }

    public static function validateOffers($input): array {
        if (!is_array($input) || count($input) > 100) throw new DomainException('Add no more than 100 seller offers per item.');
        $offers = []; $ids = [];
        foreach ($input as $row) {
            if (!is_array($row)) throw new DomainException('Invalid seller offer.');
            $name = self::text($row['name'] ?? '', 'Seller name', 255);
            $url = self::text($row['url'] ?? '', 'Seller link', 2000);
            if ($name === '' && $url === '') continue;
            if ($name === '') throw new DomainException('Give each seller a name.');
            if ($url !== '') {
                $parts = parse_url($url);
                if (!filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
                    throw new DomainException('Seller links must use http:// or https:// and must not include login details.');
                }
            }
            $amount = self::money($row['amount'] ?? '', 'Seller price');
            $shipping = self::money($row['shipping'] ?? '', 'Shipping cost');
            $currency = self::currency($row['currency'] ?? '', $amount !== null || $shipping !== null);
            $availability = self::text($row['availability'] ?? 'unknown', 'Availability', 20);
            if (!isset(self::AVAILABILITY[$availability])) throw new DomainException('Choose a valid offer availability.');
            $checked = self::text($row['last_checked'] ?? '', 'Last checked date', 10);
            if ($checked !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $checked);
                if (!$date || $date->format('Y-m-d') !== $checked || $checked > date('Y-m-d')) throw new DomainException('Last checked must be a valid date, no later than today.');
            }
            $idValue = self::text($row['id'] ?? '', 'Seller ID', 12);
            if ($idValue !== '' && (!ctype_digit($idValue) || (int)$idValue < 1)) throw new DomainException('Invalid seller ID.');
            $id = $idValue === '' ? null : (int)$idValue;
            if ($id !== null && isset($ids[$id])) throw new DomainException('The same offer cannot appear twice.');
            if ($id !== null) $ids[$id] = true;
            $offers[] = ['id' => $id, 'name' => $name, 'url' => $url, 'amount' => $amount, 'currency' => $currency, 'shipping' => $shipping, 'availability' => $availability, 'last_checked' => $checked === '' ? null : $checked, 'notes' => self::text($row['notes'] ?? '', 'Seller notes', 10000)];
        }
        return $offers;
    }

    /** Shipping must be known, and prices in different currencies are never compared. */
    public static function cheapestOffers(array $offers): array {
        $best = [];
        foreach ($offers as $offer) {
            if (!empty($offer['archived']) || ($offer['availability'] ?? '') !== 'available' || ($offer['amount'] ?? null) === null || ($offer['shipping'] ?? null) === null || !preg_match('/^[A-Z]{3}$/D', (string)($offer['currency'] ?? ''))) continue;
            $total = (int)round((float)$offer['amount'] * 100) + (int)round((float)$offer['shipping'] * 100);
            $currency = $offer['currency'];
            if (!isset($best[$currency]) || $total < $best[$currency]['total_cents']) $best[$currency] = ['id' => (int)$offer['id'], 'total_cents' => $total, 'seller' => $offer['store_name'] ?? ''];
        }
        ksort($best);
        return $best;
    }

    public function schemaReady(): bool {
        try {
            $this->pdo->query('SELECT catalogue_ref, target_price, collection_item_id FROM module_wishlist_items LIMIT 0');
            $this->pdo->query('SELECT amount, currency, shipping, availability, last_checked, archived FROM module_wishlist_stores LIMIT 0');
            $this->pdo->query('SELECT store_id FROM module_wishlist_price_history LIMIT 0');
            return true;
        } catch (Throwable $e) { return false; }
    }

    public function all(): array {
        $items = $this->pdo->query('SELECT * FROM module_wishlist_items ORDER BY priority DESC, created_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $offers = $this->pdo->query('SELECT * FROM module_wishlist_stores ORDER BY archived, id')->fetchAll(PDO::FETCH_ASSOC);
        $history = $this->pdo->query('SELECT * FROM module_wishlist_price_history ORDER BY recorded_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $historyByStore = []; $offersByItem = [];
        foreach ($history as $entry) $historyByStore[$entry['store_id']][] = $entry;
        foreach ($offers as $offer) { $offer['history'] = $historyByStore[$offer['id']] ?? []; $offersByItem[$offer['wishlist_item_id']][] = $offer; }
        foreach ($items as &$item) { $item['status'] = self::status((string)$item['status']); $item['stores'] = $offersByItem[$item['id']] ?? []; $item['best'] = self::cheapestOffers($item['stores']); }
        unset($item);
        return $items;
    }

    public function save(array $input, ?string $newImage): int {
        $item = self::validateItem($input);
        $offers = self::validateOffers($input['stores'] ?? []);
        $idValue = self::text($input['id'] ?? '', 'Item ID', 12);
        if ($idValue !== '' && (!ctype_digit($idValue) || (int)$idValue < 1)) throw new DomainException('Invalid item ID.');
        $id = (int)$idValue;
        $this->pdo->beginTransaction();
        try {
            if ($item['theme_id'] !== null) {
                $theme = $this->pdo->prepare('SELECT id FROM module_themes WHERE id = ?'); $theme->execute([$item['theme_id']]);
                if (!$theme->fetchColumn()) throw new DomainException('The selected theme no longer exists.');
            }
            if ($id > 0) {
                $lock = $this->pdo->prepare('SELECT * FROM module_wishlist_items WHERE id = ? FOR UPDATE'); $lock->execute([$id]); $existing = $lock->fetch(PDO::FETCH_ASSOC);
                if (!$existing) throw new DomainException('Wanted item not found.');
                $image = $newImage ?? $existing['image_url'];
                $sql = 'UPDATE module_wishlist_items SET name=?, description=?, theme_id=?, status=?, item_type=?, priority=?, catalogue_ref=?, target_price=?, target_currency=?, image_url=? WHERE id=?';
                $values = array_values($item); $values[] = $image; $values[] = $id;
                $this->pdo->prepare($sql)->execute($values);
            } else {
                $values = array_values($item); $values[] = $newImage;
                $this->pdo->prepare('INSERT INTO module_wishlist_items (name, description, theme_id, status, item_type, priority, catalogue_ref, target_price, target_currency, image_url) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute($values);
                $id = (int)$this->pdo->lastInsertId();
            }
            $get = $this->pdo->prepare('SELECT * FROM module_wishlist_stores WHERE wishlist_item_id=? FOR UPDATE'); $get->execute([$id]);
            $existingOffers = [];
            foreach ($get->fetchAll(PDO::FETCH_ASSOC) as $existingOffer) $existingOffers[(int)$existingOffer['id']] = $existingOffer;
            $kept = [];
            foreach ($offers as $offer) {
                $offerId = $offer['id'];
                if ($offerId !== null && !isset($existingOffers[$offerId])) throw new DomainException('A seller offer does not belong to this wanted item.');
                $old = $offerId !== null ? $existingOffers[$offerId] : null;
                $legacyPrice = $offer['amount'] === null ? ($old['price'] ?? '') : $offer['currency'] . ' ' . $offer['amount'];
                $values = [$offer['name'], $offer['url'], $legacyPrice, $offer['notes'], $offer['amount'], $offer['currency'], $offer['shipping'], $offer['availability'], $offer['last_checked']];
                if ($old) {
                    $values[] = $offerId; $values[] = $id;
                    $this->pdo->prepare('UPDATE module_wishlist_stores SET store_name=?, store_url=?, price=?, notes=?, amount=?, currency=?, shipping=?, availability=?, last_checked=?, archived=0 WHERE id=? AND wishlist_item_id=?')->execute($values);
                } else {
                    $values[] = $id;
                    $this->pdo->prepare('INSERT INTO module_wishlist_stores (store_name, store_url, price, notes, amount, currency, shipping, availability, last_checked, wishlist_item_id) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute($values);
                    $offerId = (int)$this->pdo->lastInsertId();
                }
                $kept[$offerId] = true;
                $changed = !$old;
                foreach (['amount','currency','shipping','availability','last_checked'] as $field) {
                    if (($old[$field] ?? null) !== $offer[$field]) $changed = true;
                }
                if ($changed) $this->pdo->prepare('INSERT INTO module_wishlist_price_history (store_id, amount, currency, shipping, availability, checked_on) VALUES (?,?,?,?,?,?)')->execute([$offerId, $offer['amount'], $offer['currency'], $offer['shipping'], $offer['availability'], $offer['last_checked']]);
            }
            foreach ($existingOffers as $offerId => $old) if (!isset($kept[$offerId])) $this->pdo->prepare('UPDATE module_wishlist_stores SET archived=1 WHERE id=? AND wishlist_item_id=?')->execute([$offerId, $id]);
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
}
