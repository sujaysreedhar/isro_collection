<?php
// Vendored Heroicons preserve the outline icon family already used by the site.
function archiveUiIcon(string $name): string
{
    static $icons = [];
    if (!preg_match('/^[a-z0-9-]+$/D', $name)) return '';
    if (!isset($icons[$name])) {
        $path = dirname(__DIR__) . '/assets/icons/' . $name . '.svg';
        $icons[$name] = is_file($path) ? file_get_contents($path) : '';
    }
    return '<span class="archive-icon" aria-hidden="true">' . $icons[$name] . '</span>';
}
function archiveUiDate(?string $value): string
{
    if (!$value) return '';
    // A catalog date can also be a year, range, or free-form text.
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return $value;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $date->format('d M Y') : $value;
}

function archiveUiExcerpt(string $value): string
{
    $text = trim(strip_tags($value));
    // Preserve UTF-8 without requiring the optional mbstring PHP extension.
    return preg_match('/\A(.{0,180})(.)/us', $text, $parts) ? $parts[1] . '…' : $text;
}

/** Carry the supported search fields between the independent GET controls. */
function archiveUiSearchFields(array $params, array $exclude = []): void
{
    $fields = array_intersect_key($params, array_flip([
        'q', 'category', 'category_ids', 'materials', 'has_images', 'exact',
        'year_start', 'year_end', 'sort', 'view', 'tags',
    ]));
    if (!empty($params['tag'])) $fields['tags'] = array_merge((array)($fields['tags'] ?? []), [$params['tag']]);
    foreach ($fields as $name => $values) {
        if (in_array($name, $exclude, true)) continue;
        foreach (is_array($values) ? array_unique($values) : [$values] as $value) {
            if ($value === null || $value === false || $value === '') continue;
            $fieldName = $name . (is_array($values) ? '[]' : '');
            echo '<input type="hidden" name="' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars((string)$value) . '">';
        }
    }
}
