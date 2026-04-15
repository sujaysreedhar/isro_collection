<!-- modules/set_manager/views/frontend_display.php -->
<div class="mt-8 p-6 bg-white rounded-2xl border border-gray-100 shadow-sm transition-all hover:shadow-md">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
        <div>
            <h3 class="text-lg font-bold text-gray-900 mb-0.5">Part of Dataset: <?= htmlspecialchars($set['name']) ?></h3>
            <p class="text-sm text-gray-500"><?= htmlspecialchars($set['description']) ?></p>
        </div>
        <div class="text-right flex-shrink-0">
            <span class="text-2xl font-black text-blue-600"><?= $progress['percent'] ?>%</span>
            <span class="block text-[10px] uppercase tracking-wider font-bold text-gray-400">Total Progress</span>
        </div>
    </div>
    
    <div class="relative w-full h-3 bg-gray-100 rounded-full overflow-hidden">
        <!-- Progress fill with shimmer effect -->
        <div class="absolute inset-y-0 left-0 bg-blue-600 rounded-full transition-all duration-1000 ease-out" style="width: <?= $progress['percent'] ?>%">
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent animate-shimmer" style="background-size: 200% 100%;"></div>
        </div>
    </div>
    
    <div class="mt-4 flex justify-between items-center">
        <div class="flex -space-x-2 overflow-hidden">
            <!-- Simulated avatar icons for "collector community" feel if desired, or just stats -->
            <span class="text-xs font-semibold text-gray-600">Currently tracking <?= $progress['count'] ?> of <?= $progress['target'] ?> items</span>
        </div>
        <a href="#" class="text-xs font-bold text-blue-600 hover:text-blue-700 transition flex items-center gap-1">
            View Full Checklist 
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </a>
    </div>
</div>

<style>
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.animate-shimmer {
    animation: shimmer 2s infinite;
}
</style>
