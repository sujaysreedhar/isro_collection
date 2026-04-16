<!-- modules/set_manager/views/frontend_display.php -->
<?php
/**
 * @var array $set
 * @var array $progress
 */
?>
<div class="mt-12 group">
    <div class="relative bg-white rounded-[32px] p-8 border border-slate-200 shadow-sm hover:shadow-xl transition-all duration-500 overflow-hidden">
        <!-- Abstract Background -->
        <div class="absolute top-0 right-0 -mr-12 -mt-12 w-32 h-32 bg-blue-50 rounded-full blur-3xl group-hover:bg-blue-100 transition-colors duration-500"></div>
        
        <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-8 mb-8">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-3">
                    <span class="bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-lg shadow-lg shadow-blue-200">Collection Set</span>
                    <?php if ($set['is_featured']): ?>
                        <span class="bg-amber-100 text-amber-700 text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-lg">Featured</span>
                    <?php endif; ?>
                </div>
                <h3 class="text-2xl font-black text-slate-900 group-hover:text-blue-600 transition-colors"><?= htmlspecialchars($set['name']) ?></h3>
                <p class="text-slate-500 text-sm mt-2 leading-relaxed max-w-xl"><?= htmlspecialchars(mb_strimwidth($set['description'], 0, 150, '...')) ?></p>
            </div>
            
            <div class="flex flex-col items-end flex-shrink-0 bg-slate-50 p-6 rounded-3xl border border-slate-100">
                <div class="text-4xl font-black text-slate-900 mb-1"><?= $progress['percent'] ?><span class="text-blue-600">%</span></div>
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Completion</div>
            </div>
        </div>
        
        <!-- Progress System -->
        <div class="space-y-3">
            <div class="flex justify-between text-[11px] font-black uppercase tracking-widest text-slate-400 px-1">
                <span>Owned: <?= $progress['count'] ?> Items</span>
                <span>Target: <?= $progress['target'] ?> Items</span>
            </div>
            <div class="relative w-full h-4 bg-slate-100 rounded-full overflow-hidden p-1 border border-slate-200/50 shadow-inner">
                <div class="h-full bg-gradient-to-r from-blue-600 to-indigo-600 rounded-full transition-all duration-1000 ease-out shadow-[0_0_12px_rgba(37,99,235,0.4)]" style="width: <?= $progress['percent'] ?>%">
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent animate-shimmer" style="background-size: 200% 100%;"></div>
                </div>
            </div>
        </div>
        
        <!-- Action Area -->
        <div class="mt-8 pt-8 border-t border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="flex -space-x-2">
                    <div class="w-8 h-8 rounded-full border-2 border-white bg-slate-200 flex items-center justify-center text-[10px] font-bold text-slate-500">JP</div>
                    <div class="w-8 h-8 rounded-full border-2 border-white bg-blue-100 flex items-center justify-center text-[10px] font-bold text-blue-600">CC</div>
                </div>
                <span class="text-xs font-bold text-slate-400">Join 12+ other collectors tracking this set</span>
            </div>
            <a href="<?= SITE_URL ?>/checklist/<?= $set['slug'] ?>" class="bg-slate-900 hover:bg-blue-600 text-white px-6 py-3 rounded-2xl text-xs font-black uppercase tracking-widest transition-all shadow-xl shadow-slate-200 hover:shadow-blue-200 active:scale-95">
                Full Checklist
            </a>
        </div>
    </div>
</div>

<style>
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.animate-shimmer {
    animation: shimmer 2.5s infinite linear;
}
</style>
