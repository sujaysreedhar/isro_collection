<?php
// themes/custom/index.php
// Home page template — uses CSS custom properties and Theme Studio settings for layout

global $appSettings;

$pageTitle        = SITE_TITLE;
$currentMenu      = 'home';

// Theme Studio layout settings (safe defaults so page works even without the module active)
$ts = static fn(string $k, string $d = '') => $GLOBALS['appSettings']['theme_studio_' . $k] ?? $d;

$heroStyle       = $ts('hero_style',         'split');
$gridCols        = (int) $ts('grid_cols',      '3');
$showSearch      = $ts('show_search',          '1') === '1';
$showStats       = $ts('show_stats',           '0') === '1';
$heroTagline     = $ts('hero_tagline',         '');
$heroTitle       = $ts('hero_title',           '');
$heroTextColor   = $ts('hero_text_color',      '');
$heroTaglineColor= $ts('hero_tagline_color',   '');
$heroAccentColor = $ts('hero_accent_color',    '');
$heroOverlayColor   = $ts('hero_overlay_color', '#ffffff');
$heroOverlayOpacity = max(0, min(100, (int) $ts('hero_overlay_opacity', '75')));
$featuredCount   = (int) $ts('featured_count', '6');
$heroImage       = $ts('hero_image', '');

$heroImgUrl = $heroImage && file_exists(dirname(__DIR__, 2) . '/uploads/branding/' . $heroImage)
    ? SITE_URL . '/uploads/branding/' . rawurlencode($heroImage)
    : '';

// Build overlay rgba from hex + opacity
$overlayHex = ltrim($heroOverlayColor, '#');
$or = hexdec(substr($overlayHex, 0, 2)); $og = hexdec(substr($overlayHex, 2, 2)); $ob = hexdec(substr($overlayHex, 4, 2));
$overlayRgba = "rgba({$or},{$og},{$ob}," . round($heroOverlayOpacity / 100, 2) . ')';

$gridClass = match($gridCols) {
    2 => 'grid-cols-1 sm:grid-cols-2',
    4 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
    default => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
};

ob_start();
// (no additionalHead needed — Theme Studio injects via frontend_head hook)
$additionalHead = ob_get_clean();

// Fetch Categories for discovery section
global $pdo;
$stmtCats = $pdo->query("SELECT id, name, image_path FROM categories WHERE image_path IS NOT NULL AND image_path != '' ORDER BY id ASC LIMIT 8");
$homeCategories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

require_once ThemeManager::getHeader();
?>

