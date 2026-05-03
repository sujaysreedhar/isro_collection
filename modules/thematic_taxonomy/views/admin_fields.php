<?php
/** @var array $themeOptions */
/** @var array $selectedThemeIds */
?>

<div class="mt-8 border-t border-gray-200 pt-8">
    <div class="bg-sky-50/40 rounded-xl border border-sky-100 overflow-hidden shadow-sm">
        <div class="px-5 py-4 bg-white border-b border-sky-100 flex items-center gap-3">
            <div class="p-2 bg-sky-50 text-sky-600 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-sky-900 uppercase tracking-wider">Thematic Taxonomy</h3>
                <p class="text-[10px] text-sky-500 font-medium">MODULAR ENHANCEMENT: THEMATIC_TAXONOMY</p>
            </div>
        </div>

        <div class="p-5">
            <input type="hidden" name="thematic_taxonomy_present" value="1">

            <?php if ($themeOptions): ?>
                <label class="label">
                    Assign Subjects
                    <span class="text-gray-400 font-normal">(multiple allowed)</span>
                </label>
                <select id="thematic-taxonomy-select" name="thematic_taxonomy_theme_ids[]" multiple class="w-full">
                    <?php foreach ($themeOptions as $option): ?>
                        <option value="<?= (int)$option['id'] ?>" <?= in_array((int)$option['id'], $selectedThemeIds, true) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($option['trail_label'] ?? str_repeat('-- ', (int)$option['depth']) . $option['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-3 text-xs text-slate-500">
                    Use curated subjects for topics like Space, Gandhi, Birds, Postal History, or Republic of India.
                </p>
            <?php else: ?>
                <div class="rounded-xl border border-dashed border-sky-200 bg-white px-4 py-5 text-sm text-slate-500">
                    No subjects exist yet. Create some in <a href="<?= SITE_URL ?>/admin/module_page.php?m=thematic_taxonomy" class="font-bold text-sky-700 hover:text-sky-900">Thematic Taxonomy</a> and they will appear here.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($themeOptions): ?>
<script>
    (function () {
        const select = document.getElementById('thematic-taxonomy-select');
        if (!select || select.tomselect) {
            return;
        }

        new TomSelect(select, {
            plugins: ['remove_button'],
            placeholder: 'Select one or more subjects...',
            maxOptions: 500
        });
    })();
</script>
<?php endif; ?>
