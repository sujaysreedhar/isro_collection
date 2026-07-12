<?php
// Cinematic public lobby for published exhibitions.
global $pdo;
if (!isset($pdo)) require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/ThemeManager.php';

$exhibitions = [];
try { $exhibitions = $pdo->query('SELECT * FROM museum_exhibitions WHERE is_published = 1 ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

$cards = [];
foreach ($exhibitions as $ex) {
    $items = $this->resolveItems($ex);
    $layout_label = ['grand_hall'=>'Grand Hall','l_gallery'=>'L-Gallery','courtyard_loop'=>'Courtyard Loop'][$ex['layout'] ?? 'grand_hall'] ?? 'Grand Hall';
    $cards[] = ['exhibition'=>$ex,'cover'=>$items[0]['display'] ?? null,'item_count'=>count($items),'layout_label'=>$layout_label];
}

$pageTitle = 'Virtual Museum - ' . SITE_TITLE;
$currentMenu = 'museum';
require_once ThemeManager::getHeader();
?>
<style>
.vm-lobby{--ink:#eef4f8;--muted:#95a7b8;--line:rgba(159,183,204,.2);--accent:#d8ad70;background:radial-gradient(circle at 74% 5%,#26384b 0,#101923 32%,#080d13 76%);color:var(--ink);min-height:75vh}
.vm-lobby-card{background:linear-gradient(145deg,rgba(28,42,57,.9),rgba(12,20,29,.94));border:1px solid var(--line);box-shadow:0 24px 70px rgba(0,0,0,.3);transition:transform .25s ease,border-color .25s ease,box-shadow .25s ease}
.vm-lobby-card:hover{transform:translateY(-5px);border-color:rgba(216,173,112,.58);box-shadow:0 30px 85px rgba(0,0,0,.48)}
.vm-lobby a:focus-visible{outline:3px solid rgba(240,200,135,.72);outline-offset:4px}
@media(prefers-reduced-motion:reduce){.vm-lobby-card{transition:none}.vm-lobby-card:hover{transform:none}}
</style>

<main class="vm-lobby -mt-px">
    <section class="max-w-7xl mx-auto px-5 sm:px-7 lg:px-8 pt-16 md:pt-24 pb-12">
        <div class="grid lg:grid-cols-[1fr_360px] gap-10 items-end border-b border-white/10 pb-12">
            <div>
                <div class="inline-flex items-center gap-2 text-[#d8ad70] text-xs font-bold uppercase tracking-[.23em]">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 21h18M4 21V9l8-5 8 5v12M8 12h8M9 21v-5h6v5"/></svg>
                    Immersive collection
                </div>
                <h1 class="mt-5 max-w-4xl text-4xl sm:text-6xl lg:text-7xl font-bold tracking-[-.04em] leading-[.96]">Enter the archive.<br><span class="text-[#aebfce]">Walk through its stories.</span></h1>
            </div>
            <p class="text-[#95a7b8] text-base md:text-lg leading-relaxed max-w-xl">Explore curated objects in walkable 3D rooms, or switch to the accessible 2D collection view at any time.</p>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-5 sm:px-7 lg:px-8 pb-24" aria-labelledby="vm-exhibitions-heading">
        <div class="flex items-center justify-between gap-4 mb-7">
            <h2 id="vm-exhibitions-heading" class="text-xl font-bold">Current exhibitions</h2>
            <span class="text-sm text-[#95a7b8]"><?= count($cards) ?> available</span>
        </div>
        <?php if (!$cards): ?>
            <div class="rounded-3xl border border-white/10 bg-white/[.035] p-10 md:p-16 text-center">
                <svg class="w-14 h-14 mx-auto text-[#d8ad70]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M4 20h16M5 20V9l7-4 7 4v11M8 13h8M9 20v-4h6v4"/></svg>
                <h3 class="text-xl font-bold mt-5">The next exhibition is being prepared</h3>
                <p class="text-[#95a7b8] mt-2">Please return when the gallery doors open.</p>
            </div>
        <?php else: ?>
            <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach ($cards as $card): $ex=$card['exhibition']; $url=SITE_URL.'/museum/'.rawurlencode($ex['slug']); ?>
                <article class="vm-lobby-card rounded-3xl overflow-hidden flex flex-col">
                    <a href="<?= htmlspecialchars($url) ?>" class="group block relative aspect-[16/10] overflow-hidden bg-[#111b26]" aria-label="Enter <?= htmlspecialchars($ex['title']) ?> in 3D">
                        <?php if ($card['cover']): ?><img src="<?= htmlspecialchars($card['cover']) ?>" alt="" width="720" height="450" loading="lazy" decoding="async" class="w-full h-full object-cover opacity-80 group-hover:scale-[1.03] group-hover:opacity-100 transition duration-500"><?php endif; ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-[#0b121a] via-transparent to-black/10"></div>
                        <span class="absolute left-4 bottom-4 inline-flex items-center gap-2 px-3 py-2 rounded-full bg-black/45 backdrop-blur-md border border-white/15 text-xs font-bold">Enter in 3D <span aria-hidden="true">→</span></span>
                    </a>
                    <div class="p-6 flex-1 flex flex-col">
                        <p class="text-[11px] uppercase tracking-[.16em] text-[#d8ad70] font-bold"><?= htmlspecialchars($card['layout_label']) ?> · <?= (int) $card['item_count'] ?> objects</p>
                        <h3 class="text-xl font-bold mt-2"><?= htmlspecialchars($ex['title']) ?></h3>
                        <p class="text-sm text-[#95a7b8] leading-relaxed mt-3 line-clamp-3"><?= htmlspecialchars(trim(strip_tags($ex['description'] ?? '')) ?: 'A curated walk through the collection.') ?></p>
                        <div class="mt-6 pt-4 border-t border-white/10 flex gap-3">
                            <a href="<?= htmlspecialchars($url) ?>" class="text-sm font-bold text-[#f0c887]">3D experience</a>
                            <a href="<?= htmlspecialchars($url) ?>?view=2d" class="text-sm font-bold text-[#b3c2ce]">Browse in 2D</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require_once ThemeManager::getFooter(); ?>
