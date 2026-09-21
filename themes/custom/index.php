<?php
// themes/custom/index.php
// Home page template — uses CSS custom properties and Theme Studio settings for layout

global $appSettings;

$pageTitle = SITE_TITLE;
$currentMenu = 'home';

// Theme Studio layout settings (safe defaults so page works even without the module active)
$ts = static fn(string $k, string $d = '') => $GLOBALS['appSettings']['theme_studio_' . $k] ?? $d;

$heroStyle = $ts('hero_style', 'split');
$gridCols = (int) $ts('grid_cols', '3');
$showSearch = $ts('show_search', '1') === '1';
$showStats = $ts('show_stats', '0') === '1';
$heroTagline = $ts('hero_tagline', '');
$heroTitle = $ts('hero_title', '');
$heroTextColor = $ts('hero_text_color', '');
$heroTaglineColor = $ts('hero_tagline_color', '');
$heroAccentColor = $ts('hero_accent_color', '');
$heroOverlayColor = $ts('hero_overlay_color', '#ffffff');
$heroOverlayOpacity = max(0, min(100, (int) $ts('hero_overlay_opacity', '75')));
$featuredCount = (int) $ts('featured_count', '6');
$heroImage = $ts('hero_image', '');

$heroImgUrl = $heroImage && file_exists(dirname(__DIR__, 2) . '/uploads/branding/' . $heroImage)
    ? SITE_URL . '/uploads/branding/' . rawurlencode($heroImage)
    : '';

// Build overlay rgba from hex + opacity
$overlayHex = ltrim($heroOverlayColor, '#');
$or = hexdec(substr($overlayHex, 0, 2));
$og = hexdec(substr($overlayHex, 2, 2));
$ob = hexdec(substr($overlayHex, 4, 2));
$overlayRgba = "rgba({$or},{$og},{$ob}," . round($heroOverlayOpacity / 100, 2) . ')';

$gridClass = match ($gridCols) {
    2 => 'grid-cols-1 sm:grid-cols-2',
    4 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
    default => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
};

ob_start();
// (no additionalHead needed — Theme Studio injects via frontend_head hook)
$additionalHead = ob_get_clean();

