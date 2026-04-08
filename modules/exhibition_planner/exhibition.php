<?php
// modules/exhibition_planner/exhibition.php
global $pdo, $storage;

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM module_exhibition_pages WHERE slug = ?");
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    die("Exhibition not found.");
}

$items = $pdo->prepare("
    SELECT i.*, ei.annotation 
    FROM module_exhibition_items ei
    JOIN items i ON ei.item_id = i.id
    WHERE ei.page_id = ?
    ORDER BY ei.sort_order ASC
");
$items->execute([$page['id']]);
$exhibitionItems = $items->fetchAll();

$pageTitle = $page['title'];
require_once ThemeManager::getHeader();
?>

<div class="bg-slate-950 py-20 border-b border-slate-900">
    <div class="max-w-5xl mx-auto px-4 text-center">
        <a href="exhibitions.php" class="text-sm font-bold text-blue-500 uppercase tracking-widest hover:text-blue-400 mb-6 inline-block">← All Exhibitions</a>
        <h1 class="text-5xl md:text-7xl font-black text-white serif mb-8 tracking-tight"><?= htmlspecialchars($page['title']) ?></h1>
        <?php if ($page['description']): ?>
            <div class="max-w-3xl mx-auto text-xl text-slate-400 leading-relaxed font-light serif italic">
                <?= nl2br(htmlspecialchars($page['description'])) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 py-20">
    <div class="space-y-32">
        <?php foreach ($exhibitionItems as $index => $item): 
            $media = $pdo->prepare("SELECT file_path FROM media WHERE item_id = ? LIMIT 1");
            $media->execute([$item['id']]);
            $primaryMedia = $media->fetchColumn();
            
            $isEven = $index % 2 === 0;
        ?>
            <div class="flex flex-col <?= $isEven ? 'lg:flex-row' : 'lg:flex-row-reverse' ?> gap-12 lg:gap-24 items-center">
                <!-- Visual -->
                <div class="flex-1 w-full">
                    <div class="relative group">
                        <div class="absolute -inset-4 bg-gradient-to-tr from-slate-200 to-white rounded-[3rem] -z-10 opacity-50"></div>
                        <div class="rounded-[2.5rem] overflow-hidden shadow-2xl shadow-slate-300">
                             <?php if ($primaryMedia): ?>
                                <img src="<?= MediaProcessor::url($primaryMedia, 'display', 'image', $storage) ?>" 
                                     class="w-full aspect-[4/5] object-cover group-hover:scale-105 transition-transform duration-700" 
                                     alt="<?= htmlspecialchars($item['title']) ?>">
                             <?php else: ?>
                                <div class="w-full aspect-[4/5] bg-slate-100 flex items-center justify-center">
                                    <span class="text-slate-300 font-bold tracking-widest uppercase">No Image Available</span>
                                </div>
                             <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="flex-1 space-y-8">
                    <div class="space-y-4">
                        <span class="text-xs font-bold text-blue-600 uppercase tracking-[0.3em] bg-blue-50 px-3 py-1 rounded-full">Artifact <?= $index + 1 ?></span>
                        <h2 class="text-4xl md:text-5xl font-black text-slate-900 serif leading-tight tracking-tight"><?= htmlspecialchars($item['title']) ?></h2>
                    </div>

                    <div class="text-lg text-slate-600 leading-relaxed space-y-4">
                        <?php if ($item['annotation']): ?>
                            <div class="p-6 bg-slate-50 border-l-4 border-slate-900 italic font-serif text-slate-700">
                                "<?= nl2br(htmlspecialchars($item['annotation'])) ?>"
                            </div>
                        <?php endif; ?>
                        <div class="prose prose-slate prose-lg">
                            <?= $item['physical_description'] ?>
                        </div>
                    </div>

                    <div class="pt-6">
                        <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="inline-flex items-center gap-3 px-8 py-4 bg-slate-900 text-white rounded-2xl font-bold hover:bg-slate-800 transition-all shadow-xl shadow-slate-300 group">
                            View Exibit
                            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once ThemeManager::getFooter(); ?>
