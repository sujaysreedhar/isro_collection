<?php
// modules/grading_certs/views/frontend_display.php
/** @var array $grading Existing grading data */
?>

<div class="mt-8">
    <div class="inline-flex items-stretch rounded-xl overflow-hidden border border-indigo-200 shadow-sm bg-white">
        <!-- Grade Badge -->
        <div class="bg-indigo-600 px-4 py-3 flex flex-col justify-center border-r border-indigo-500">
            <span class="text-[9px] font-extrabold text-indigo-100 uppercase tracking-[0.2em] leading-none mb-1">Grade</span>
            <span class="text-xl font-black text-white leading-none"><?= htmlspecialchars($grading['grade']) ?></span>
        </div>
        
        <!-- Cert Info -->
        <div class="px-5 py-3 flex flex-col justify-center bg-indigo-50/50">
            <?php if (!empty($grading['cert_authority'])): ?>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Certified by</span>
                    <span class="text-xs font-bold text-indigo-700"><?= htmlspecialchars($grading['cert_authority']) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($grading['cert_number'])): ?>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Cert #</span>
                    <span class="text-xs font-mono font-bold text-slate-700"><?= htmlspecialchars($grading['cert_number']) ?></span>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($grading['cert_authority'])): ?>
            <!-- Verification Action -->
            <div class="px-4 flex items-center bg-white">
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
