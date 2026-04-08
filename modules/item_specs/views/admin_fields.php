<?php
// modules/item_specs/views/admin_fields.php
/** @var array $specs Existing specs for the item */
/** @var array $mapping Category ID -> Profile name mapping */
/** @var int $id Item ID */
/** @var array $item Item data if editing */

// Group categories by profile for JS efficiency
$profileMap = [];
foreach ($mapping as $catId => $profile) {
    $profileMap[$profile][] = (string)$catId;
}
?>

<div class="mt-8 border-t border-gray-200 pt-8">
    <div class="bg-slate-50 rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 bg-white border-b border-slate-200 flex items-center gap-3">
            <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Technical Specifications</h3>
                <p class="text-[10px] text-slate-500 font-medium">MODULAR ENHANCEMENT: ITEM_SPECS (CONFIGURABLE)</p>
            </div>
        </div>
        
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5" id="specs-container">
            
            <!-- Philately Group -->
            <div class="spec-group" data-profile="philately">
                <label class="label">Perforation</label>
                <input type="text" name="item_specs[Perforation]" value="<?= htmlspecialchars($specs['Perforation'] ?? '') ?>" class="input" placeholder="e.g. 14 x 14.5">
            </div>
            
            <div class="spec-group" data-profile="philately,banknotes"> 
                <label class="label">Watermark</label>
                <input type="text" name="item_specs[Watermark]" value="<?= htmlspecialchars($specs['Watermark'] ?? '') ?>" class="input" placeholder="e.g. Multiple Crowns">
            </div>

            <!-- Numismatics Group -->
            <div class="spec-group" data-profile="numismatics">
                <label class="label">Weight (g)</label>
                <input type="text" name="item_specs[Weight]" value="<?= htmlspecialchars($specs['Weight'] ?? '') ?>" class="input" placeholder="e.g. 2.83">
            </div>

            <div class="spec-group" data-profile="numismatics">
                <label class="label">Diameter (mm)</label>
                <input type="text" name="item_specs[Diameter]" value="<?= htmlspecialchars($specs['Diameter'] ?? '') ?>" class="input" placeholder="e.g. 19.05">
            </div>

            <div class="spec-group" data-profile="numismatics">
                <label class="label">Mint Mark</label>
                <input type="text" name="item_specs[Mint Mark]" value="<?= htmlspecialchars($specs['Mint Mark'] ?? '') ?>" class="input" placeholder="e.g. D (Denver)">
            </div>

            <!-- Banknotes Group -->
            <div class="spec-group" data-profile="banknotes">
                <label class="label">Serial Number</label>
                <input type="text" name="item_specs[Serial Number]" value="<?= htmlspecialchars($specs['Serial Number'] ?? '') ?>" class="input" placeholder="e.g. A/1 123456">
            </div>

            <!-- Postcard / History Group -->
            <div class="spec-group" data-profile="postcard">
                <label class="label">Postmark / Cancellation</label>
                <input type="text" name="item_specs[Postmark]" value="<?= htmlspecialchars($specs['Postmark'] ?? '') ?>" class="input" placeholder="e.g. Bombay G.P.O.">
            </div>

            <div class="spec-group" data-profile="postcard">
                <label class="label">Publisher / series</label>
                <input type="text" name="item_specs[Publisher]" value="<?= htmlspecialchars($specs['Publisher'] ?? '') ?>" class="input" placeholder="e.g. Raphael Tuck & Sons">
            </div>

            <!-- Common -->
            <div class="spec-group"> <!-- Always visible -->
                <label class="label">Catalog Reference</label>
                <input type="text" name="item_specs[Catalog Ref]" value="<?= htmlspecialchars($specs['Catalog Ref'] ?? '') ?>" class="input" placeholder="e.g. Scott #123, SC #55">
            </div>
        </div>
        <div class="px-5 py-3 bg-slate-100/50 border-t border-slate-200">
            <p class="text-[10px] text-slate-400 italic">Configure category-to-profile mappings in the module settings. Fields update dynamically.</p>
        </div>
    </div>
</div>

<script>
(function() {
    const categorySelect = document.getElementById('category-select');
    if (!categorySelect) return;

    // Mapping from profile name to array of category IDs
    const profileToCats = <?= json_encode($profileMap) ?>;

    function refreshSpecVisibility() {
        const selectedCats = Array.from(categorySelect.selectedOptions).map(opt => opt.value);
        
        document.querySelectorAll('.spec-group').forEach(group => {
            const profilesStr = group.getAttribute('data-profile');
            if (!profilesStr) return; // Always show if no profile required
            
            const profiles = profilesStr.split(',');
            let isVisible = false;

            profiles.forEach(p => {
                const allowedCats = profileToCats[p] || [];
                if (allowedCats.some(id => selectedCats.includes(id))) {
                    isVisible = true;
                }
            });
            
            if (isVisible) {
                group.classList.remove('hidden');
            } else {
                group.classList.add('hidden');
            }
        });
    }

    categorySelect.addEventListener('change', refreshSpecVisibility);
    // Initial run
    setTimeout(refreshSpecVisibility, 100);
})();
</script>
