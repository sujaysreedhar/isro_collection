<!-- modules/storage_labels/views/admin_fields.php -->
<div class="mt-6 pt-6 border-t border-gray-200">
    <div class="flex items-center gap-2 mb-3">
        <h4 class="font-semibold text-gray-800">Physical Storage Location</h4>
        <span class="bg-indigo-100 text-indigo-700 text-[10px] uppercase px-1.5 py-0.5 rounded font-bold">Module</span>
    </div>
    <div class="bg-indigo-50/50 rounded-xl p-5 border border-indigo-100/50">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Album / Binder</label>
                <input type="text" name="storage_labels[album]" value="<?= htmlspecialchars($storage['album'] ?? '') ?>" placeholder="e.g. Blue Album #1" class="w-full px-3 py-2 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none bg-white text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Page Number</label>
                <input type="text" name="storage_labels[page_number]" value="<?= htmlspecialchars($storage['page_number'] ?? '') ?>" placeholder="e.g. 42" class="w-full px-3 py-2 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none bg-white text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Box / Drawer</label>
                <input type="text" name="storage_labels[box_id]" value="<?= htmlspecialchars($storage['box_id'] ?? '') ?>" placeholder="e.g. Safe Box A" class="w-full px-3 py-2 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none bg-white text-sm">
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Specific Location Notes</label>
            <textarea name="storage_labels[location_notes]" rows="2" placeholder="e.g. Third row from top, left corner mount" class="w-full px-3 py-2 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none bg-white text-sm"><?= htmlspecialchars($storage['location_notes'] ?? '') ?></textarea>
        </div>
    </div>
</div>
