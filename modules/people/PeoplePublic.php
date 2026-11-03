<?php
declare(strict_types=1);
require_once __DIR__ . '/PeopleSupport.php';

/** Public biography queries and presentation. Private profiles and records stay out of these views. */
final class PeoplePublic
{
    public static function h($value): string { return htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    public static function icon(string $name): string
    {
        if (!preg_match('/^[a-z-]+$/D', $name)) return '';
        $file = dirname(__DIR__, 2) . '/themes/custom/assets/icons/' . $name . '.svg';
        return is_file($file) ? '<span class="bio-icon" aria-hidden="true">' . file_get_contents($file) . '</span>' : '';
    }

    public static function assets(): void
    {
        static $registered = false;
        if ($registered) return;
        $registered = true;
        HookRegistry::addAction('frontend_head', static function() {
            echo AssetManager::renderStyles(['modules/people/assets/people-public.css']);
            echo '<script src="' . self::h(SITE_URL . '/modules/people/assets/people-public.js?v=' . filemtime(__DIR__ . '/assets/people-public.js')) . '" defer></script>';
        }, 30);
    }

    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = '';
        foreach (array_slice($words, 0, 2) as $word) if (preg_match('/^./us', $word, $first)) $letters .= $first[0];
        return function_exists('mb_strtoupper') ? mb_strtoupper($letters) : strtoupper($letters);
    }

    public static function lifespan(array $person): string
    {
        $birth = trim((string)($person['birth_date'] ?? ''));
        $death = trim((string)($person['death_date'] ?? ''));
        if ($birth && $death) return $birth . ' – ' . $death;
        return $birth ? 'Born ' . $birth : ($death ? 'Died ' . $death : '');
    }

    public static function facts(array $person): array
    {
        $facts = [];
        foreach (['birth_date' => 'Born', 'death_date' => 'Died'] as $key => $label) if (!empty($person[$key])) $facts[] = ['label' => $label, 'value' => (string)$person[$key]];
        $extra = json_decode((string)($person['infobox_data'] ?? '[]'), true);
        if (is_array($extra)) foreach (array_slice($extra, 0, 50) as $row) {
            if (!is_array($row) || !is_scalar($row['label'] ?? null) || !is_scalar($row['value'] ?? null)) continue;
            if (trim((string)$row['label']) === '' || trim((string)$row['value']) === '') continue;
            $facts[] = ['label' => (string)$row['label'], 'value' => (string)$row['value']];
        }
        return $facts;
    }

    public static function paragraphs(string $biography): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $biography));
        return $text === '' ? [] : preg_split('/\n\s*\n/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    }

    public static function profile(PDO $pdo, string $slug): ?array
    {
        $stmt = $pdo->prepare('SELECT id, name, slug, birth_date, death_date, short_description, biography, infobox_data, profile_image FROM people WHERE slug = ? AND is_public = 1');
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function records(PDO $pdo, int $personId): array
    {
        $stmt = $pdo->prepare("SELECT DISTINCT i.id, i.title, i.reg_number, i.production_date,
            (SELECT m.file_path FROM media m WHERE m.item_id = i.id AND m.media_type = 'image' ORDER BY m.id ASC LIMIT 1) AS image_path
            FROM items i JOIN item_people ip ON i.id = ip.item_id
            WHERE ip.person_id = ? AND i.is_visible = 1 ORDER BY i.production_date DESC, i.id DESC");
        $stmt->execute([$personId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function directory(PDO $pdo, array $input): array
    {
        $query = is_string($input['q'] ?? null) ? trim($input['q']) : '';
        $query = function_exists('mb_substr') ? mb_substr($query, 0, 150) : substr($query, 0, 150);
        $letter = is_string($input['letter'] ?? null) ? strtoupper($input['letter']) : '';
        if (!preg_match('/^(?:[A-Z]|#)$/D', $letter)) $letter = '';
        $where = ['p.is_public = 1']; $params = [];
        if ($query !== '') {
            $term = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
            $where[] = "(p.name LIKE ? ESCAPE '!' OR p.short_description LIKE ? ESCAPE '!' OR p.biography LIKE ? ESCAPE '!')";
            array_push($params, $term, $term, $term);
        }
        if ($letter === '#') $where[] = "UPPER(SUBSTR(p.name, 1, 1)) NOT BETWEEN 'A' AND 'Z'";
        elseif ($letter !== '') { $where[] = 'UPPER(SUBSTR(p.name, 1, 1)) = ?'; $params[] = $letter; }
        $filter = implode(' AND ', $where);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM people p WHERE ' . $filter);
        $stmt->execute($params); $total = (int)$stmt->fetchColumn();
        $pages = max(1, (int)ceil($total / 12));
        $requestedPage = filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT);
        $page = min($pages, max(1, $requestedPage ?: 1)); $offset = ($page - 1) * 12;
        $stmt = $pdo->prepare("SELECT p.id, p.name, p.slug, p.birth_date, p.death_date, p.short_description, p.profile_image,
            (SELECT COUNT(DISTINCT i.id) FROM item_people ip JOIN items i ON i.id = ip.item_id AND i.is_visible = 1 WHERE ip.person_id = p.id) AS record_count
            FROM people p WHERE {$filter} ORDER BY p.name ASC, p.id ASC LIMIT 12 OFFSET {$offset}");
        $stmt->execute($params);
        return ['people' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'query' => $query, 'letter' => $letter, 'page' => $page, 'pages' => $pages, 'total' => $total];
    }

    public static function directoryUrl(array $view, array $changes = []): string
    {
        $params = array_merge(['q' => $view['query'] ?? '', 'letter' => $view['letter'] ?? '', 'page' => $view['page'] ?? 1], $changes);
        $params = array_filter($params, static fn($value, $key) => $value !== '' && !($key === 'page' && (int)$value === 1), ARRAY_FILTER_USE_BOTH);
        return SITE_URL . '/people.php' . ($params ? '?' . http_build_query($params) : '');
    }
}
