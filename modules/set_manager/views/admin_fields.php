<!-- modules/set_manager/views/admin_fields.php -->
<?php
/**
 * @var array $sets
 * @var array|null $setDetails
 */
$currentSetId = $setDetails['set_id'] ?? 0;
$currentStructId = $setDetails['structure_id'] ?? 0;
?>
<div class="mt-8 pt-8 border-t border-slate-200">
    <div class="flex items-center gap-3 mb-5">
        <div class="w-8 h-8 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
        </div>
        <div>
            <h4 class="font-bold text-slate-800 leading-tight">Collection Set / Checklist</h4>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Module: Set Manager</p>
        </div>
    </div>

    <div class="bg-slate-50 rounded-[32px] p-8 border border-slate-200 shadow-inner group">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Set Selection -->
            <div>
                <label class="block text-xs font-black uppercase text-slate-400 mb-2 ml-1">Assign to Set</label>
                <select name="set_manager_id" id="set_manager_id" onchange="loadSetStructure(this.value)" class="w-full px-5 py-3 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition font-semibold text-slate-700">
                    <option value="0">-- Not part of any set --</option>
                    <?php foreach ($sets as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $s['id'] == $currentSetId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Structure Requirement Selection -->
            <div id="structure_picker_container" class="<?= $currentSetId > 0 ? '' : 'opacity-40 pointer-events-none' ?> transition-opacity duration-300">
                <label class="block text-xs font-black uppercase text-slate-400 mb-2 ml-1">Checklist Requirement</label>
                <select name="set_manager_structure_id" id="set_manager_structure_id" class="w-full px-5 py-3 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition font-semibold text-slate-700">
                    <option value="0">-- General (unmapped) --</option>
                    <!-- Options loaded via JS -->
                </select>
            </div>
        </div>
        <p class="mt-4 text-[11px] text-slate-400 italic px-2">
            Assigning an item to a checklist requirement helps track set completion accurately.
        </p>
    </div>
</div>

<script>
async function loadSetStructure(setId) {
    const picker = document.getElementById('set_manager_structure_id');
    const container = document.getElementById('structure_picker_container');
    
    // Clear current options
    picker.innerHTML = '<option value="0">-- General (unmapped) --</option>';
    
    if (setId == 0) {
        container.classList.add('opacity-40', 'pointer-events-none');
        return;
    }
    
    container.classList.remove('opacity-40', 'pointer-events-none');
    
    try {
        const response = await fetch(`ajax.php?action=get_set_structure&set_id=${setId}`);
        const data = await response.json();
        
        if (data.success && data.structure.length > 0) {
            data.structure.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = item.label;
                if (item.id == <?= (int)$currentStructId ?>) opt.selected = true;
                picker.appendChild(opt);
            });
        }
    } catch (e) {
        console.error("Failed to load set structure", e);
    }
}

// Initial load if a set is already selected
if (document.getElementById('set_manager_id').value > 0) {
    loadSetStructure(document.getElementById('set_manager_id').value);
}
</script>
