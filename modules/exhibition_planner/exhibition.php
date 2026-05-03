<?php
// modules/exhibition_planner/exhibition.php
global $pdo, $storage;

$slug = trim((string)($_GET['slug'] ?? ''));

$pageStmt = $pdo->prepare("
    SELECT
        p.*,
        (
            SELECT COUNT(*)
            FROM module_exhibition_items ei
            WHERE ei.page_id = p.id
        ) AS item_count
    FROM module_exhibition_pages p
    WHERE p.slug = ?
    LIMIT 1
");
$pageStmt->execute([$slug]);
$page = $pageStmt->fetch();

if (!$page) {
    http_response_code(404);
    $pageTitle = 'Exhibition Not Found';
    require_once ThemeManager::getHeader();
    ?>
    <div class="max-w-3xl mx-auto px-4 py-24 text-center">
        <h1 class="text-4xl font-black text-slate-900 tracking-tight">Exhibition not found</h1>
        <p class="text-slate-500 mt-4">The exhibition you requested does not exist or is no longer available.</p>
        <a href="<?= SITE_URL ?>/exhibitions" class="inline-flex items-center gap-2 mt-8 px-6 py-3 rounded-2xl bg-slate-900 text-white font-bold hover:bg-slate-800 transition">
            Back to Exhibitions
        </a>
    </div>
    <?php
    require_once ThemeManager::getFooter();
    return;
}

$itemsStmt = $pdo->prepare("
    SELECT
        i.id,
        i.reg_number,
        i.title,
        i.physical_description,
        ei.annotation,
        ei.sort_order,
        media.file_path AS primary_media
    FROM module_exhibition_items ei
    JOIN items i ON i.id = ei.item_id
    LEFT JOIN media media ON media.id = (
        SELECT m.id
        FROM media m
        WHERE m.item_id = i.id AND m.media_type = 'image'
        ORDER BY m.upload_date DESC, m.id DESC
        LIMIT 1
    )
    WHERE ei.page_id = ?
    ORDER BY ei.sort_order ASC, ei.id ASC
");
$itemsStmt->execute([$page['id']]);
$exhibitionItems = $itemsStmt->fetchAll();

$pageTitle = $page['title'];
require_once ThemeManager::getHeader();
?>

<div class="relative overflow-hidden bg-slate-950 text-white">
    <?php if (trim((string)$page['banner_image']) !== ''): ?>
        <img src="<?= htmlspecialchars($page['banner_image']) ?>" alt="<?= htmlspecialchars($page['title']) ?>" class="absolute inset-0 w-full h-full object-cover opacity-30">
    <?php endif; ?>
    <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(2,6,23,0.96),rgba(15,23,42,0.88),rgba(30,41,59,0.92))]"></div>

    <div class="relative max-w-5xl mx-auto px-4 py-20 md:py-24">
        <a href="<?= SITE_URL ?>/exhibitions" class="inline-flex items-center gap-2 text-sm font-bold text-blue-300 uppercase tracking-[0.28em] hover:text-blue-200 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            All Exhibitions
        </a>

        <div class="mt-8 max-w-4xl">
            <div class="flex flex-wrap items-center gap-3 mb-6">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/10 backdrop-blur text-white text-[11px] font-black uppercase tracking-widest">
                    Exhibition
                </span>
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-500/80 text-white text-[11px] font-black uppercase tracking-widest">
                    <?= (int)$page['item_count'] ?> artifact<?= (int)$page['item_count'] === 1 ? '' : 's' ?>
                </span>
            </div>

            <h1 class="text-5xl md:text-7xl font-black tracking-tight mb-8"><?= htmlspecialchars($page['title']) ?></h1>

            <?php if (trim((string)$page['description']) !== ''): ?>
                <div class="max-w-3xl text-lg md:text-xl text-slate-200 leading-relaxed">
                    <?= nl2br(htmlspecialchars($page['description'])) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 py-16 md:py-20">
    <?php if (!$exhibitionItems): ?>
        <div class="rounded-[2rem] border border-slate-200 bg-slate-50 px-8 py-16 text-center">
            <h2 class="text-2xl font-bold text-slate-700">This exhibition is still being assembled</h2>
            <p class="text-slate-500 mt-3">Curated artifacts have not been added yet. Please check back soon.</p>
        </div>
    <?php else: ?>
        <div class="space-y-20">
            <?php foreach ($exhibitionItems as $index => $item): ?>
                <?php $reverse = $index % 2 === 1; ?>
                <article class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-14 items-center">
                    <div class="<?= $reverse ? 'lg:order-2' : '' ?>">
                        <div class="relative overflow-hidden rounded-[2.5rem] bg-slate-100 shadow-2xl shadow-slate-200/60">
                            <?php if (!empty($item['primary_media'])): ?>
                                <img src="<?= MediaProcessor::url($item['primary_media'], 'display', 'image', $storage ?? null) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full aspect-[4/5] object-cover">
                            <?php else: ?>
                                <div class="w-full aspect-[4/5] flex items-center justify-center bg-slate-100 text-slate-400 font-bold uppercase tracking-[0.3em] text-sm">
                                    No Image Available
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="space-y-6 <?= $reverse ? 'lg:order-1' : '' ?>">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-black uppercase tracking-[0.25em]">
                                Artifact <?= $index + 1 ?>
                            </span>
                            <?php if (trim((string)$item['reg_number']) !== ''): ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-black uppercase tracking-[0.2em]">
                                    <?= htmlspecialchars($item['reg_number']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <h2 class="text-4xl md:text-5xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars($item['title']) ?></h2>
                        </div>

                        <?php if (trim((string)$item['annotation']) !== ''): ?>
                            <div class="rounded-[1.75rem] bg-slate-50 border border-slate-200 p-6">
                                <div class="text-xs font-black uppercase tracking-[0.25em] text-slate-400 mb-3">Curator Note</div>
                                <div class="text-lg text-slate-700 leading-relaxed"><?= nl2br(htmlspecialchars($item['annotation'])) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (trim(strip_tags((string)$item['physical_description'])) !== ''): ?>
                            <div class="prose prose-slate prose-lg max-w-none">
                                <?= $item['physical_description'] ?>
                            </div>
                        <?php endif; ?>

                        <div class="pt-2">
                            <a href="<?= SITE_URL ?>/item/<?= (int)$item['id'] ?>" class="inline-flex items-center gap-3 px-7 py-4 rounded-2xl bg-slate-900 text-white font-bold hover:bg-slate-800 transition shadow-lg shadow-slate-200">
                                View Exhibit Item
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once ThemeManager::getFooter(); ?>