<?php /* ═══════════ HERO SECTION ═══════════ */ ?>
<?php if ($heroStyle === 'split'): ?>
    <!-- SPLIT: text left, optional image right -->
    <div class="hero-pattern border-b tc-border overflow-hidden">
        <div class="max-w-7xl mx-auto flex flex-col lg:flex-row items-stretch min-h-[420px]">
            <!-- Text side -->
            <div class="flex-1 flex flex-col justify-center px-6 lg:px-12 py-16 lg:py-24">
                <?php 
                $h1Style = $heroTextColor ? "color:".htmlspecialchars($heroTextColor) : "";
                $acStyle = $heroAccentColor ? "style=\"color:".htmlspecialchars($heroAccentColor)."\"" : "";
                ?>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold serif tracking-tight leading-tight" style="<?= $h1Style ?>">
                    <?= htmlspecialchars($heroTitle ?: 'Discover history in the') ?><br>
                    <span class="tc-accent-text" <?= $acStyle ?>><?= htmlspecialchars(SITE_TITLE) ?></span>
                </h1>
                <?php if ($heroTagline): ?>
                    <p class="mt-4 text-lg max-w-lg"
                       style="color:<?= $heroTaglineColor ? htmlspecialchars($heroTaglineColor) : 'var(--color-text-muted,#6b7280)' ?>">
                        <?= htmlspecialchars($heroTagline) ?>
                    </p>
                <?php else: ?>
                    <p class="mt-4 text-lg max-w-lg"
                       style="color:<?= $heroTaglineColor ? htmlspecialchars($heroTaglineColor) : 'var(--color-text-muted,#6b7280)' ?>">
                        Explore pictorial cancellations, narratives, and media from across India.
                    </p>
                <?php endif; ?>
                <?php if ($showSearch): ?>
                <div class="mt-8 max-w-lg">
                    <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative rounded-lg shadow-sm flex overflow-hidden" style="border:1px solid var(--color-border,#e5e7eb);">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input type="text" name="q" data-autocomplete="true" autocomplete="off"
                               class="flex-grow pl-12 pr-4 py-4 text-base bg-white tc-input border-0"
                               placeholder="Search items, dates, or stories...">
                        <button type="submit" class="px-6 py-4 font-semibold text-base tc-search-btn">Search</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <!-- Image side -->
            <?php if ($heroImgUrl): ?>
            <div class="hidden lg:block flex-1 min-h-[380px] relative overflow-hidden">
                <img src="<?= htmlspecialchars($heroImgUrl) ?>" alt="Hero"
                     class="absolute inset-0 w-full h-full object-cover">
                <div class="absolute inset-0" style="background: linear-gradient(to right, <?= $overlayRgba ?> 0%, transparent 40%);"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($heroStyle === 'centered'): ?>
    <!-- CENTERED: full-width centred hero, optional background image -->
    <div class="hero-pattern border-b tc-border relative overflow-hidden"
         <?php if ($heroImgUrl): ?>
         style="background-image: url('<?= htmlspecialchars($heroImgUrl) ?>'); background-size:cover; background-position:center center;"
         <?php endif; ?>>
        <?php if ($heroImgUrl): ?>
        <div class="absolute inset-0" style="background: <?= $overlayRgba ?>;"></div>
        <?php endif; ?>
        <div class="relative z-10 max-w-3xl mx-auto text-center px-6 py-24">
            <h1 class="text-5xl sm:text-6xl font-extrabold serif tracking-tight"
                style="color:<?= $heroTextColor ? htmlspecialchars($heroTextColor) : 'var(--color-primary,#111827)' ?>">
                <?= htmlspecialchars($heroTitle ?: SITE_TITLE) ?>
            </h1>
            <p class="mt-4 text-xl"
               style="color:<?= $heroTaglineColor ? htmlspecialchars($heroTaglineColor) : 'var(--color-text-muted,#6b7280)' ?>">
                <?= htmlspecialchars($heroTagline ?: 'Explore pictorial cancellations from across India.') ?>
            </p>
            <?php if ($showSearch): ?>
            <div class="mt-10 max-w-xl mx-auto">
                <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative rounded-xl shadow-lg flex overflow-hidden" style="border:1px solid var(--color-border,#e5e7eb);">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input type="text" name="q" data-autocomplete="true" autocomplete="off"
                           class="flex-grow pl-12 pr-4 py-4 text-base bg-white tc-input border-0"
                           placeholder="Search items, dates, or stories...">
                    <button type="submit" class="px-6 py-4 font-semibold text-base tc-search-btn">Search</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: /* minimal */ ?>
    <!-- MINIMAL: just a compact search bar banner -->
    <div class="border-b tc-border" style="background:var(--color-hero-bg,#fff);">
        <div class="max-w-3xl mx-auto px-4 py-10 text-center">
            <h1 class="text-3xl font-bold serif mb-6"
                style="color:<?= $heroTextColor ? htmlspecialchars($heroTextColor) : 'var(--color-primary,#111827)' ?>">
                <?= htmlspecialchars(SITE_TITLE) ?>
            </h1>
            <?php if ($showSearch): ?>
            <form action="<?= SITE_URL ?>/search.php" method="GET" class="relative rounded-lg flex overflow-hidden shadow-sm" style="border:1px solid var(--color-border,#e5e7eb);">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text" name="q" data-autocomplete="true" autocomplete="off"
                       class="flex-grow pl-10 pr-4 py-3 text-base bg-white tc-input border-0"
                       placeholder="Search the collection...">
                <button type="submit" class="px-5 py-3 font-semibold tc-search-btn">Search</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php /* ═══════════ STATS BAR ═══════════ */ ?>
<?php if ($showStats): ?>
    <?php
    global $pdo;
    $totalItems    = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
    $totalWithImgs = $pdo->query("SELECT COUNT(DISTINCT item_id) FROM media")->fetchColumn();
    ?>
    <div class="border-b tc-border" style="background:var(--color-surface,#fff);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap gap-6 justify-center sm:justify-start text-sm font-medium tc-text">
            <span>📦 <strong class="tc-primary-text"><?= number_format((int)$totalItems) ?></strong> items in collection</span>
            <span>🖼 <strong class="tc-primary-text"><?= number_format((int)$totalWithImgs) ?></strong> with images</span>
        </div>
    </div>
<?php endif; ?>

<?php /* ═══════════ FEATURED ITEMS ═══════════ */ ?>
<main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-16">
    <div class="flex items-center justify-between mb-8">
        <h2 class="text-3xl font-bold serif tc-primary-text">Recently Added Items</h2>
        <a href="<?= SITE_URL ?>/search.php" class="text-sm font-semibold tc-accent-text hover:opacity-80 flex items-center gap-1">
            View All
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>

    <?php if (count($featuredItems) > 0): ?>
        <div class="grid <?= $gridClass ?> gap-6 lg:gap-8">
            <?php foreach ($featuredItems as $item): ?>
                <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>"
                   class="tc-card tc-radius group flex flex-col h-full overflow-hidden">
                    <div class="relative h-56 overflow-hidden" style="background:var(--color-border,#e5e7eb);">
                        <?php if (!empty($item['file_path'])): ?>
                            <?php $imgUrl = isset($storage)
                                ? $storage->url('display/' . $item['file_path'])
                                : SITE_URL . '/uploads/display/' . $item['file_path']; ?>
                            <img src="<?= htmlspecialchars($imgUrl) ?>"
                                 alt="<?= htmlspecialchars($item['title']) ?>"
                                 class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-500">
                        <?php else: ?>
                            <div class="flex items-center justify-center h-full tc-text-muted">
                                <svg class="w-12 h-12 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        <?php endif; ?>
                        <div class="absolute bottom-0 left-0 px-3 py-1 text-xs font-bold tc-text-muted tracking-wider"
                             style="background:color-mix(in srgb,var(--color-surface,#fff) 90%,transparent);">
                            <?= htmlspecialchars($item['reg_number']) ?>
                        </div>
                        
                        <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('item_card_badge', $item); } ?>
                    </div>
                    <div class="p-5 flex flex-col flex-grow">
                        <h3 class="text-lg font-bold serif tc-primary-text mb-2 group-hover:tc-accent-text transition-colors line-clamp-2">
                            <?= htmlspecialchars($item['title']) ?>
                        </h3>
                        <p class="text-sm tc-text-muted line-clamp-3 mb-3 flex-grow">
                            <?= htmlspecialchars(strip_tags($item['physical_description'] ?? 'No description available.')) ?>
                        </p>
                        <?php $cardTags = $featuredTags[$item['id']] ?? []; ?>
                        <?php if ($cardTags): ?>
                        <div class="flex flex-wrap gap-1 mt-auto">
                            <?php foreach (array_slice($cardTags, 0, 4) as $ct): ?>
                                <span class="inline-flex items-center px-2 py-0.5 text-xs tc-text-muted tc-radius"
                                      style="background:color-mix(in srgb,var(--color-accent,#2563eb) 10%,transparent);">
                                    <span class="mr-0.5 tc-accent-text">#</span><?= htmlspecialchars($ct['name']) ?>
                                </span>
                            <?php endforeach; ?>
                            <?php if (count($cardTags) > 4): ?>
                                <span class="text-xs tc-text-muted">+<?= count($cardTags) - 4 ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-20 tc-surface border tc-border tc-radius">
            <svg class="mx-auto h-12 w-12 tc-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium tc-primary-text">No items available</h3>
            <p class="mt-1 text-sm tc-text-muted">Get started by importing data into your MySQL database.</p>
        </div>
    <?php endif; ?>

    <?php /* ═══════════ BROWSE BY CATEGORY ═══════════ */ ?>
    <?php if ($homeCategories): ?>
    <div class="mt-20">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-3xl font-bold serif tc-primary-text">Browse by Category</h2>
            <div class="h-px flex-1 bg-tc-border ml-8 opacity-30"></div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-6">
            <?php foreach ($homeCategories as $cat): ?>
            <a href="<?= SITE_URL ?>/search.php?category_ids[]=<?= (int)$cat['id'] ?>" class="group block">
                <div class="relative aspect-video rounded-2xl overflow-hidden tc-border bg-tc-accent-bg/5 hover:shadow-xl transition-all duration-300">
                    <img src="<?= SITE_URL ?>/uploads/categories/<?= htmlspecialchars($cat['image_path']) ?>" 
                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" alt="<?= htmlspecialchars($cat['name']) ?>">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                    <div class="absolute inset-0 p-6 flex flex-col justify-end">
                        <h4 class="text-white font-bold text-lg serif group-hover:tc-accent-text transition-colors"><?= htmlspecialchars($cat['name']) ?></h4>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modular Sections -->
    <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('home_page_sections'); } ?>
</main>

<?php require_once ThemeManager::getFooter(); ?>
