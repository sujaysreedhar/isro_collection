<?php
/** Ephemeral SQLite data for visual and behavior checks; never connects to MySQL. */
if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true)) { http_response_code(404); exit; }

class ArchiveSearchFixturePDO extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        // Keep the real controller and search engine; translate MySQL-only syntax.
        // Full-text relevance is not assessed by this visual/interaction fixture.
        $query = str_replace("GROUP_CONCAT(c.name SEPARATOR ', ')", "GROUP_CONCAT(c.name, ', ')", $query);
        $query = preg_replace('/MATCH\(i\.title, i\.physical_description\) AGAINST\(\? IN (?:BOOLEAN|NATURAL LANGUAGE) MODE\)/', "(i.title || ' ' || i.physical_description) LIKE ?", $query);
        return parent::prepare($query, $options);
    }
}

function archiveSearchFixturePdo(): PDO
{
    $pdo = new ArchiveSearchFixturePDO();
    $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, title TEXT, reg_number TEXT, production_date TEXT, year_start INTEGER, year_end INTEGER, material TEXT, physical_description TEXT, credit_line TEXT, is_visible INTEGER)');
    $pdo->exec('CREATE TABLE media (id INTEGER PRIMARY KEY, item_id INTEGER, file_path TEXT, media_type TEXT, upload_date TEXT, is_primary INTEGER)');
    $pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
    $pdo->exec('CREATE TABLE item_category (item_id INTEGER, category_id INTEGER)');
    $pdo->exec('CREATE TABLE tags (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
    $pdo->exec('CREATE TABLE item_tag (item_id INTEGER, tag_id INTEGER)');
    $categoryNames = ['Permanent Pictorial Cancellations', 'Postal Cancellations', 'Stamps', 'Special Cancellations'];
    $tagNames = ['Permanent', 'KARNATAKA', 'Special', 'Kerala', 'Chhattisgarh', 'Science'];
    foreach ($categoryNames as $index => $name) $pdo->prepare('INSERT INTO categories VALUES (?, ?, ?)')->execute([$index + 1, $name, 'category-' . ($index + 1)]);
    foreach ($tagNames as $index => $name) $pdo->prepare('INSERT INTO tags VALUES (?, ?, ?)')->execute([$index + 1, $name, strtolower($name)]);
    $samples = [
        ['National Science Day', 'img_103_9028527f310efc91.webp'],
        ['Chinese Fishing Nets, Kochi', 'img_104_ae99cd3d598b879e.webp'],
        ['Mattancherry Postal Cancellation', 'img_105_0b0144070d03ed58.webp'],
        ['Asiatic Cheetah', '1754573759_6894abbf18f58.jpg'],
        ['World Airmail Day', '1753162139_687f219bc3a19.jpg'],
        ['Space Exploration', '1754559501_6894740d1b0d4.jpg'],
    ];
    for ($id = 1; $id <= 26; $id++) {
        [$title, $image] = $samples[($id - 1) % count($samples)];
        if ($id > 6 && $id < 20) $title .= ' (study ' . $id . ')';
        $year = 2024 + ($id % 3);
        $pdo->prepare('INSERT INTO items VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $id, $title, 'PS-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT),
            $year . '-04-05', $year, $year, $id % 3 ? 'Paper, Ink' : 'Paper',
            'Local preview fixture for the collection search UI.', '', $id === 26 ? 0 : 1,
        ]);
        $pdo->prepare('INSERT INTO item_category VALUES (?, ?)')->execute([$id, ($id % 4) + 1]);
        $pdo->prepare('INSERT INTO item_tag VALUES (?, ?)')->execute([$id, ($id % 6) + 1]);
        if ($id !== 2) $pdo->prepare('INSERT INTO media VALUES (?, ?, ?, ?, ?, ?)')->execute([$id, $id, $image, 'image', '2026-01-01', 1]);
    }
    return $pdo;
}
