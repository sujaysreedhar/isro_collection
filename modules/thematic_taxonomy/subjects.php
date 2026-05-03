<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/ThemeManager.php';

global $activeModulesSlugs;

$pageTitle = 'Subjects - ' . SITE_TITLE;
$ogTitle = $pageTitle;
$ogDescription = 'Browse the collection by curated subjects such as Space, Postal History, Wildlife, and numismatic eras.';
$ogUrl = SITE_URL . '/subjects';
$currentMenu = 'subjects';

if (!in_array('thematic_taxonomy', $activeModulesSlugs, true)) {
    http_response_code(404);
    require_once ThemeManager::getTemplatePath('404.php');
    return;
}

$themeTree = $this->getThemeTree(true);
$themeCounts = $this->getThemeAggregateCounts(true);
$allThemes = $this->getThemeOptions(true);
$topLevelCount = count($themeTree);

ob_start();
require __DIR__ . '/views/frontend_head.php';
$additionalHead = ob_get_clean();

require_once ThemeManager::getHeader();
?>

<main class="subject-atlas flex-grow">
    <section class="subject-atlas-hero subject-atlas-bleed">
        <div class="subject-atlas-shell">
            <div class="subject-atlas-hero-grid">
                <div>
                    <span class="subject-atlas-kicker">Collector Atlas</span>
                    <h1 class="subject-atlas-display mt-6">
                        Map the archive by <span style="color:var(--atlas-accent);">subject</span>, not just format.
                    </h1>
                    <p class="subject-atlas-lead">
                        This index lets the collection read like a curated cabinet of ideas: postal history, exhibitions,
                        wildlife, republic issues, commemorative narratives, numismatic eras, and every cross-cutting story
                        that binds stamps, covers, FDCs, coins, and related material together.
                    </p>
                </div>

                <aside class="subject-atlas-meta">
                    <div class="subject-atlas-meta-block">
                        <span class="subject-atlas-meta-label">Top-Level Cabinets</span>
                        <span class="subject-atlas-meta-value"><?= $topLevelCount ?></span>
                    </div>
                    <div class="subject-atlas-meta-block">
                        <span class="subject-atlas-meta-label">Published Subject Paths</span>
                        <span class="subject-atlas-meta-value"><?= count($allThemes) ?></span>
                    </div>
                    <div class="subject-atlas-meta-block">
                        <span class="subject-atlas-meta-label">Archive Lens</span>
                        <p class="mt-3 text-sm leading-7" style="color:var(--atlas-muted);">
                            Move across media types without losing the historical thread.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <div class="subject-atlas-shell pb-20">
        <?php if (!$themeTree): ?>
            <section class="subject-atlas-section">
                <div class="subject-atlas-empty">
                    <svg class="mx-auto h-12 w-12 tc-text-muted opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                    <h2 class="subject-atlas-title mt-5">No public subjects yet</h2>
                    <p class="subject-atlas-copy mx-auto mt-3">
                        Create and publish subjects from the admin area to turn the archive into a browsable thematic atlas.
                    </p>
                </div>
            </section>
        <?php else: ?>
            <section class="subject-atlas-section">
                <div class="subject-atlas-section-head">
                    <span class="subject-atlas-kicker">Primary Routes</span>
                    <h2 class="subject-atlas-title">Start with the broad cabinets.</h2>
                    <p class="subject-atlas-copy">
                        Each subject opens a collector-facing route through related material. Start wide, then narrow into
                        exhibitions, regions, postmarks, issue programs, or historical series.
                    </p>
                </div>

                <div class="subject-atlas-stack">
                    <?php foreach ($themeTree as $index => $rootTheme): ?>
                        <?php
                        $rootCount = (int)($themeCounts[(int)$rootTheme['id']] ?? 0);
                        $children = $rootTheme['children'] ?? [];
                        ?>
                        <a href="<?= SITE_URL ?>/subject/<?= urlencode($rootTheme['slug']) ?>" class="subject-atlas-band group">
                            <div class="subject-atlas-order"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></div>
                            <div>
                                <div class="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <div class="subject-atlas-kicker" style="margin-left:0;">Subject Cabinet</div>
                                        <h3 class="subject-atlas-band-title mt-4"><?= htmlspecialchars($rootTheme['name']) ?></h3>
                                    </div>
                                    <div class="subject-atlas-panel max-w-[12rem] w-full sm:w-auto">
                                        <span class="subject-atlas-meta-label">Linked Public Items</span>
                                        <span class="subject-atlas-meta-value text-[2.35rem]"><?= $rootCount ?></span>
                                    </div>
                                </div>

                                <?php if (!empty($rootTheme['description'])): ?>
                                    <p class="subject-atlas-band-copy">
                                        <?= htmlspecialchars($rootTheme['description']) ?>
                                    </p>
                                <?php else: ?>
                                    <p class="subject-atlas-band-copy">
                                        A collector pathway that gathers related issues, covers, postmarks, and adjacent archive
                                        material under one navigable theme.
                                    </p>
                                <?php endif; ?>

                                <?php if ($children): ?>
                                    <div class="subject-atlas-chip-row">
                                        <?php foreach (array_slice($children, 0, 4) as $child): ?>
                                            <span class="subject-atlas-chip">
                                                <span><?= htmlspecialchars($child['name']) ?></span>
                                                <strong><?= (int)($themeCounts[(int)$child['id']] ?? 0) ?></strong>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($children) > 4): ?>
                                            <span class="subject-atlas-chip">+<?= count($children) - 4 ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="subject-atlas-link">
                                    Enter subject route
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12h14m-6-6l6 6-6 6" />
                                    </svg>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="subject-atlas-section">
                <div class="subject-atlas-section-head">
                    <span class="subject-atlas-kicker">Full Index</span>
                    <h2 class="subject-atlas-title">Survey every published subject at once.</h2>
                    <p class="subject-atlas-copy">
                        Use the full index when you already know the topic you want, or when you want to scan the archive's
                        complete thematic vocabulary in one pass.
                    </p>
                </div>

                <div class="subject-atlas-panel">
                    <div class="subject-atlas-index">
                        <?php foreach ($allThemes as $theme): ?>
                            <a href="<?= SITE_URL ?>/subject/<?= urlencode($theme['slug']) ?>">
                                <span class="subject-atlas-index-name"><?= htmlspecialchars($theme['trail_label'] ?? str_repeat('-- ', (int)$theme['depth']) . $theme['name']) ?></span>
                                <span class="subject-atlas-index-count"><?= (int)($themeCounts[(int)$theme['id']] ?? 0) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php require_once ThemeManager::getFooter(); ?>
