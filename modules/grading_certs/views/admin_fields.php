<?php
// modules/grading_certs/views/admin_fields.php
/** @var array $grading Existing grading data */
?>

<div class="mt-8 border-t border-gray-200 pt-8">
    <div class="bg-indigo-50/30 rounded-xl border border-indigo-100 overflow-hidden">
        <div class="px-5 py-4 bg-white border-b border-indigo-100 flex items-center gap-3">
            <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-indigo-900 uppercase tracking-wider">Condition grading & Certification</h3>
                <p class="text-[10px] text-indigo-500 font-medium">MODULAR ENHANCEMENT: GRADING_CERTS</p>
            </div>
        </div>
        
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            <div>
                <label class="label">Condition Grade</label>
                <input type="text" name="item_grading[grade]" value="<?= htmlspecialchars($grading['grade'] ?? '') ?>" class="input" placeholder="e.g. MS-65, UNC, MNH">
            </div>

            <div>
                <label class="label">Certificate Authority</label>
                <input type="text" name="item_grading[cert_authority]" value="<?= htmlspecialchars($grading['cert_authority'] ?? '') ?>" class="input" placeholder="e.g. NGC, PCGS, Sismondo">
            </div>

            <div>
                <label class="label">Certificate #</label>
                <input type="text" name="item_grading[cert_number]" value="<?= htmlspecialchars($grading['cert_number'] ?? '') ?>" class="input" placeholder="e.g. 123456-001">
            </div>

            <div>
                <label class="label">Certification Date</label>
                <input type="date" name="item_grading[cert_date]" value="<?= htmlspecialchars($grading['cert_date'] ?? '') ?>" class="input">
            </div>
        </div>
    </div>
</div>
