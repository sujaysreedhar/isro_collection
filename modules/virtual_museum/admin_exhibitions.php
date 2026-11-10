<?php
// Guided full-page exhibition builder. Rendered inside admin/module_page.php.
global $pdo;

$csrf = function_exists('ensureCsrfToken') ? ensureCsrfToken() : '';
$baseUrl = SITE_URL . '/admin/module_page.php?m=virtual_museum';
$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);

$sourceOptions = ['category' => [], 'collection' => [], 'tag' => []];
try { $sourceOptions['category'] = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
try { $sourceOptions['collection'] = $pdo->query('SELECT id, title AS name FROM collections ORDER BY title')->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
try { $sourceOptions['tag'] = $pdo->query('SELECT id, name FROM tags ORDER BY name')->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

$exhibition = $id > 0 ? $this->getExhibitionById($id) : null;
$isEditor = in_array($action, ['new', 'edit'], true);
$sources = $exhibition ? $this->getExhibitionSources($id, $exhibition) : [];
$overrides = $exhibition ? $this->getExhibitionOverrides($id) : [];
$resolved = $exhibition ? $this->resolveItems($exhibition) : [];

$exhibitions = [];
if (!$isEditor) {
    try { $exhibitions = $pdo->query('SELECT * FROM museum_exhibitions ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
}

$icon = static function (string $path, string $class = 'w-5 h-5'): string {
    return '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="' . $path . '"/></svg>';
};
?>

<style>
.vm-shell {
    --vm-bg: #090e15;
    --vm-panel: rgba(18, 29, 41, 0.7);
    --vm-panel-2: rgba(23, 37, 54, 0.85);
    --vm-line: rgba(42, 58, 77, 0.6);
    --vm-text: #edf3f7;
    --vm-muted: #8fa1b3;
    --vm-accent: #d2a566;
    --vm-accent-2: #f0c887;
    --vm-glass-border: rgba(255, 255, 255, 0.05);
    color: var(--vm-text);
    background: radial-gradient(circle at top right, rgba(23,37,54,0.4), transparent 50%),
                radial-gradient(circle at bottom left, rgba(210,165,102,0.05), transparent 40%),
                var(--vm-bg);
}

.vm-card {
    background: var(--vm-panel);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--vm-glass-border);
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.05);
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.vm-card:hover {
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

article.vm-card:hover {
    transform: translateY(-4px);
    border-color: rgba(210,165,102,0.3);
}

.vm-input {
    width: 100%;
    min-height: 46px;
    border: 1px solid rgba(52, 72, 94, 0.8);
    background: rgba(13, 23, 34, 0.6);
    color: #fff;
    border-radius: 0.75rem;
    padding: 0.7rem 0.85rem;
    outline: none;
    transition: all 0.2s ease;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
}
.vm-input:focus {
    background: rgba(13, 23, 34, 0.9);
    border-color: var(--vm-accent);
    box-shadow: 0 0 0 4px rgba(210,165,102,0.15), inset 0 1px 2px rgba(0,0,0,0.2);
}

.vm-btn {
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: 0.75rem;
    padding: 0.65rem 1rem;
    font-weight: 700;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.vm-btn:active { transform: scale(0.97); }
.vm-btn:focus-visible, .vm-action:focus-visible {
    outline: 3px solid rgba(240,200,135,0.55);
    outline-offset: 2px;
}

.vm-primary {
    background: linear-gradient(135deg, var(--vm-accent), var(--vm-accent-2));
    color: #111820;
    box-shadow: 0 4px 12px rgba(210,165,102,0.2);
    border: none;
}
.vm-primary:hover {
    box-shadow: 0 6px 16px rgba(210,165,102,0.35);
    transform: translateY(-1px);
}
.vm-primary::after {
    content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(rgba(255,255,255,0.2), transparent);
    opacity: 0; transition: opacity 0.2s;
}
.vm-primary:hover::after { opacity: 1; }

.vm-secondary {
    background: rgba(32, 48, 68, 0.6);
    backdrop-filter: blur(8px);
    color: #e8eef3;
    border: 1px solid rgba(53, 73, 96, 0.8);
}
.vm-secondary:hover {
    background: rgba(41, 59, 80, 0.8);
    border-color: rgba(64, 85, 107, 1);
    transform: translateY(-1px);
}

.vm-step {
    transition: all 0.3s ease;
    position: relative;
}
.vm-step::after {
    content: ''; position: absolute; bottom: -4px; left: 10%; right: 10%; height: 2px;
    background: var(--vm-accent); border-radius: 2px;
    opacity: 0; transform: scaleX(0); transition: all 0.3s ease;
}
.vm-step[aria-current="step"] {
    color: var(--vm-accent-2);
    background: rgba(210,165,102,0.05);
}
.vm-step[aria-current="step"]::after {
    opacity: 1; transform: scaleX(1);
}

@keyframes slideUpFade {
    0% { opacity: 0; transform: translateY(12px) scale(0.99); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}

.vm-stage { display: none; }
.vm-stage.is-active {
    display: block;
    animation: slideUpFade 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.vm-source-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.75rem;
    border: 1px solid rgba(64, 85, 107, 0.6);
    background: rgba(32, 50, 71, 0.6);
    backdrop-filter: blur(4px);
    border-radius: 999px;
    font-size: 0.78rem;
    transition: all 0.2s ease;
}
.vm-source-chip:hover {
    border-color: var(--vm-accent);
    background: rgba(32, 50, 71, 0.9);
}

.vm-item-card {
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.vm-item-card:hover {
    border-color: rgba(210,165,102,0.4);
    background: rgba(23, 37, 54, 0.8);
}
.vm-item-card[aria-grabbed="true"] {
    opacity: 0.6;
    border-color: var(--vm-accent);
    transform: scale(0.98);
    box-shadow: 0 0 0 2px rgba(210,165,102,0.3);
}

.vm-choice {
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.vm-choice:hover {
    border-color: rgba(210,165,102,0.4);
    transform: translateY(-2px);
    background: rgba(23, 37, 54, 0.8);
}
.vm-choice[aria-checked="true"] {
    border-color: var(--vm-accent);
    background: rgba(210,165,102,0.05);
    box-shadow: 0 0 0 2px rgba(210,165,102,0.18), inset 0 0 20px rgba(210,165,102,0.05);
}

.vm-scroll::-webkit-scrollbar { width: 6px; }
.vm-scroll::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 3px; }
.vm-scroll::-webkit-scrollbar-thumb { background: rgba(143,161,179,0.3); border-radius: 3px; }
.vm-scroll::-webkit-scrollbar-thumb:hover { background: rgba(143,161,179,0.5); }

@media(max-width:767px) {
    .vm-step-label { display: none; }
    .vm-sticky { position: static !important; }
}
</style>

<div class="vm-shell -m-6 md:-m-8 min-h-[calc(100vh-4rem)] bg-transparent p-5 md:p-8">
<?php if (!$isEditor): ?>
    <header class="max-w-7xl mx-auto flex flex-col sm:flex-row sm:items-end justify-between gap-5 mb-8">
        <div>
            <p class="text-xs uppercase tracking-[.22em] text-[#d2a566] font-bold">Curator workspace</p>
            <h1 class="text-3xl md:text-4xl font-bold mt-2">Virtual Museum</h1>
            <p class="text-sm text-[#8fa1b3] mt-2">Build cinematic, accessible exhibitions from multiple collection sources.</p>
        </div>
        <a href="<?= $baseUrl ?>&action=new" class="vm-btn vm-primary"><?= $icon('M12 5v14M5 12h14') ?> New exhibition</a>
    </header>

    <main class="max-w-7xl mx-auto">
        <?php if (!$exhibitions): ?>
            <section class="vm-card rounded-2xl p-12 text-center">
                <div class="w-14 h-14 rounded-2xl bg-[#24364a] text-[#d2a566] grid place-items-center mx-auto mb-5"><?= $icon('M3 21h18M4 21V9l8-5 8 5v12M9 21v-6h6v6', 'w-7 h-7') ?></div>
                <h2 class="text-xl font-bold">Create your first exhibition</h2>
                <p class="text-[#8fa1b3] mt-2 mb-6">Combine sources, curate the sequence, and choose a room layout.</p>
                <a href="<?= $baseUrl ?>&action=new" class="vm-btn vm-primary">Start building</a>
            </section>
        <?php else: ?>
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($exhibitions as $ex):
                    $items = $this->resolveItems($ex);
                    $cover = $items[0]['thumb'] ?? null;
                    $exSources = $this->getExhibitionSources((int) $ex['id'], $ex);
                    $sourceText = count($exSources) . ' source' . (count($exSources) === 1 ? '' : 's');
                ?>
                <article class="vm-card rounded-2xl overflow-hidden flex flex-col" data-exhibition-id="<?= (int) $ex['id'] ?>">
                    <div class="aspect-[16/8] bg-[#101b27] relative overflow-hidden">
                        <?php if ($cover): ?><img src="<?= htmlspecialchars($cover) ?>" alt="" loading="lazy" class="w-full h-full object-cover opacity-80"><?php endif; ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-[#101923] via-transparent to-transparent"></div>
                        <span class="absolute top-3 left-3 px-2.5 py-1 rounded-full text-[11px] font-bold <?= (int) $ex['is_published'] ? 'bg-emerald-400/20 text-emerald-200' : 'bg-slate-300/15 text-slate-200' ?>">
                            <?= (int) $ex['is_published'] ? 'Published' : 'Draft' ?>
                        </span>
                    </div>
                    <div class="p-5 flex-1">
                        <h2 class="text-lg font-bold line-clamp-1"><?= htmlspecialchars($ex['title']) ?></h2>
                        <p class="text-xs text-[#8fa1b3] mt-2"><?= count($items) ?> items · <?= htmlspecialchars($sourceText) ?> · <?= htmlspecialchars(ucwords(str_replace('_', ' ', $ex['layout'] ?? 'grand_hall'))) ?></p>
                    </div>
                    <div class="px-5 pb-5 flex flex-wrap gap-2">
                        <a class="vm-btn vm-secondary text-xs flex-1" href="<?= $baseUrl ?>&action=edit&id=<?= (int) $ex['id'] ?>">Edit</a>
                        <a class="vm-btn vm-secondary text-xs" href="<?= SITE_URL ?>/museum/preview/<?= (int) $ex['id'] ?>" target="_blank" rel="noopener">Preview</a>
                        <button class="vm-action vm-duplicate vm-btn vm-secondary text-xs" data-id="<?= (int) $ex['id'] ?>">Duplicate</button>
                        <button class="vm-action vm-delete vm-btn text-rose-300 bg-rose-500/10 border border-rose-500/25 text-xs" data-id="<?= (int) $ex['id'] ?>" data-title="<?= htmlspecialchars($ex['title'], ENT_QUOTES) ?>" aria-label="Delete <?= htmlspecialchars($ex['title']) ?>"><?= $icon('M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14') ?></button>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
<?php else: ?>
    <header class="max-w-7xl mx-auto mb-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="<?= $baseUrl ?>" class="vm-btn vm-secondary" aria-label="Back to exhibitions"><?= $icon('M15 18l-6-6 6-6') ?></a>
            <div>
                <p class="text-xs uppercase tracking-[.2em] text-[#d2a566] font-bold"><?= $exhibition ? 'Edit exhibition' : 'New exhibition' ?></p>
                <h1 id="vm-editor-heading" class="text-2xl font-bold mt-1"><?= htmlspecialchars($exhibition['title'] ?? 'Untitled exhibition') ?></h1>
            </div>
        </div>
        <?php if ($exhibition): ?><a href="<?= SITE_URL ?>/museum/preview/<?= $id ?>" target="_blank" rel="noopener" class="vm-btn vm-secondary">Preview draft <?= $icon('M14 3h7v7m0-7L10 14M5 7v14h14v-5') ?></a><?php endif; ?>
    </header>

    <nav class="max-w-7xl mx-auto vm-card rounded-2xl p-2 mb-6 grid grid-cols-4 gap-2" aria-label="Exhibition editor stages">
        <?php foreach ([['details','1','Details'],['sources','2','Sources'],['curation','3','Curate'],['room_publish','4','Rooms & publish']] as [$key,$number,$label]): ?>
            <button type="button" class="vm-step vm-action min-h-12 rounded-xl border border-transparent px-3 text-sm font-bold text-[#8fa1b3]" data-step="<?= $key ?>">
                <span class="inline-grid place-items-center w-6 h-6 rounded-full bg-white/5 mr-1"><?= $number ?></span><span class="vm-step-label"><?= $label ?></span>
            </button>
        <?php endforeach; ?>
    </nav>

    <main class="max-w-7xl mx-auto grid lg:grid-cols-[1fr_280px] gap-6 items-start">
        <section class="vm-card rounded-2xl p-5 md:p-7 min-h-[560px]">
            <div class="vm-stage" data-step="details">
                <p class="text-xs uppercase tracking-[.18em] text-[#d2a566] font-bold">Stage 1</p><h2 class="text-2xl font-bold mt-2">Exhibition details</h2>
                <p class="text-sm text-[#8fa1b3] mt-2 mb-7">Introduce the story. The public URL remains stable after creation.</p>
                <div class="space-y-5 max-w-3xl">
                    <label class="block"><span class="text-sm font-semibold block mb-2">Title *</span><input id="vm-title" class="vm-input" value="<?= htmlspecialchars($exhibition['title'] ?? '') ?>" maxlength="255" required></label>
                    <label class="block"><span class="text-sm font-semibold block mb-2">Description</span><textarea id="vm-description" class="vm-input min-h-32" rows="5"><?= htmlspecialchars($exhibition['description'] ?? '') ?></textarea></label>
                    <label class="block max-w-xs"><span class="text-sm font-semibold block mb-2">Maximum displayed items</span><input id="vm-item-limit" class="vm-input" type="number" min="1" max="100" value="<?= (int) ($exhibition['item_limit'] ?? 40) ?>"><span class="text-xs text-[#8fa1b3] mt-2 block">Hard limit: 100 items.</span></label>
                    <?php if ($exhibition): ?><div class="rounded-xl border border-[#314357] bg-[#0d1722] p-4"><span class="text-xs text-[#8fa1b3]">Stable public URL</span><code class="block text-sm text-[#f0c887] mt-1 break-all"><?= SITE_URL ?>/museum/<?= htmlspecialchars($exhibition['slug']) ?></code></div><?php endif; ?>
                </div>
            </div>

            <div class="vm-stage" data-step="sources">
                <p class="text-xs uppercase tracking-[.18em] text-[#d2a566] font-bold">Stage 2</p><h2 class="text-2xl font-bold mt-2">Combine collection sources</h2>
                <p class="text-sm text-[#8fa1b3] mt-2 mb-6">Items added to these sources will flow into the exhibition automatically.</p>
                <label class="block"><span class="sr-only">Search available sources</span><input id="vm-source-search" class="vm-input" type="search" placeholder="Search categories, collections, and tags…"></label>
                <div id="vm-selected-sources" class="flex flex-wrap gap-2 mt-4 min-h-10"></div>
                <div id="vm-source-results" class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3 mt-6"></div>
            </div>

            <div class="vm-stage" data-step="curation">
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div><p class="text-xs uppercase tracking-[.18em] text-[#d2a566] font-bold">Stage 3</p><h2 class="text-2xl font-bold mt-2">Curate the sequence</h2><p class="text-sm text-[#8fa1b3] mt-2">Drag cards or use the arrow controls. Removing a live item creates a persistent exclusion.</p></div>
                    <div class="text-sm text-[#8fa1b3]"><strong id="vm-resolved-count" class="text-white"><?= count($resolved) ?></strong> visible · <strong id="vm-excluded-count" class="text-white"><?= count(array_filter($overrides, static fn($o) => $o['selection_state'] === 'exclude')) ?></strong> excluded</div>
                </div>
                <div class="mt-6 grid xl:grid-cols-[1fr_320px] gap-6">
                    <div><div id="vm-curated-grid" class="grid sm:grid-cols-2 gap-3"></div></div>
                    <aside class="rounded-xl border border-[#2a3a4d] bg-[#0d1722] p-4 xl:sticky xl:top-4 self-start">
                        <h3 class="font-bold">Hand-pick items</h3><p class="text-xs text-[#8fa1b3] mt-1 mb-4">Search the complete visible catalog.</p>
                        <input id="vm-item-search" class="vm-input" type="search" placeholder="Title or registration no.">
                        <div id="vm-item-results" class="space-y-2 mt-4 max-h-[460px] overflow-y-auto vm-scroll"></div>
                        <button id="vm-load-more" type="button" class="vm-btn vm-secondary w-full mt-3 hidden">Load more</button>
                    </aside>
                </div>
            </div>

            <div class="vm-stage" data-step="room_publish">
                <p class="text-xs uppercase tracking-[.18em] text-[#d2a566] font-bold">Stage 4</p><h2 class="text-2xl font-bold mt-2">Rooms and publishing</h2>
                <p class="text-sm text-[#8fa1b3] mt-2 mb-7">Choose atmosphere and architecture independently.</p>
                <fieldset><legend class="font-bold mb-3">Room theme</legend><div class="grid sm:grid-cols-3 gap-3" id="vm-theme-choices">
                    <?php foreach (['classic'=>'Heritage','dark'=>'Nocturne','white_cube'=>'White cube'] as $value=>$label): ?><button type="button" class="vm-choice vm-action rounded-xl border border-[#34485e] bg-[#111c28] p-4 text-left min-h-24" data-theme="<?= $value ?>" role="radio"><strong><?= $label ?></strong><span class="block text-xs text-[#8fa1b3] mt-2"><?= $value === 'dark' ? 'Cinematic, spotlit, high contrast' : ($value === 'classic' ? 'Warm timber and gallery plaster' : 'Bright contemporary minimalism') ?></span></button><?php endforeach; ?>
                </div></fieldset>
                <fieldset class="mt-7"><legend class="font-bold mb-3">Room layout</legend><div class="grid sm:grid-cols-3 gap-3" id="vm-layout-choices">
                    <?php foreach (['grand_hall'=>['Grand Hall','Works with 1+ items'],'l_gallery'=>['L-Gallery','Recommended for 12+'],'courtyard_loop'=>['Courtyard Loop','Recommended for 24+']] as $value=>$copy): ?><button id="vm-layout-<?= $value ?>" type="button" class="vm-choice vm-action rounded-xl border border-[#34485e] bg-[#111c28] p-4 text-left min-h-28" data-layout="<?= $value ?>" role="radio"><span class="block h-10 rounded-lg bg-gradient-to-r from-[#25394c] to-[#3c2c22] mb-3"></span><strong><?= $copy[0] ?></strong><span class="block text-xs text-[#8fa1b3] mt-1"><?= $copy[1] ?></span></button><?php endforeach; ?>
                </div></fieldset>
                <fieldset class="mt-7"><legend class="font-bold mb-1">Centerpiece</legend>
                <p class="text-xs text-[#8fa1b3] mb-3">The sculptural model displayed at the room's focal point.</p>
                <div class="grid sm:grid-cols-3 gap-3" id="vm-centerpiece-choices">
                    <?php foreach ([
                        'auto'        => ['Automatic', 'Matches the chosen layout'],
                        'torus_knot'  => ['Torus knot', 'Bronze knot on a plinth'],
                        'icosahedron' => ['Gemstone', 'Faceted icosahedron'],
                        'obelisk'     => ['Obelisk', 'Four-sided stone spire'],
                        'globe'       => ['Globe', 'Polished sphere'],
                        'none'        => ['None', 'Leave the focal point bare'],
                    ] as $value => $copy): ?><button type="button" class="vm-choice vm-action rounded-xl border border-[#34485e] bg-[#111c28] p-4 text-left min-h-20" data-centerpiece="<?= $value ?>" role="radio"><strong><?= $copy[0] ?></strong><span class="block text-xs text-[#8fa1b3] mt-1"><?= $copy[1] ?></span></button><?php endforeach; ?>
                </div></fieldset>
                <div id="vm-layout-warning" class="hidden mt-4 rounded-xl border border-amber-400/25 bg-amber-300/10 text-amber-100 p-4 text-sm" role="status"></div>
                <label class="mt-7 flex items-start gap-3 rounded-xl border border-[#34485e] bg-[#111c28] p-4 cursor-pointer"><input id="vm-published" type="checkbox" class="mt-1 w-5 h-5 accent-[#d2a566]" <?= !empty($exhibition['is_published']) ? 'checked' : '' ?>><span><strong>Publish exhibition</strong><span class="block text-xs text-[#8fa1b3] mt-1">When off, the exhibition remains a private draft.</span></span></label>
            </div>

            <div id="vm-error" class="hidden mt-6 rounded-xl border border-rose-400/30 bg-rose-500/10 text-rose-200 p-4 text-sm" role="alert"></div>
        </section>

        <aside class="vm-card vm-sticky lg:sticky lg:top-6 rounded-2xl p-5">
            <p class="text-xs uppercase tracking-[.16em] text-[#8fa1b3] font-bold">Progress</p>
            <div class="mt-4 space-y-3 text-sm"><div>Details <span id="vm-check-details" class="float-right text-[#d2a566]">●</span></div><div>Sources <span id="vm-check-sources" class="float-right text-[#8fa1b3]">●</span></div><div>Curation <span id="vm-check-curation" class="float-right text-[#8fa1b3]">●</span></div><div>Room <span id="vm-check-room_publish" class="float-right text-[#8fa1b3]">●</span></div></div>
            <hr class="border-[#2a3a4d] my-5">
            <button id="vm-save-stage" type="button" class="vm-btn vm-primary w-full">Save and continue</button>
            <button id="vm-previous-stage" type="button" class="vm-btn vm-secondary w-full mt-2">Previous stage</button>
            <p id="vm-save-status" class="text-xs text-[#8fa1b3] text-center mt-3" aria-live="polite"></p>
        </aside>
    </main>
<?php endif; ?>
</div>

<script>
(() => {
const AJAX = 'ajax.php';
const BASE = <?= json_encode($baseUrl) ?>;
const CSRF = <?= json_encode($csrf) ?>;
const config = <?= json_encode([
    'id' => (int) ($exhibition['id'] ?? 0),
    'slug' => $exhibition['slug'] ?? '',
    'theme' => $exhibition['theme'] ?? 'classic',
    'layout' => $exhibition['layout'] ?? 'grand_hall',
    'centerpiece' => in_array($exhibition['centerpiece'] ?? '', VirtualMuseumModule::CENTERPIECES, true) ? $exhibition['centerpiece'] : 'auto',
    'sources' => array_map(static fn($s) => ['type'=>$s['source_type'],'id'=>(int)$s['source_id'],'sort_order'=>(int)$s['sort_order']], $sources),
    'overrides' => $overrides,
    'resolved' => $resolved,
    'sourceOptions' => $sourceOptions,
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const $ = id => document.getElementById(id);
const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const post = async body => { body.set('csrf_token', CSRF); const r = await fetch(AJAX,{method:'POST',body,headers:{'X-CSRF-Token':CSRF}}); const d = await r.json(); if(!d.success) throw new Error(d.error || 'Request failed.'); return d; };

document.querySelectorAll('.vm-delete').forEach(btn => btn.addEventListener('click', async () => {
    if(!confirm(`Delete exhibition “${btn.dataset.title}”? This cannot be undone.`)) return;
    const b=new URLSearchParams({action:'museum_delete',id:btn.dataset.id}); try{await post(b);location.reload();}catch(e){alert(e.message);}
}));
document.querySelectorAll('.vm-duplicate').forEach(btn => btn.addEventListener('click', async () => {
    btn.disabled=true; const b=new URLSearchParams({action:'museum_duplicate',id:btn.dataset.id}); try{const d=await post(b);location.href=`${BASE}&action=edit&id=${d.id}`;}catch(e){alert(e.message);btn.disabled=false;}
}));
if (!$('vm-save-stage')) return;

let exhibitionId=config.id, dirty=false;
let sources=[...config.sources];
const overrides=new Map(Object.entries(config.overrides).map(([id,row])=>[Number(id),{selection_state:row.selection_state,sort_order:row.sort_order}]));
let order=config.resolved.map(item=>Number(item.id));
const itemById=new Map(config.resolved.map(item=>[Number(item.id),item]));
let theme=config.theme, layout=config.layout, centerpiece=config.centerpiece, active='details', searchPage=1, searchTerm='';
const steps=['details','sources','curation','room_publish'];

function setDirty(){dirty=true;$('vm-save-status').textContent='Unsaved changes';}
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
document.querySelectorAll('input,textarea').forEach(el=>el.addEventListener('input',setDirty));

function showStep(step){active=steps.includes(step)?step:'details';document.querySelectorAll('.vm-stage').forEach(el=>el.classList.toggle('is-active',el.dataset.step===active));document.querySelectorAll('.vm-step').forEach(el=>el.setAttribute('aria-current',el.dataset.step===active?'step':'false'));$('vm-previous-stage').classList.toggle('invisible',active==='details');$('vm-save-stage').textContent=active==='room_publish'?'Save exhibition':'Save and continue';history.replaceState(null,'',`${BASE}&action=${exhibitionId?'edit':'new'}${exhibitionId?`&id=${exhibitionId}`:''}&step=${active}`);steps.forEach((s,i)=>{const dot=$('vm-check-'+s);if(dot)dot.className=steps.indexOf(active)>=i?'float-right text-[#d2a566]':'float-right text-[#8fa1b3]';});}
document.querySelectorAll('.vm-step').forEach(btn=>btn.addEventListener('click',()=>showStep(btn.dataset.step)));
$('vm-previous-stage').addEventListener('click',()=>showStep(steps[Math.max(0,steps.indexOf(active)-1)]));

function sourceName(source){return (config.sourceOptions[source.type]||[]).find(x=>Number(x.id)===Number(source.id))?.name || `#${source.id}`;}
function renderSources(){
    $('vm-selected-sources').innerHTML=sources.length?sources.map((s,i)=>`<span class="vm-source-chip"><span class="uppercase text-[10px] text-[#d2a566]">${esc(s.type)}</span>${esc(sourceName(s))}<button type="button" data-source-remove="${i}" class="w-6 h-6" aria-label="Remove ${esc(sourceName(s))}">×</button></span>`).join(''):'<span class="text-sm text-[#8fa1b3]">No live sources selected. You can still hand-pick items in the next stage.</span>';
    document.querySelectorAll('[data-source-remove]').forEach(btn=>btn.addEventListener('click',()=>{sources.splice(Number(btn.dataset.sourceRemove),1);sources.forEach((s,i)=>s.sort_order=i);setDirty();renderSources();renderSourceResults();}));
}
function renderSourceResults(){const q=$('vm-source-search').value.trim().toLowerCase();const selected=new Set(sources.map(s=>`${s.type}:${s.id}`));let rows=[];Object.entries(config.sourceOptions).forEach(([type,opts])=>opts.forEach(o=>{if(!selected.has(`${type}:${o.id}`)&&(!q||o.name.toLowerCase().includes(q)))rows.push({type,...o});}));$('vm-source-results').innerHTML=rows.slice(0,60).map(r=>`<button type="button" class="vm-action text-left rounded-xl border border-[#34485e] bg-[#111c28] p-4 hover:border-[#d2a566]" data-source-add="${r.type}:${r.id}"><span class="uppercase text-[10px] text-[#d2a566] font-bold">${esc(r.type)}</span><strong class="block mt-1">${esc(r.name)}</strong></button>`).join('')||'<p class="text-sm text-[#8fa1b3]">No matching sources.</p>';document.querySelectorAll('[data-source-add]').forEach(btn=>btn.addEventListener('click',()=>{const [type,id]=btn.dataset.sourceAdd.split(':');sources.push({type,id:Number(id),sort_order:sources.length});setDirty();renderSources();renderSourceResults();}));}
$('vm-source-search').addEventListener('input',renderSourceResults);

function normalizeOrder(){order.forEach((id,i)=>overrides.set(id,{selection_state:'include',sort_order:i}));}
function moveItem(id,delta){const at=order.indexOf(id),to=Math.max(0,Math.min(order.length-1,at+delta));if(at<0||at===to)return;order.splice(at,1);order.splice(to,0,id);normalizeOrder();setDirty();renderCurated();}
function excludeItem(id){order=order.filter(x=>x!==id);overrides.set(id,{selection_state:'exclude',sort_order:null});setDirty();renderCurated();}
function renderCurated(){
    $('vm-resolved-count').textContent=order.length;$('vm-excluded-count').textContent=[...overrides.values()].filter(v=>v.selection_state==='exclude').length;
    $('vm-curated-grid').innerHTML=order.map((id,i)=>{const item=itemById.get(id)||{id,title:`Item #${id}`,thumb:''};return `<article draggable="true" data-curated-id="${id}" class="vm-item-card rounded-xl border border-[#34485e] bg-[#111c28] p-3 flex gap-3 items-center"><span class="text-xs text-[#8fa1b3] w-6">${i+1}</span><img src="${esc(item.thumb)}" alt="" class="w-16 h-16 rounded-lg bg-[#0b1119] object-cover"><div class="min-w-0 flex-1"><strong class="text-sm line-clamp-2">${esc(item.title)}</strong><span class="text-xs text-[#8fa1b3]">${esc(item.reg||'')}</span></div><div class="grid gap-1"><button type="button" class="vm-action w-9 h-9 rounded-lg bg-white/5" data-move="-1" aria-label="Move ${esc(item.title)} up">↑</button><button type="button" class="vm-action w-9 h-9 rounded-lg bg-white/5" data-move="1" aria-label="Move ${esc(item.title)} down">↓</button><button type="button" class="vm-action w-9 h-9 rounded-lg bg-rose-500/10 text-rose-300" data-exclude aria-label="Remove ${esc(item.title)}">×</button></div></article>`}).join('')||'<div class="rounded-xl border border-dashed border-[#40556b] p-10 text-center text-[#8fa1b3]">Save sources or hand-pick items to build the sequence.</div>';
    document.querySelectorAll('[data-curated-id]').forEach(card=>{const id=Number(card.dataset.curatedId);card.querySelectorAll('[data-move]').forEach(b=>b.addEventListener('click',()=>moveItem(id,Number(b.dataset.move))));card.querySelector('[data-exclude]').addEventListener('click',()=>excludeItem(id));card.addEventListener('dragstart',()=>{card.setAttribute('aria-grabbed','true');card.dataset.dragging='1';});card.addEventListener('dragend',()=>{card.setAttribute('aria-grabbed','false');delete card.dataset.dragging;});card.addEventListener('dragover',e=>e.preventDefault());card.addEventListener('drop',e=>{e.preventDefault();const from=document.querySelector('[data-dragging="1"]');if(!from)return;const fromId=Number(from.dataset.curatedId),fromAt=order.indexOf(fromId),toAt=order.indexOf(id);order.splice(fromAt,1);order.splice(toAt,0,fromId);normalizeOrder();setDirty();renderCurated();});});
}
async function searchItems(reset=true){if(reset){searchPage=1;$('vm-item-results').innerHTML='';}const params=new URLSearchParams({action:'museum_item_search',q:searchTerm,page:String(searchPage)});const r=await fetch(`${AJAX}?${params}`);const d=await r.json();if(!d.success)throw new Error(d.error);$('vm-item-results').insertAdjacentHTML('beforeend',d.items.map(item=>`<button type="button" class="vm-action w-full flex gap-3 items-center text-left rounded-lg border border-[#34485e] p-2 hover:border-[#d2a566]" data-add-item="${item.id}"><img src="${esc(item.thumb)}" alt="" class="w-12 h-12 rounded object-cover"><span class="min-w-0"><strong class="text-xs line-clamp-2">${esc(item.title)}</strong><small class="text-[#8fa1b3]">${esc(item.reg||'')}</small></span></button>`).join(''));d.items.forEach(item=>itemById.set(Number(item.id),item));document.querySelectorAll('[data-add-item]').forEach(btn=>btn.onclick=()=>{const id=Number(btn.dataset.addItem);if(!order.includes(id))order.push(id);normalizeOrder();setDirty();renderCurated();});$('vm-load-more').classList.toggle('hidden',!d.has_more);}
let timer;$('vm-item-search').addEventListener('input',e=>{clearTimeout(timer);searchTerm=e.target.value.trim();timer=setTimeout(()=>searchItems(true).catch(err=>$('vm-error').textContent=err.message),250);});$('vm-load-more').addEventListener('click',()=>{searchPage++;searchItems(false);});

function selectChoice(kind,value){if(kind==='theme')theme=value;else if(kind==='layout')layout=value;else centerpiece=value;document.querySelectorAll(`[data-${kind}]`).forEach(el=>el.setAttribute('aria-checked',el.dataset[kind]===value?'true':'false'));setDirty();updateWarning();}
document.querySelectorAll('[data-theme]').forEach(el=>el.addEventListener('click',()=>selectChoice('theme',el.dataset.theme)));
document.querySelectorAll('[data-layout]').forEach(el=>el.addEventListener('click',()=>selectChoice('layout',el.dataset.layout)));
document.querySelectorAll('[data-centerpiece]').forEach(el=>el.addEventListener('click',()=>selectChoice('centerpiece',el.dataset.centerpiece)));
function updateWarning(){const min={grand_hall:1,l_gallery:12,courtyard_loop:24}[layout]||1;const el=$('vm-layout-warning');el.classList.toggle('hidden',order.length>=min);el.textContent=order.length<min?`This layout is recommended for ${min}+ items. You can still save or publish it.`:'';}

$('vm-save-stage').addEventListener('click',async()=>{const btn=$('vm-save-stage'),error=$('vm-error');error.classList.add('hidden');btn.disabled=true;btn.textContent='Saving…';$('vm-save-status').textContent='Saving changes';try{const b=new URLSearchParams({action:'museum_save',stage:active,id:String(exhibitionId)});if(active==='details'){b.set('title',$('vm-title').value.trim());b.set('description',$('vm-description').value.trim());b.set('item_limit',$('vm-item-limit').value);}else if(active==='sources'){b.set('sources_json',JSON.stringify(sources));}else if(active==='curation'){normalizeOrder();b.set('items_json',JSON.stringify([...overrides].map(([item_id,row])=>({item_id,...row}))));}else{b.set('theme',theme);b.set('layout',layout);b.set('centerpiece',centerpiece);if($('vm-published').checked)b.set('is_published','1');}const d=await post(b);dirty=false;$('vm-save-status').textContent='Saved';if(active==='details'&&!exhibitionId){exhibitionId=d.id;location.href=`${BASE}&action=edit&id=${d.id}&step=sources`;return;}const next=steps[steps.indexOf(active)+1];if(next){if(active==='sources'||active==='curation'){location.href=`${BASE}&action=edit&id=${exhibitionId}&step=${next}`;return;}showStep(next);}}catch(e){error.textContent=e.message;error.classList.remove('hidden');error.focus();}finally{btn.disabled=false;btn.textContent=active==='room_publish'?'Save exhibition':'Save and continue';}});

renderSources();renderSourceResults();renderCurated();searchItems(true);selectChoice('theme',theme);selectChoice('layout',layout);selectChoice('centerpiece',centerpiece);dirty=false;showStep(new URLSearchParams(location.search).get('step')||'details');
})();
</script>
