<?php
// modules/virtual_museum/lobby.php — public list of published 3D exhibitions.
// Reached via route_request: /museum. Uses the active theme's header/footer.

global $pdo, $storage;

if (!isset($pdo)) {
    require_once __DIR__ . '/../../config/config.php';
    global $pdo, $storage;
}
require_once __DIR__ . '/../../includes/ThemeManager.php';

$exhibitions = [];
try {
    $stmt = $pdo->query("SELECT * FROM museum_exhibitions WHERE is_published = 1 ORDER BY created_at DESC");
    $exhibitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $exhibitions = [];
}

// Match the core site's primary-image preference when the column exists.
$mediaOrder = 'upload_date ASC, id ASC';
try {
    if ($pdo->query("SHOW COLUMNS FROM media LIKE 'is_primary'")->fetch()) {
        $mediaOrder = 'is_primary DESC, upload_date ASC, id ASC';
    }
} catch (\PDOException $e) {
}

/** Best-effort single cover image filename for an exhibition. */
$coverFile = function (array $ex) use ($pdo, $mediaOrder): ?string {
    $imgSub = "(SELECT file_path FROM media WHERE item_id = i.id AND media_type='image' ORDER BY {$mediaOrder} LIMIT 1)";
    try {
        switch ($ex['source_type']) {
            case 'category':
                $s = $pdo->prepare("SELECT {$imgSub} AS f FROM items i WHERE i.is_visible=1 AND i.category_id=? ORDER BY i.id DESC LIMIT 1");
                $s->execute([(int) $ex['source_id']]);
                break;
            case 'collection':
                $s = $pdo->prepare("SELECT {$imgSub} AS f FROM items i JOIN collection_items ci ON ci.item_id=i.id AND ci.collection_id=? WHERE i.is_visible=1 ORDER BY ci.sort_order ASC LIMIT 1");
                $s->execute([(int) $ex['source_id']]);
                break;
            case 'tag':
                $s = $pdo->prepare("SELECT {$imgSub} AS f FROM items i JOIN item_tag it ON it.item_id=i.id AND it.tag_id=? WHERE i.is_visible=1 ORDER BY i.id DESC LIMIT 1");
                $s->execute([(int) $ex['source_id']]);
                break;
            case 'manual':
                $s = $pdo->prepare("SELECT {$imgSub} AS f FROM items i JOIN museum_exhibition_items mei ON mei.item_id=i.id AND mei.exhibition_id=? WHERE i.is_visible=1 ORDER BY mei.sort_order ASC LIMIT 1");
                $s->execute([(int) $ex['id']]);
                break;
            default:
                return null;
        }
        $f = $s->fetchColumn();
        return $f ?: null;
    } catch (\PDOException $e) {
        return null;
    }
};

$pageTitle   = '3D Museum - ' . SITE_TITLE;
$currentMenu = 'museum';

require_once ThemeManager::getHeader();
?>

<main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-16">

    <header class="mb-12 text-center">
        <div class="text-5xl mb-4">🏛️</div>
        <h1 class="text-4xl font-extrabold text-gray-900 dark:text-white serif">The 3D Museum</h1>
        <p class="mt-3 text-gray-500 dark:text-gray-400 max-w-2xl mx-auto">
            Step inside a walkable gallery and explore the collection in first person.
        </p>
    </header>

    <?php if (empty($exhibitions)): ?>
        <div class="text-center py-24 text-gray-400">
            <div class="text-4xl mb-4">🖼️</div>
            <p class="text-lg">No exhibitions have been published yet.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($exhibitions as $ex): ?>
                <?php
                    $file = $coverFile($ex);
                    $cover = $file
                        ? (isset($storage) ? $storage->url('display/' . $file) : SITE_URL . '/uploads/display/' . rawurlencode($file))
                        : null;
                    $url = SITE_URL . '/museum/' . rawurlencode($ex['slug']);
                ?>
                <a href="<?= htmlspecialchars($url) ?>"
                   class="group relative flex flex-col rounded-3xl overflow-hidden bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/50 hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-500">
                    <div class="relative h-52 w-full bg-gray-900 overflow-hidden">
                        <?php if ($cover): ?>
                            <img src="<?= htmlspecialchars($cover) ?>" alt="<?= htmlspecialchars($ex['title']) ?>"
                                 class="w-full h-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-105 transition-all duration-700">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-5xl">🏛️</div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"></div>
                        <div class="absolute bottom-4 left-4 right-4">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/15 backdrop-blur text-white text-[11px] font-bold uppercase tracking-wider border border-white/20">
                                Enter in 3D &rarr;
                            </span>
                        </div>
                    </div>
                    <div class="p-6 flex flex-col flex-grow">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors line-clamp-1">
                            <?= htmlspecialchars($ex['title']) ?>
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed line-clamp-2">
                            <?= htmlspecialchars(strip_tags($ex['description'] ?? '')) ?: 'A curated walk-through of the collection.' ?>
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

<?php
require_once ThemeManager::getFooter();