// Fetch categories (with item counts) for the discovery section
global $pdo;
$stmtCats = $pdo->query("
    SELECT c.id, c.name, c.image_path, COUNT(ic.item_id) AS item_count
    FROM categories c
    LEFT JOIN item_category ic ON ic.category_id = c.id
    WHERE c.image_path IS NOT NULL AND c.image_path != ''
    GROUP BY c.id, c.name, c.image_path
    ORDER BY c.id ASC LIMIT 8
");
$homeCategories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

// Collection stats (used by hero strip when enabled)
$totalItems = $totalWithImgs = $totalCategories = 0;
if ($showStats) {
    $totalItems = (int) $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
    $totalWithImgs = (int) $pdo->query("SELECT COUNT(DISTINCT item_id) FROM media")->fetchColumn();
    $totalCategories = (int) $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
}

// Detect dark hero background so text defaults stay readable on any palette.
// For a centered hero with a background image, the overlay colour dominates instead.
$heroBgSetting = ($heroStyle === 'centered' && $heroImgUrl && $heroOverlayOpacity >= 40)
    ? $heroOverlayColor
    : $ts('color_hero_bg', '#ffffff');
$heroBgHex = ltrim($heroBgSetting, '#');
if (strlen($heroBgHex) === 3) {
    $heroBgHex = $heroBgHex[0] . $heroBgHex[0] . $heroBgHex[1] . $heroBgHex[1] . $heroBgHex[2] . $heroBgHex[2];
}
$heroIsDark = (0.299 * hexdec(substr($heroBgHex, 0, 2))
    + 0.587 * hexdec(substr($heroBgHex, 2, 2))
    + 0.114 * hexdec(substr($heroBgHex, 4, 2))) < 128;
$heroDarkClass = $heroIsDark ? ' tc-on-dark' : '';

$heroTitleStyle = 'color:' . ($heroTextColor
    ? htmlspecialchars($heroTextColor)
    : ($heroIsDark ? '#f9fafb' : 'var(--color-primary,#111827)'));
$heroAccentStyle = $heroAccentColor ? 'color:' . htmlspecialchars($heroAccentColor) : '';
$heroTaglineStyle = 'color:' . ($heroTaglineColor
    ? htmlspecialchars($heroTaglineColor)
    : ($heroIsDark ? 'rgba(249,250,251,.75)' : 'var(--color-text-muted,#6b7280)'));

require_once ThemeManager::getHeader();

/* Reusable pill search form */
$renderSearchForm = static function (string $placeholder = 'Search items, dates, or stories...') {
    ?>
    <form action="<?= SITE_URL ?>/search.php" method="GET" class="tc-search-pill relative flex items-center">
        <svg class="h-5 w-5 tc-text-muted absolute left-5 pointer-events-none" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input type="text" name="q" data-autocomplete="true" autocomplete="off"
            class="flex-grow pl-13 pr-3 py-4 text-base bg-transparent border-0 focus:outline-none tc-text"
            placeholder="<?= htmlspecialchars($placeholder) ?>">
        <button type="submit" class="tc-search-pill-btn m-1.5 px-6 py-2.5 font-semibold text-sm">Search</button>
    </form>
    <?php
};
?>

<?php /* ═══════════ HERO SECTION ═══════════ */ ?>
<?php if ($heroStyle === 'split'): ?>
    <!-- SPLIT: text left, optional image right -->
    <div class="tc-hero-modern<?= $heroDarkClass ?> border-b tc-border overflow-hidden relative">
        <div class="tc-hero-glow" aria-hidden="true"></div>
        <div class="max-w-7xl mx-auto flex flex-col lg:flex-row items-stretch min-h-[480px] relative">
            <!-- Text side -->
            <div class="flex-1 flex flex-col justify-center px-6 lg:px-12 py-16 lg:py-24 reveal">
                <span class="tc-eyebrow mb-5">
                    <span class="tc-eyebrow-dot" aria-hidden="true"></span>
                    Curated Archive
                </span>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold serif tracking-tight leading-[1.08]"
                    style="<?= $heroTitleStyle ?>">
                    <?= htmlspecialchars($heroTitle ?: 'Discover history in the') ?><br>
                    <span class="tc-accent-text tc-hero-underline" style="<?= $heroAccentStyle ?>"><?= htmlspecialchars(SITE_TITLE) ?></span>
                </h1>
                <p class="mt-6 text-lg leading-relaxed max-w-lg" style="<?= $heroTaglineStyle ?>">
                    <?= htmlspecialchars($heroTagline ?: 'Explore pictorial cancellations, narratives, and media from across India.') ?>
                </p>

                <?php if ($showSearch): ?>
                    <div class="mt-9 max-w-lg">
                        <?php $renderSearchForm(); ?>
                    </div>
                <?php endif; ?>

                <?php if ($homeCategories): ?>
                    <div class="mt-6 flex flex-wrap items-center gap-2 max-w-lg">
                        <span class="text-xs font-semibold uppercase tracking-wider tc-text-muted mr-1">Popular:</span>
                        <?php foreach (array_slice($homeCategories, 0, 4) as $qc): ?>
                            <a href="<?= SITE_URL ?>/search.php?category_ids[]=<?= (int) $qc['id'] ?>" class="tc-chip">
                                <?= htmlspecialchars($qc['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($showStats): ?>
                    <div class="mt-10 flex items-center gap-8 flex-wrap">
                        <div>
                            <div class="text-3xl font-extrabold serif tc-primary-text">
                                <?= number_format($totalItems) ?></div>
                            <div class="text-xs font-semibold uppercase tracking-wider tc-text-muted mt-1">Items archived</div>
                        </div>
                        <div class="tc-stat-divider" aria-hidden="true"></div>
                        <div>
                            <div class="text-3xl font-extrabold serif tc-primary-text">
                                <?= number_format($totalWithImgs) ?></div>
                            <div class="text-xs font-semibold uppercase tracking-wider tc-text-muted mt-1">With images</div>
                        </div>
                        <div class="tc-stat-divider" aria-hidden="true"></div>
                        <div>
                            <div class="text-3xl font-extrabold serif tc-primary-text">
                                <?= number_format($totalCategories) ?></div>
                            <div class="text-xs font-semibold uppercase tracking-wider tc-text-muted mt-1">Categories</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Image side -->
            <?php if ($heroImgUrl): ?>
                <div class="hidden lg:flex flex-1 items-center justify-center relative py-16 pr-12 reveal reveal-delay-1">
                    <div class="tc-hero-frame relative w-full max-w-xl">
                        <img src="<?= htmlspecialchars($heroImgUrl) ?>" alt="Hero"
                            class="w-full h-[420px] object-cover tc-hero-img">
                        <div class="absolute inset-0 tc-hero-img pointer-events-none"
                            style="background: linear-gradient(to top right, <?= $overlayRgba ?> 0%, transparent 45%);"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($heroStyle === 'centered'): ?>
    <!-- CENTERED: full-width centred hero, optional background image -->
    <div class="tc-hero-modern<?= $heroDarkClass ?> border-b tc-border relative overflow-hidden" <?php if ($heroImgUrl): ?>
            style="background-image: url('<?= htmlspecialchars($heroImgUrl) ?>'); background-size:cover; background-position:center center;"
        <?php endif; ?>>
        <?php if ($heroImgUrl): ?>
            <div class="absolute inset-0" style="background: <?= $overlayRgba ?>;"></div>
        <?php else: ?>
            <div class="tc-hero-glow" aria-hidden="true"></div>
        <?php endif; ?>
        <div class="relative z-10 max-w-3xl mx-auto text-center px-6 py-24 lg:py-28 reveal">
            <span class="tc-eyebrow mb-6 mx-auto">
                <span class="tc-eyebrow-dot" aria-hidden="true"></span>
                Curated Archive
            </span>
            <h1 class="text-5xl sm:text-6xl font-extrabold serif tracking-tight leading-[1.08]"
                style="<?= $heroTitleStyle ?>">
                <?= htmlspecialchars($heroTitle ?: SITE_TITLE) ?>
            </h1>
            <p class="mt-6 text-xl leading-relaxed" style="<?= $heroTaglineStyle ?>">
                <?= htmlspecialchars($heroTagline ?: 'Explore pictorial cancellations from across India.') ?>
            </p>
            <?php if ($showSearch): ?>
                <div class="mt-10 max-w-xl mx-auto">
                    <?php $renderSearchForm(); ?>
                </div>
            <?php endif; ?>
            <?php if ($homeCategories): ?>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                    <?php foreach (array_slice($homeCategories, 0, 4) as $qc): ?>
                        <a href="<?= SITE_URL ?>/search.php?category_ids[]=<?= (int) $qc['id'] ?>" class="tc-chip">
                            <?= htmlspecialchars($qc['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($showStats): ?>
                <div class="mt-12 flex items-center justify-center gap-8 flex-wrap">
                    <div>
                        <div class="text-3xl font-extrabold serif tc-primary-text"><?= number_format($totalItems) ?></div>
                        <div class="text-xs font-semibold uppercase tracking-wider tc-text-muted mt-1">Items archived</div>
                    </div>
                    <div class="tc-stat-divider" aria-hidden="true"></div>
                    <div>
                        <div class="text-3xl font-extrabold serif tc-primary-text"><?= number_format($totalWithImgs) ?></div>
                        <div class="text-xs font-semibold uppercase tracking-wider tc-text-muted mt-1">With images</div>
                    </div>
                    <div class="tc-stat-divider" aria-hidden="true"></div>
                    <div>
                        <div class="text-3xl font-extrabold serif tc-primary-text"><?= number_format($totalCategories) ?></div>
                        <div class="text-xs font-semibold uppercase tracking-wider tc-text-muted mt-1">Categories</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: /* minimal */ ?>
    <!-- MINIMAL: just a compact search bar banner -->
    <div class="border-b tc-border tc-hero-modern<?= $heroDarkClass ?> relative overflow-hidden">
        <div class="max-w-3xl mx-auto px-4 py-12 text-center relative z-10 reveal">
            <h1 class="text-3xl font-bold serif mb-6"
                style="<?= $heroTitleStyle ?>">
                <?= htmlspecialchars($heroTitle ?: SITE_TITLE) ?>
            </h1>
            <?php if ($showSearch): ?>
                <?php $renderSearchForm('Search the collection...'); ?>
            <?php endif; ?>
            <?php if ($showStats): ?>
                <p class="mt-5 text-sm tc-text-muted">
                    <strong class="tc-primary-text"><?= number_format($totalItems) ?></strong> items ·
                    <strong class="tc-primary-text"><?= number_format($totalWithImgs) ?></strong> with images ·
                    <strong class="tc-primary-text"><?= number_format($totalCategories) ?></strong> categories
                </p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php /* ═══════════ FEATURED ITEMS ═══════════ */ ?>
<main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
    <div class="flex items-end justify-between gap-6 mb-10 reveal">
        <div>
            <span class="tc-eyebrow mb-3">
                <span class="tc-eyebrow-dot" aria-hidden="true"></span>
                Latest additions
            </span>
            <h2 class="text-3xl lg:text-4xl font-bold serif tc-primary-text">Recently Added Items</h2>
        </div>
        <a href="<?= SITE_URL ?>/search.php" class="tc-btn-outline hidden sm:inline-flex">
            View all
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </a>
    </div>

    <?php if (count($featuredItems) > 0): ?>
        <div class="grid <?= $gridClass ?> gap-6 lg:gap-8">
            <?php foreach ($featuredItems as $i => $item): ?>
                <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>"
                    class="tc-card-modern group flex flex-col h-full overflow-hidden reveal reveal-delay-<?= $i % 3 ?>">
                    <div class="relative h-60 overflow-hidden" style="background:var(--color-border,#e5e7eb);">
                        <?php if (!empty($item['file_path'])): ?>
                            <?php $imgUrl = MediaProcessor::url($item['file_path'], 'card', 'image', $storage ?? null); ?>
                            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($item['title']) ?>"
                                class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-700 ease-out"
                                loading="lazy">
                        <?php else: ?>
                            <div class="flex items-center justify-center h-full tc-text-muted">
                                <svg class="w-12 h-12 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        <?php endif; ?>

                        <div class="absolute inset-0 tc-card-scrim opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                            aria-hidden="true"></div>

                        <span class="absolute top-3 left-3 tc-reg-badge"><?= htmlspecialchars($item['reg_number']) ?></span>

                        <span class="absolute bottom-3 right-3 tc-view-pill opacity-0 translate-y-1 group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300">
                            View item
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </span>

                        <?php if (class_exists('HookRegistry')) {
                            HookRegistry::doAction('item_card_badge', $item);
                        } ?>
                    </div>
                    <div class="p-5 flex flex-col flex-grow">
                        <h3
                            class="text-lg font-bold serif tc-primary-text mb-2 group-hover:tc-accent-text transition-colors line-clamp-2">
                            <?= htmlspecialchars($item['title']) ?>
                        </h3>
                        <p class="text-sm leading-relaxed tc-text-muted line-clamp-3 mb-4 flex-grow">
                            <?= htmlspecialchars(strip_tags($item['physical_description'] ?? 'No description available.')) ?>
                        </p>
                        <?php $cardTags = $featuredTags[$item['id']] ?? []; ?>
                        <?php if ($cardTags): ?>
                            <div class="flex flex-wrap gap-1.5 mt-auto pt-3 border-t tc-border">
                                <?php foreach (array_slice($cardTags, 0, 4) as $ct): ?>
                                    <span class="tc-tag">
                                        <span class="tc-accent-text">#</span><?= htmlspecialchars($ct['name']) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($cardTags) > 4): ?>
                                    <span class="text-xs tc-text-muted self-center">+<?= count($cardTags) - 4 ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="mt-8 text-center sm:hidden">
            <a href="<?= SITE_URL ?>/search.php" class="tc-btn-outline inline-flex">
                View all items
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </div>
    <?php else: ?>
        <div class="text-center py-20 tc-surface border tc-border tc-radius">
            <svg class="mx-auto h-12 w-12 tc-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            <h3 class="mt-2 text-sm font-medium tc-primary-text">No items available</h3>
            <p class="mt-1 text-sm tc-text-muted">Get started by importing data into your MySQL database.</p>
        </div>
    <?php endif; ?>

    <?php /* ═══════════ BROWSE BY CATEGORY ═══════════ */ ?>
    <?php if ($homeCategories): ?>
        <div class="mt-20 lg:mt-24">
            <div class="flex items-end justify-between gap-6 mb-10 reveal">
                <div>
                    <span class="tc-eyebrow mb-3">
                        <span class="tc-eyebrow-dot" aria-hidden="true"></span>
                        Discover
                    </span>
                    <h2 class="text-3xl lg:text-4xl font-bold serif tc-primary-text">Browse by Category</h2>
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 lg:gap-6">
                <?php foreach ($homeCategories as $i => $cat): ?>
                    <a href="<?= SITE_URL ?>/search.php?category_ids[]=<?= (int) $cat['id'] ?>"
                        class="group block reveal reveal-delay-<?= $i % 4 ?>">
                        <div class="tc-cat-card relative aspect-video overflow-hidden">
                            <img src="<?= SITE_URL ?>/uploads/categories/<?= htmlspecialchars($cat['image_path']) ?>"
                                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 ease-out"
                                alt="<?= htmlspecialchars($cat['name']) ?>" loading="lazy">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-transparent"></div>
                            <div class="absolute inset-0 p-4 lg:p-5 flex flex-col justify-end">
                                <h4 class="font-bold text-base lg:text-lg serif leading-snug" style="color:#ffffff !important;">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </h4>
                                <?php if ((int) $cat['item_count'] > 0): ?>
                                    <span class="text-xs font-medium mt-0.5" style="color:rgba(255,255,255,.75);">
                                        <?= number_format((int) $cat['item_count']) ?>
                                        item<?= (int) $cat['item_count'] === 1 ? '' : 's' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="tc-cat-arrow absolute top-3 right-3 opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-300"
                                aria-hidden="true">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php /* ═══════════ EXPLORE CTA ═══════════ */ ?>
    <div class="mt-20 lg:mt-24 reveal">
        <div class="tc-cta-band relative overflow-hidden px-6 py-14 lg:px-16 lg:py-16 text-center">
            <div class="relative z-10 max-w-2xl mx-auto">
                <h2 class="text-3xl lg:text-4xl font-bold serif" style="color:#ffffff;">Explore the full archive</h2>
                <p class="mt-3 text-base lg:text-lg" style="color:rgba(255,255,255,.85);">
                    Search across the entire collection — filter by category, tag, or keyword to uncover every story.
                </p>
                <a href="<?= SITE_URL ?>/search.php" class="tc-btn-light mt-8 inline-flex">
                    Start exploring
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Modular Sections -->
    <?php if (class_exists('HookRegistry')) {
        HookRegistry::doAction('home_page_sections');
    } ?>
</main>

<script>
    // Scroll-reveal animations (respects prefers-reduced-motion)
    (function () {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.querySelectorAll('.reveal').forEach(el => el.classList.add('is-visible'));
            return;
        }
        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('is-visible');
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
        document.querySelectorAll('.reveal').forEach(el => io.observe(el));
    })();
</script>

<?php require_once ThemeManager::getFooter(); ?>
