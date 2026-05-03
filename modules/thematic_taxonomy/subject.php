<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/ThemeManager.php';

global $activeModulesSlugs, $storage;

$slug = $_GET['slug'] ?? '';

if (!in_array('thematic_taxonomy', $activeModulesSlugs, true)) {
    http_response_code(404);
    require_once ThemeManager::getTemplatePath('404.php');
    return;
}

if ($slug === '') {
    header('Location: ' . SITE_URL . '/subjects');
    exit;
}

$theme = $this->getThemeBySlug($slug, true);
if (!$theme) {
    http_response_code(404);
    require_once ThemeManager::getTemplatePath('404.php');
    return;
}

$breadcrumbs = $this->getThemeLineage((int)$theme['id'], true);
$childThemes = $this->getChildThemes((int)$theme['id'], true);
$themeCounts = $this->getThemeAggregateCounts(true);
$descendantIds = $this->getDescendantIds((int)$theme['id'], true);

$perPage = 24;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;
$totalResults = $this->countItemsForThemes($descendantIds);
$totalPages = max(1, (int)ceil($totalResults / $perPage));
if ($totalResults > 0 && $offset >= $totalResults) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $perPage;
}
$items = $this->getItemsForThemes($descendantIds, $perPage, $offset);

$pageTitle = $theme['name'] . ' - Subject - ' . SITE_TITLE;
$ogTitle = $pageTitle;
$ogDescription = $theme['description'] !== ''
    ? $theme['description']
    : 'Browse collection items filed under the ' . $theme['name'] . ' subject.';
