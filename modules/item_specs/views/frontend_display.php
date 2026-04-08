<?php
// modules/item_specs/views/frontend_display.php
/** @var array $specs Existing specs for the item */
?>

<div class="mt-12 border-t border-slate-200 pt-8">
    <div class="flex items-center gap-3 mb-6">
        <div class="p-2 bg-slate-900 text-white rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-slate-900 serif">Technical Specifications</h2>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($specs as $label => $value): ?>
            <?php if (trim((string)$value) !== ''): ?>
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 hover:bg-white hover:shadow-md transition-all group">
                    <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1 group-hover:text-blue-500 transition-colors"><?= htmlspecialchars($label) ?></dt>
                    <dd class="text-lg font-bold text-slate-900 serif"><?= htmlspecialchars($value) ?></dd>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
