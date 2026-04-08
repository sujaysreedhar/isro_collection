<?php
// modules/exhibition_planner/exhibitions.php
global $pdo, $storage;

$pages = $pdo->query("SELECT * FROM module_exhibition_pages ORDER BY created_at DESC")->fetchAll();

$pageTitle = "Virtual Exhibitions";
require_once ThemeManager::getHeader();
?>

<div class="max-w-7xl mx-auto px-4 py-12">
    <div class="text-center mb-16">
        <h1 class="text-5xl font-black text-slate-900 serif mb-4 tracking-tight">Virtual Exhibitions</h1>
        <p class="text-xl text-slate-500 max-w-2xl mx-auto leading-relaxed">Curated selections from the ISRO Archive, organized into thematic journeys and specialized studies.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
        <?php foreach ($pages as $p): ?>
            <a href="<?= SITE_URL ?>/exhibition/<?= $p['slug'] ?>" class="group">
                <div class="bg-white rounded-[2rem] overflow-hidden border border-slate-100 shadow-xl shadow-slate-200/50 hover:shadow-2xl hover:shadow-blue-200/50 transition-all duration-500 hover:-translate-y-2">
                    <div class="h-64 bg-slate-900 relative overflow-hidden">
                        <?php if ($p['banner_image']): ?>
                            <img src="<?= htmlspecialchars($p['banner_image']) ?>" class="w-full h-full object-cover opacity-80 group-hover:scale-110 transition-transform duration-700" loading="lazy">
                        <?php else: ?>
                            <div class="absolute inset-0 bg-gradient-to-br from-slate-800 to-slate-900 flex items-center justify-center p-12">
                                <svg class="w-20 h-20 text-slate-700 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                        <?php endif; ?>
                        <div class="absolute bottom-0 left-0 right-0 p-8 bg-gradient-to-t from-slate-900/80 to-transparent">
                            <span class="inline-block px-3 py-1 bg-blue-500 text-white text-[10px] font-bold uppercase tracking-widest rounded-full mb-3 shadow-lg shadow-blue-500/50">Exhibition</span>
                            <h2 class="text-2xl font-bold text-white serif leading-tight"><?= htmlspecialchars($p['title']) ?></h2>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once ThemeManager::getFooter(); ?>
