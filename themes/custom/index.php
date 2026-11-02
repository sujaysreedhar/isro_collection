<?php
require_once __DIR__ . '/partials/archive_helpers.php';
global $appSettings, $pdo;
$pageTitle = SITE_TITLE;
$currentMenu = 'home';
$bodyClass = 'archive-ui';
$ts = static fn(string $key, string $fallback = '') => $appSettings['theme_studio_' . $key] ?? $fallback;
$showStats = $ts('show_stats', '1') === '1';
$hideHeaderSearch = $ts('show_search', '1') !== '1';
$heroStyle = $ts('hero_style', 'split');
$gridCols = max(2, min(4, (int) $ts('grid_cols', '4')));
$heroImage = $ts('hero_image');
$heroImgUrl = $heroImage && is_file(dirname(__DIR__, 2) . '/uploads/branding/' . $heroImage)
    ? SITE_URL . '/uploads/branding/' . rawurlencode($heroImage)
    : SITE_URL . '/themes/custom/assets/archive-hero.webp';
$heroTitle = $ts('hero_title') ?: 'A Personal Archive of Stamps, Notes, Coins and More';
$heroTagline = $ts('hero_tagline') ?: 'Explore a curated collection of postal cancellations, commemorative stamps, bank notes, coins and special releases from India and around the world.';

