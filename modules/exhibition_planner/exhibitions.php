<?php
// modules/exhibition_planner/exhibitions.php
global $pdo;

if (!function_exists('exhibitionPlannerPublicExcerpt')) {
    function exhibitionPlannerPublicExcerpt(?string $text, int $limit = 160): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string)$text)));
        if ($plain === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($plain) > $limit ? mb_substr($plain, 0, $limit - 1) . '...' : $plain;
        }

        return strlen($plain) > $limit ? substr($plain, 0, $limit - 1) . '...' : $plain;
    }
}

$pages = $pdo->query("
    SELECT
        p.*,
        (
            SELECT COUNT(*)
            FROM module_exhibition_items ei
            WHERE ei.page_id = p.id
        ) AS item_count
    FROM module_exhibition_pages p
    ORDER BY p.created_at DESC, p.title ASC
")->fetchAll();

$pageTitle = "Virtual Exhibitions";
require_once ThemeManager::getHeader();
?>

<div class="bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 text-white">
    <div class="max-w-6xl mx-auto px-4 py-20 md:py-24">
        <div class="max-w-3xl">
            <p class="text-xs font-black uppercase tracking-[0.35em] text-blue-300 mb-5">Curated Storytelling</p>
            <h1 class="text-5xl md:text-6xl font-black tracking-tight mb-6">Virtual Exhibitions</h1>
            <p class="text-lg md:text-xl text-slate-300 leading-relaxed">
                Explore focused journeys through the archive, each one pairing curator context with a carefully ordered set of artifacts.
            </p>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 py-14 md:py-20">
    <?php if (!$pages): ?>
        <div class="rounded-[2rem] border border-slate-200 bg-slate-50 px-8 py-16 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <h2 class="text-2xl font-bold text-slate-700">No exhibitions are live yet</h2>
            <p class="text-slate-500 mt-3">New public exhibitions will appear here once they have been curated in the planner.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <?php foreach ($pages as $page): ?>
                <?php $excerpt = exhibitionPlannerPublicExcerpt($page['description']); ?>
                <a href="<?= SITE_URL ?>/exhibition/<?= rawurlencode($page['slug']) ?>" class="group block">
                    <article class="overflow-hidden rounded-[2.25rem] border border-slate-200 bg-white shadow-sm hover:shadow-2xl hover:shadow-slate-200/70 transition-all duration-300 hover:-translate-y-1">
                        <div class="relative h-72 overflow-hidden bg-slate-900">
                            <?php if (trim((string)$page['banner_image']) !== ''): ?>
                                <img src="<?= htmlspecialchars($page['banner_image']) ?>" alt="<?= htmlspecialchars($page['title']) ?>" class="w-full h-full object-cover opacity-80 group-hover:scale-105 transition-transform duration-700">
                            <?php else: ?>
                                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.35),_transparent_35%),linear-gradient(135deg,#0f172a,#020617)]"></div>
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/30 to-transparent"></div>
                            <div class="absolute top-5 left-5">
                                <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/10 backdrop-blur text-white text-[11px] font-black uppercase tracking-widest">
                                    <?= (int)$page['item_count'] ?> item<?= (int)$page['item_count'] === 1 ? '' : 's' ?>
                                </span>
                            </div>
                            <div class="absolute bottom-0 left-0 right-0 p-7">
                                <h2 class="text-3xl font-black text-white tracking-tight"><?= htmlspecialchars($page['title']) ?></h2>
                            </div>
                        </div>

                        <div class="p-7">
                            <p class="text-slate-600 leading-relaxed min-h-[72px]">
                                <?= $excerpt !== '' ? htmlspecialchars($excerpt) : 'Open this exhibition to read the curator introduction and move through the selected artifacts.' ?>
                            </p>
                            <div class="mt-6 inline-flex items-center gap-3 text-sm font-bold text-blue-700">
                                Enter Exhibition
                                <svg class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                </svg>
                            </div>
                        </div>
                    </article>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once ThemeManager::getFooter(); ?>
