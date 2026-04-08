<?php
// modules/valuation_tracker/views/admin_fields.php
/** @var array $valuation Existing valuation data */
?>

<div class="mt-8 border-t border-gray-200 pt-8">
    <div class="bg-amber-50/30 rounded-xl border border-amber-100 overflow-hidden shadow-sm">
        <div class="px-5 py-4 bg-white border-b border-amber-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-amber-900 uppercase tracking-wider">Financial records</h3>
                    <p class="text-[10px] text-amber-500 font-medium">MODULAR ENHANCEMENT: VALUATION_TRACKER</p>
                </div>
            </div>
            <span class="text-[10px] font-bold py-1 px-2 bg-amber-100 text-amber-700 rounded-full border border-amber-200 uppercase tracking-widest">Confidential / Admin Only</span>
        </div>
        
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            <div>
                <label class="label">Purchase Price</label>
                <div class="relative">
                    <input type="number" step="0.01" name="item_valuation[purchase_price]" value="<?= htmlspecialchars($valuation['purchase_price'] ?? '') ?>" class="input" placeholder="0.00">
                </div>
            </div>

            <div>
                <label class="label">Current Estimate</label>
                <input type="number" step="0.01" name="item_valuation[current_value]" value="<?= htmlspecialchars($valuation['current_value'] ?? '') ?>" class="input" placeholder="0.00">
            </div>

            <div>
                <label class="label">Currency</label>
                <select name="item_valuation[currency]" class="input">
                    <option value="INR" <?= ($valuation['currency'] ?? 'INR') === 'INR' ? 'selected' : '' ?>>₹ INR</option>
                    <option value="USD" <?= ($valuation['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>$ USD</option>
                    <option value="GBP" <?= ($valuation['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>£ GBP</option>
                    <option value="EUR" <?= ($valuation['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>€ EUR</option>
                </select>
            </div>

            <div>
                <label class="label">Acquisition Date</label>
                <input type="date" name="item_valuation[purchase_date]" value="<?= htmlspecialchars($valuation['purchase_date'] ?? '') ?>" class="input">
            </div>
        </div>
    </div>
</div>
