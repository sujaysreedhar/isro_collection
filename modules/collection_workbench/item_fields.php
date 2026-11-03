<section class="rounded-lg border border-slate-200 bg-slate-50 p-5 space-y-4">
    <div><h3 class="font-semibold text-slate-900">Private storage details</h3><p class="text-sm text-slate-600 mt-1">For administrators only. Manage album, page, box, purchase price and valuation in the Collection Workbench.</p></div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium mb-1" for="cw-sleeve">Sleeve</label><input id="cw-sleeve" name="collection_private[sleeve]" maxlength="100" value="<?= htmlspecialchars($details['sleeve'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2"></div>
        <div><label class="block text-sm font-medium mb-1" for="cw-shelf">Shelf</label><input id="cw-shelf" name="collection_private[shelf]" maxlength="100" value="<?= htmlspecialchars($details['shelf'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2"></div>
    </div>
    <div><label class="block text-sm font-medium mb-1" for="cw-private-notes">Private notes</label><textarea id="cw-private-notes" name="collection_private[private_notes]" rows="3" maxlength="20000" class="w-full rounded-md border border-slate-300 px-3 py-2"><?= htmlspecialchars($details['private_notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></div>
    <a href="<?= SITE_URL ?>/admin/module_page.php?m=collection_workbench<?= !empty($id) ? '&amp;item=' . (int)$id : '' ?>" class="text-sm font-medium text-blue-700 underline">Open Collection Workbench</a>
</section>