$ogUrl = SITE_URL . '/subject/' . $theme['slug'];
$currentMenu = 'subjects';

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
                    <nav class="subject-atlas-breadcrumbs" aria-label="Breadcrumb">
                        <a href="<?= SITE_URL ?>">Archive</a>
                        <span>/</span>
                        <a href="<?= SITE_URL ?>/subjects">Subjects</a>
                        <?php foreach ($breadcrumbs as $index => $crumb): ?>
                            <span>/</span>
                            <?php if ($index < count($breadcrumbs) - 1): ?>
                                <a href="<?= SITE_URL ?>/subject/<?= urlencode($crumb['slug']) ?>"><?= htmlspecialchars($crumb['name']) ?></a>
                            <?php else: ?>
                                <span class="is-current"><?= htmlspecialchars($crumb['name']) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>

                    <span class="subject-atlas-kicker mt-8">Curated Subject Route</span>
                    <h1 class="subject-atlas-display mt-6"><?= htmlspecialchars($theme['name']) ?></h1>
                    <?php if (!empty($theme['description'])): ?>
                        <p class="subject-atlas-lead"><?= htmlspecialchars($theme['description']) ?></p>
                    <?php else: ?>
                        <p class="subject-atlas-lead">
                            A collector path that groups related pieces of the archive under one readable subject route,
                            linking items and subsubjects without forcing visitors to think in terms of format first.
                        </p>
                    <?php endif; ?>
                </div>

                <aside class="subject-atlas-meta">
                    <div class="subject-atlas-meta-block">
                        <span class="subject-atlas-meta-label">Linked Items</span>
                        <span class="subject-atlas-meta-value"><?= $totalResults ?></span>
                    </div>
                    <div class="subject-atlas-meta-block">
                        <span class="subject-atlas-meta-label">Immediate Subsubjects</span>
                        <span class="subject-atlas-meta-value"><?= count($childThemes) ?></span>
                    </div>
                    <div class="subject-atlas-meta-block">
                        <span class="subject-atlas-meta-label">Subject Lineage</span>
                        <p class="mt-3 text-sm leading-7" style="color:var(--atlas-muted);">
                            <?= htmlspecialchars(implode(' / ', array_map(static function ($crumb) {
                                return $crumb['name'];
                            }, $breadcrumbs))) ?>
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <div class="subject-atlas-shell pb-20">
        <?php if ($childThemes): ?>
            <section class="subject-atlas-section">
                <div class="subject-atlas-section-head">
                    <span class="subject-atlas-kicker">Next Branches</span>
                    <h2 class="subject-atlas-title">Continue deeper into the subject map.</h2>
                    <p class="subject-atlas-copy">
                        These subsubjects narrow the scope of <?= htmlspecialchars($theme['name']) ?> into smaller cabinets
                        that visitors can browse with the same collector-first logic.
                    </p>
                </div>
 
                <div class="subject-atlas-stack">
                    <?php foreach ($childThemes as $index => $child): ?>
                        <a href="<?= SITE_URL ?>/subject/<?= urlencode($child['slug']) ?>" class="subject-atlas-band group">
                            <div class="subject-atlas-order"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></div>
                            <div>
                                <div class="flex flex-wrap items-center justify-between gap-4">
                                    <h3 class="subject-atlas-band-title"><?= htmlspecialchars($child['name']) ?></h3>
                                    <div class="subject-atlas-panel max-w-[12rem] w-full sm:w-auto">
                                        <span class="subject-atlas-meta-label">Public Items</span>
                                        <span class="subject-atlas-meta-value text-[2.35rem]"><?= (int)($themeCounts[(int)$child['id']] ?? 0) ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($child['description'])): ?>
                                    <p class="subject-atlas-band-copy"><?= htmlspecialchars($child['description']) ?></p>
                                <?php else: ?>
                                    <p class="subject-atlas-band-copy">
                                        A narrower collector route nested within <?= htmlspecialchars($theme['name']) ?>.
                                    </p>
                                <?php endif; ?>
                                <div class="subject-atlas-link">
                                    Open subsubject
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12h14m-6-6l6 6-6 6" />
                                    </svg>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="subject-atlas-section">
            <div class="subject-atlas-section-head">
                <span class="subject-atlas-kicker">Archive Records</span>
                <h2 class="subject-atlas-title">Items gathered under this route.</h2>
                <p class="subject-atlas-copy">
                    Public records linked to this subject and its descendants, arranged as a visual browsing field rather
                    than a plain listing.
                </p>
            </div>

            <?php if ($items): ?>
                <div class="subject-atlas-grid subject-atlas-grid-3">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $imgUrl = '';
                        if (!empty($item['preview_file_path'])) {
                            if (isset($storage) && $storage) {
                                $imgUrl = $storage->url('display/' . $item['preview_file_path']);
                            } else {
                                $displayFile = $item['preview_file_path'];
                                $displayPath = ABSPATH . '/uploads/display/' . $displayFile;
                                $imgUrl = file_exists($displayPath)
                                    ? SITE_URL . '/uploads/display/' . rawurlencode($displayFile)
                                    : SITE_URL . '/uploads/originals/' . rawurlencode($displayFile);
                            }
                        }
                        $desc = trim(strip_tags($item['physical_description'] ?? ''));
                        ?>
                        <a href="<?= SITE_URL ?>/item/<?= (int)$item['id'] ?>" class="subject-atlas-item group">
                            <div class="subject-atlas-media">
                                <?php if ($imgUrl !== ''): ?>
                                    <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($item['title']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="flex items-center justify-center h-full" style="color:var(--atlas-muted);">
                                        <svg class="w-12 h-12 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                            </path>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <div class="subject-atlas-reg"><?= htmlspecialchars($item['reg_number']) ?></div>
                            </div>

                            <div class="subject-atlas-item-copy">
                                <?php if (!empty($item['production_date'])): ?>
                                    <div class="subject-atlas-item-date"><?= htmlspecialchars($item['production_date']) ?></div>
                                <?php endif; ?>
                                <h3 class="subject-atlas-item-title"><?= htmlspecialchars($item['title']) ?></h3>
                                <p class="text-sm leading-7" style="color:var(--atlas-muted);">
                                    <?= htmlspecialchars($desc !== '' ? $desc : 'No description available yet.') ?>
                                </p>
                                <div class="subject-atlas-link">
                                    View record
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12h14m-6-6l6 6-6 6" />
                                    </svg>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="mt-10">
                    <?php require ThemeManager::getTemplatePath('partials/pagination.php'); ?>
                </div>
            <?php else: ?>
                <div class="subject-atlas-empty">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                                        </path>
                                    </svg>
                    <h3 class="subject-atlas-title mt-5">No public items assigned yet</h3>
                    <p class="subject-atlas-copy mx-auto mt-3">
                        Assign this subject to collection items from the item editor to populate this route with live archive material.
                    </p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php require_once ThemeManager::getFooter(); ?>