// Discovery and totals only include public items.
$orderClause = AppConfig::get('media_has_is_primary', '0') === '1' ? 'm.is_primary DESC, m.upload_date ASC' : 'm.upload_date ASC';
$homeCategories = $pdo->query("
    SELECT c.id, c.name, c.image_path, COUNT(i.id) AS item_count,
        (SELECT m.file_path FROM media m
         INNER JOIN items ci ON ci.id = m.item_id AND ci.is_visible = 1
         INNER JOIN item_category cic ON cic.item_id = ci.id
         WHERE cic.category_id = c.id AND m.media_type = 'image'
         ORDER BY {$orderClause} LIMIT 1) AS sample_image
    FROM categories c
    LEFT JOIN item_category ic ON ic.category_id = c.id
    LEFT JOIN items i ON i.id = ic.item_id AND i.is_visible = 1
    GROUP BY c.id, c.name, c.image_path
    ORDER BY c.id ASC
")->fetchAll(PDO::FETCH_ASSOC);
$collectionStats = ['total_items' => 0, 'with_images' => 0, 'latest_year' => null];
if ($showStats) {
    $collectionStats = $pdo->query("
        SELECT COUNT(*) AS total_items,
            COALESCE(SUM(EXISTS(SELECT 1 FROM media m WHERE m.item_id = i.id AND m.media_type = 'image')), 0) AS with_images,
            MAX(i.year_start) AS latest_year
        FROM items i WHERE i.is_visible = 1
    ")->fetch(PDO::FETCH_ASSOC);
}
require_once ThemeManager::getHeader();
$continueItem = $featuredItems[0] ?? null;
?>

<main class="archive-page archive-home">
    <div class="archive-intro<?= !$showStats ? ' archive-intro--simple' : '' ?>">
        <section class="archive-hero archive-hero--<?= htmlspecialchars(in_array($heroStyle, ['split', 'centered', 'minimal'], true) ? $heroStyle : 'split') ?>" aria-labelledby="archive-title">
            <img class="archive-hero-image" src="<?= htmlspecialchars($heroImgUrl) ?>" alt="" width="2061" height="763" fetchpriority="high">
            <div class="archive-hero-content">
                <p class="archive-eyebrow">My Collection <span></span></p>
                <h1 id="archive-title"<?= $ts('hero_text_color') ? ' style="color:' . htmlspecialchars($ts('hero_text_color')) . '"' : '' ?>><?= htmlspecialchars($heroTitle) ?></h1>
                <p class="archive-hero-description"<?= $ts('hero_tagline_color') ? ' style="color:' . htmlspecialchars($ts('hero_tagline_color')) . '"' : '' ?>><?= htmlspecialchars($heroTagline) ?></p>
                <a class="archive-cta" href="<?= SITE_URL ?>/search.php">Explore the Collection <?= archiveUiIcon('arrow-right') ?></a>
            </div>
        </section>

        <?php if ($showStats || $continueItem): ?>
        <aside class="archive-sidebar" aria-label="Collection overview">
            <?php if ($showStats): ?>
            <div class="archive-stats archive-panel">
                <dl>
                    <div><?= archiveUiIcon('square-3-stack-3d') ?><dt>Items in Collection</dt><dd><?= number_format((int) $collectionStats['total_items']) ?></dd></div>
                    <div><?= archiveUiIcon('photo') ?><dt>Items with Images</dt><dd><?= number_format((int) $collectionStats['with_images']) ?></dd></div>
                    <div><?= archiveUiIcon('tag') ?><dt>Categories</dt><dd><?= number_format(count($homeCategories)) ?></dd></div>
                    <div><?= archiveUiIcon('calendar-days') ?><dt>Latest Collection Year</dt><dd><?= htmlspecialchars($collectionStats['latest_year'] ?? 'N/A') ?></dd></div>
                </dl>
            </div>
            <?php endif; ?>
            <?php if ($continueItem): ?>
            <a class="archive-continue archive-panel" data-archive-continue href="<?= SITE_URL ?>/item/<?= (int) $continueItem['id'] ?>">
                <div class="archive-continue-text">
                    <h2><?= archiveUiIcon('clock') ?> <span data-continue-heading>Start Exploring</span></h2>
                    <p><span data-continue-title><?= htmlspecialchars($continueItem['title']) ?></span><span data-continue-reg><?= htmlspecialchars($continueItem['reg_number']) ?></span></p>
                </div>
                <?php if (!empty($continueItem['file_path'])): ?>
                <img data-continue-image src="<?= htmlspecialchars(MediaProcessor::url($continueItem['file_path'], 'card', 'image', $storage ?? null)) ?>" alt="" width="140" height="95">
                <?php endif; ?>
                <span class="archive-round-arrow"><?= archiveUiIcon('chevron-right') ?></span>
            </a>
            <?php endif; ?>
        </aside>
        <?php endif; ?>
    </div>

    <?php if ($homeCategories): ?>
    <section class="archive-section" id="categories" aria-labelledby="categories-title">
        <div class="archive-section-heading">
            <h2 id="categories-title"><?= archiveUiIcon('squares-2x2') ?> Browse by Category</h2>
            <a class="archive-text-link" href="<?= SITE_URL ?>/search.php">View All Categories <?= archiveUiIcon('arrow-right') ?></a>
        </div>
        <div class="archive-categories" style="--category-count:<?= min(5, count($homeCategories)) ?>">
            <?php foreach ($homeCategories as $cat):
                $categoryImage = !empty($cat['image_path'])
                    ? SITE_URL . '/uploads/categories/' . rawurlencode($cat['image_path'])
                    : (!empty($cat['sample_image']) ? MediaProcessor::url($cat['sample_image'], 'card', 'image', $storage ?? null) : '');
            ?>
            <a class="archive-category" href="<?= SITE_URL ?>/search.php?category_ids[]=<?= (int) $cat['id'] ?>">
                <?php if ($categoryImage): ?><img src="<?= htmlspecialchars($categoryImage) ?>" alt="" width="360" height="180" loading="lazy"><?php endif; ?>
                <div class="archive-category-label"><?= archiveUiIcon('square-3-stack-3d') ?><h3><?= htmlspecialchars($cat['name']) ?></h3><span><?= number_format((int) $cat['item_count']) ?> item<?= (int) $cat['item_count'] === 1 ? '' : 's' ?></span></div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="archive-section" aria-labelledby="recent-title">
        <div class="archive-section-heading">
            <h2 id="recent-title"><?= archiveUiIcon('clock') ?> Recently Added Items</h2>
            <a class="archive-text-link" href="<?= SITE_URL ?>/search.php">View All <?= archiveUiIcon('arrow-right') ?></a>
        </div>
        <?php if ($featuredItems): ?>
        <div class="archive-recent-grid" style="--recent-columns:<?= $gridCols ?>">
            <?php foreach ($featuredItems as $featured): ?>
            <a href="<?= SITE_URL ?>/item/<?= (int) $featured['id'] ?>" class="archive-item-card archive-panel">
                <div class="archive-card-image">
                    <?php if (!empty($featured['file_path'])): ?>
                    <img src="<?= htmlspecialchars(MediaProcessor::url($featured['file_path'], 'card', 'image', $storage ?? null)) ?>" alt="<?= htmlspecialchars($featured['title']) ?>" width="180" height="150" loading="lazy">
                    <?php else: ?><?= archiveUiIcon('photo') ?><?php endif; ?>
                    <?php if (class_exists('HookRegistry')) HookRegistry::doAction('item_card_badge', $featured); ?>
                </div>
                <div class="archive-card-content">
                    <span class="archive-reg"><?= htmlspecialchars($featured['reg_number']) ?></span>
                    <h3><?= htmlspecialchars($featured['title']) ?></h3>
                    <?php if (!empty($featured['production_date'])): ?><p class="archive-card-date"><?= archiveUiIcon('calendar-days') ?> <?= htmlspecialchars(archiveUiDate($featured['production_date'])) ?></p><?php endif; ?>
                </div>
                <span class="archive-round-arrow"><?= archiveUiIcon('chevron-right') ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="archive-empty archive-panel"><?= archiveUiIcon('book-open') ?><h3>No items available yet</h3><p>New additions to the collection will appear here.</p></div>
        <?php endif; ?>
    </section>
    <?php if (class_exists('HookRegistry')) HookRegistry::doAction('home_page_sections'); ?>
</main>
<?php require_once ThemeManager::getFooter(); ?>
