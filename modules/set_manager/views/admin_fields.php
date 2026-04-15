<!-- modules/set_manager/views/admin_fields.php -->
<div class="mt-6 pt-6 border-t border-gray-200">
    <div class="flex items-center gap-2 mb-3">
        <h4 class="font-semibold text-gray-800">Collection Set / Checklist</h4>
        <span class="bg-blue-100 text-blue-700 text-[10px] uppercase px-1.5 py-0.5 rounded font-bold">Module</span>
    </div>
    <div class="bg-blue-50/50 rounded-xl p-5 border border-blue-100/50">
        <label class="block text-sm font-medium text-gray-700 mb-2">Assign to Set</label>
        <select name="set_manager_id" class="w-full px-4 py-2 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
            <option value="0">-- Not part of any set --</option>
            <?php foreach ($sets as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id'] == $itemSetId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name']) ?> 
                    (<?= $this->getSetProgress($s['id'])['percent'] ?>% complete)
                </option>
            <?php endforeach; ?>
        </select>
        <p class="mt-2 text-xs text-gray-500 italic">Sets help group items and show completion progress on the frontend.</p>
    </div>
</div>
