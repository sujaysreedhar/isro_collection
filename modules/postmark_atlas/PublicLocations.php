<?php
declare(strict_types=1);

final class PublicLocations {
    public static function all(PDO $pdo, bool $withCoordinatesOnly): array {
        $sql = 'SELECT pl.id, pl.pin_code, pl.post_office, pl.ppc_name, pl.district, pl.state, '
            . 'pl.latitude, pl.longitude, pl.is_acquired, '
            . 'i.title AS linked_item_title, i.id AS linked_item_id, i.id AS item_link_id '
            . 'FROM postmark_locations pl '
            . 'LEFT JOIN items i ON i.id = pl.linked_item_id AND i.is_visible = 1';
        if ($withCoordinatesOnly) {
            $sql .= ' WHERE pl.latitude != 0 AND pl.longitude != 0 ORDER BY pl.state ASC, pl.district ASC';
        }
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
