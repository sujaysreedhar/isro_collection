<?php
// modules/set_manager/admin_page.php
$action = $_GET['action'] ?? 'list';
$setId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($setId > 0) {
    $activeSet = $this->getSet($setId);
    $structure = $this->getSetStructure($setId);
}
?>

<div class="p-8 bg-[#fdfdfd] min-h-screen">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-10">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Collection <span class="text-blue-600">Checklists</span></h1>
            <p class="text-slate-500 text-sm mt-1">Manage set groupings, catalogs, and completion progress.</p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($action === 'list'): ?>
                <a href="module_page.php?m=set_manager&action=edit" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-200 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
                    New Set
                </a>
            <?php else: ?>
                <a href="module_page.php?m=set_manager" class="text-slate-500 hover:text-slate-900 font-bold px-4 py-2 border border-slate-200 rounded-xl bg-white hover:bg-slate-50 transition">
                    ← Back to List
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($action === 'list'): ?>
        <?php $sets = $this->getAllSets(); ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($sets as $s): ?>
                <?php $prog = $this->getSetProgress($s['id']); ?>
                <div class="bg-white rounded-[32px] border border-slate-200 p-6 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="flex justify-between items-start mb-6">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900 leading-tight"><?= htmlspecialchars($s['name']) ?></h3>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Checklist Set</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <?php if ($s['is_featured']): ?><span class="w-2 h-2 bg-amber-400 rounded-full" title="Featured"></span><?php endif; ?>
                            <?php if ($s['is_public']): ?><span class="w-2 h-2 bg-emerald-400 rounded-full" title="Public"></span><?php else: ?><span class="w-2 h-2 bg-rose-400 rounded-full" title="Private"></span><?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-6 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-600 rounded-full" style="width: <?= $prog['percent'] ?>%"></div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-8">
                        <div class="bg-slate-50 p-3 rounded-2xl text-center">
                            <div class="text-xl font-black text-slate-900"><?= $prog['count'] ?></div>
                            <div class="text-[10px] text-slate-400 font-bold uppercase">Owned</div>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-2xl text-center">
                            <div class="text-xl font-black text-slate-900"><?= $prog['target'] ?></div>
                            <div class="text-[10px] text-slate-400 font-bold uppercase">Target</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-slate-100 pt-5">
                        <div class="flex space-x-2">
                            <a href="module_page.php?m=set_manager&action=edit&id=<?= $s['id'] ?>" class="text-blue-600 hover:text-blue-800 font-bold text-sm bg-blue-50/50 px-4 py-2 rounded-xl transition">Edit</a>
                            <a href="<?= SITE_URL ?>/checklist/<?= $s['slug'] ?>" target="_blank" class="text-slate-600 hover:text-slate-900 font-bold text-sm bg-slate-50 px-4 py-2 rounded-xl transition">View</a>
                        </div>
                        <a href="module_page.php?m=set_manager&action=delete&id=<?= $s['id'] ?>" class="text-rose-500 hover:text-rose-700 font-bold text-sm" onclick="return confirm('Really delete?')">Delete</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <form method="POST" class="flex flex-col lg:flex-row gap-10">
            <!-- Left Side: Basic Info -->
            <div class="flex-1 space-y-8">
                <div class="bg-white p-8 rounded-[40px] border border-slate-200 shadow-sm">
                    <h2 class="text-xl font-bold text-slate-900 mb-6">Basic Information</h2>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-black uppercase text-slate-400 mb-2">Set Name</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($activeSet['name'] ?? '') ?>" required class="w-full px-5 py-3 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition font-medium">
                            </div>
                            <div>
                                <label class="block text-xs font-black uppercase text-slate-400 mb-2">URL Slug</label>
                                <input type="text" name="slug" value="<?= htmlspecialchars($activeSet['slug'] ?? '') ?>" class="w-full px-5 py-3 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition font-mono text-sm" placeholder="auto-generated-if-empty">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-slate-400 mb-2">Description</label>
                            <textarea name="description" rows="4" class="w-full px-5 py-3 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition"><?= htmlspecialchars($activeSet['description'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-slate-400 mb-2">Banner Image URL</label>
                            <input type="text" name="banner_image" value="<?= htmlspecialchars($activeSet['banner_image'] ?? '') ?>" class="w-full px-5 py-3 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition text-sm">
                        </div>
                    </div>
                </div>

                <!-- Structure Editor -->
                <div class="bg-white p-8 rounded-[40px] border border-slate-200 shadow-sm overflow-hidden">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Checklist Structure</h2>
                            <p class="text-xs text-slate-400 mt-1">Define the "expected" items for this set.</p>
                        </div>
                        <button type="button" onclick="addStructureRow()" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-bold flex items-center gap-2 hover:bg-slate-800 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
                            Add Requirement
                        </button>
                    </div>

                    <div id="structure-container" class="space-y-4">
                        <?php foreach (($structure ?: [[]]) as $index => $row): ?>
                            <div class="structure-row bg-slate-50 p-4 rounded-2xl flex flex-col md:flex-row gap-4 items-start relative group">
                                <div class="w-full md:w-1/3">
                                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Label</label>
                                    <input type="text" name="structure[<?= $index ?>][label]" value="<?= htmlspecialchars($row['label'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl outline-none focus:border-blue-500 text-sm font-bold">
                                </div>
                                <div class="flex-1 w-full">
                                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Description</label>
                                    <input type="text" name="structure[<?= $index ?>][description]" value="<?= htmlspecialchars($row['description'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl outline-none focus:border-blue-500 text-sm">
                                </div>
                                <button type="button" onclick="this.parentElement.remove()" class="mt-5 text-rose-400 hover:text-rose-600 p-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar: Settings & Save -->
            <div class="w-full lg:w-80 space-y-8">
                <div class="bg-white p-8 rounded-[40px] border border-slate-200 shadow-sm sticky top-8">
                    <h2 class="text-xl font-bold text-slate-900 mb-6">Settings</h2>
                    
                    <div class="space-y-6">
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-400 mb-3 tracking-widest">Visibility</label>
                            <div class="space-y-3">
                                <label class="flex items-center gap-3 p-3 border border-slate-100 rounded-2xl cursor-pointer hover:bg-slate-50 transition">
                                    <input type="checkbox" name="is_public" <?= ($activeSet['is_public'] ?? 1) ? 'checked' : '' ?> class="w-5 h-5 rounded-lg border-slate-300 text-blue-600 focus:ring-blue-500">
                                    <span class="text-sm font-bold text-slate-700">Publicly Visible</span>
                                </label>
                                <label class="flex items-center gap-3 p-3 border border-slate-100 rounded-2xl cursor-pointer hover:bg-slate-50 transition">
                                    <input type="checkbox" name="is_featured" <?= ($activeSet['is_featured'] ?? 0) ? 'checked' : '' ?> class="w-5 h-5 rounded-lg border-slate-300 text-amber-500 focus:ring-amber-500">
                                    <span class="text-sm font-bold text-slate-700">Featured Set</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-slate-400 mb-2">Target Count</label>
                            <input type="number" name="target_count" value="<?= (int)($activeSet['target_count'] ?? 0) ?>" class="w-full px-5 py-3 border border-slate-200 rounded-2xl outline-none font-bold">
                            <p class="text-[10px] text-slate-400 mt-2">Only used if structure is empty.</p>
                        </div>

                        <div class="pt-6 border-t border-slate-100">
                            <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black uppercase tracking-widest text-sm hover:bg-blue-700 transition shadow-xl shadow-blue-200 active:scale-95 duration-75">
                                Save Module
                            </button>
                            <a href="module_page.php?m=set_manager" class="block text-center mt-4 text-slate-400 text-xs font-bold hover:text-slate-600">Discard Changes</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <script>
            let rowIndex = <?= count($structure ?: [[]]) ?>;
            function addStructureRow() {
                const container = document.getElementById('structure-container');
                const div = document.createElement('div');
                div.className = 'structure-row bg-slate-50 p-4 rounded-2xl flex flex-col md:flex-row gap-4 items-start relative group';
                div.innerHTML = `
                    <div class="w-full md:w-1/3">
                        <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Label</label>
                        <input type="text" name="structure[${rowIndex}][label]" class="w-full px-3 py-2 border border-slate-200 rounded-xl outline-none focus:border-blue-500 text-sm font-bold">
                    </div>
                    <div class="flex-1 w-full">
                        <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Description</label>
                        <input type="text" name="structure[${rowIndex}][description]" class="w-full px-3 py-2 border border-slate-200 rounded-xl outline-none focus:border-blue-500 text-sm">
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="mt-5 text-rose-400 hover:text-rose-600 p-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                `;
                container.appendChild(div);
                rowIndex++;
            }
        </script>
    <?php endif; ?>
</div>
