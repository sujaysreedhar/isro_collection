<?php
// modules/exhibition_planner/admin/manage.php

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'list'):
    $pages = $this->pdo->query("SELECT * FROM module_exhibition_pages ORDER BY created_at DESC")->fetchAll();
?>
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Virtual Exhibitions</h1>
            <p class="text-sm text-gray-500">Curate and arrange items into online album pages.</p>
        </div>
        <a href="module_page.php?m=exhibition_planner&action=new" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">Create New Exhibition</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($pages as $p): ?>
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden group hover:shadow-lg transition-all">
                <div class="h-32 bg-slate-100 flex items-center justify-center border-b border-gray-100">
                    <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <div class="p-5">
                    <h3 class="font-bold text-gray-900 group-hover:text-blue-600 transition-colors"><?= htmlspecialchars($p['title']) ?></h3>
                    <p class="text-xs text-gray-500 mt-1 line-clamp-2"><?= htmlspecialchars($p['description']) ?></p>
                    <div class="mt-4 flex gap-2">
                        <a href="module_page.php?m=exhibition_planner&action=edit&id=<?= $p['id'] ?>" class="flex-1 text-center px-3 py-2 bg-slate-50 text-slate-600 text-xs font-bold rounded-lg hover:bg-slate-100 transition-colors uppercase tracking-widest">Manage Items</a>
                        <a href="<?= SITE_URL ?>/exhibition/<?= $p['slug'] ?>" target="_blank" class="px-3 py-2 bg-blue-50 text-blue-600 text-xs font-bold rounded-lg hover:bg-blue-100 transition-colors uppercase tracking-widest">View ↗</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php elseif ($action === 'new' || $action === 'edit'): 
    $page = $id > 0 ? $this->pdo->query("SELECT * FROM module_exhibition_pages WHERE id = $id")->fetch() : ['title' => '', 'description' => ''];
?>
    <div class="mb-6">
        <a href="module_page.php?m=exhibition_planner" class="text-sm font-medium text-gray-500 hover:text-gray-900">&larr; Back to List</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2"><?= $id > 0 ? 'Edit Exhibition' : 'New Exhibition' ?></h1>
    </div>

    <form method="POST" action="module_page.php?m=exhibition_planner&action=save&id=<?= $id ?>" class="space-y-6">
        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-4">
            <div>
                <label class="label">Exhibition Title</label>
                <input type="text" name="title" value="<?= htmlspecialchars($page['title']) ?>" class="input font-bold text-lg" required placeholder="e.g. My Early Indian Stamps Collection">
            </div>
            <div>
                <label class="label">Introduction / Description</label>
                <textarea name="description" class="input min-h-[100px]" placeholder="Briefly explain the theme of this exhibition..."><?= htmlspecialchars($page['description']) ?></textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2 bg-gray-900 text-white text-sm font-bold rounded-lg hover:bg-gray-800 transition-all shadow-lg shadow-gray-200">Save Exhibition Info</button>
            </div>
        </div>
    </form>
    
    <?php if ($id > 0): 
        $exhibitionItems = $this->pdo->query("
            SELECT i.id, i.reg_number, i.title, ei.sort_order 
            FROM module_exhibition_items ei 
            JOIN items i ON ei.item_id = i.id 
            WHERE ei.page_id = $id 
            ORDER BY ei.sort_order ASC
        ")->fetchAll();

        $allItems = $this->pdo->query("SELECT id, reg_number, title FROM items ORDER BY reg_number ASC")->fetchAll();
    ?>
        <div class="mt-10 grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Items in this Exhibition (<?= count($exhibitionItems) ?>)</h2>
                <?php if ($exhibitionItems): ?>
                    <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden divide-y divide-gray-100">
                        <?php foreach ($exhibitionItems as $item): ?>
                            <div class="px-6 py-4 flex items-center justify-between hover:bg-slate-50 transition-colors">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center text-slate-400 font-bold text-xs">
                                        #<?= $item['id'] ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900"><?= htmlspecialchars($item['title']) ?></p>
                                        <p class="text-[10px] text-gray-400 font-mono tracking-widest"><?= htmlspecialchars($item['reg_number']) ?></p>
                                    </div>
                                </div>
                                <a href="module_page.php?m=exhibition_planner&action=edit&id=<?= $id ?>&remove_item_id=<?= $item['id'] ?>" class="text-red-500 hover:text-red-700 p-2" title="Remove from Exhibition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-slate-50 border-2 border-dashed border-slate-200 rounded-2xl p-10 text-center">
                        <p class="text-sm text-slate-500 font-medium">No items added yet. Use the sidebar to add items from your collection.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <h2 class="text-xl font-bold text-gray-900 mb-4">Add Items</h2>
                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                    <form method="POST" action="module_page.php?m=exhibition_planner&action=edit&id=<?= $id ?>" class="space-y-4">
                        <select name="add_item_id" class="input w-full" required>
                            <option value="">Select an item to add...</option>
                            <?php foreach ($allItems as $ai): ?>
                                <option value="<?= $ai['id'] ?>">[<?= htmlspecialchars($ai['reg_number']) ?>] <?= htmlspecialchars($ai['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-lg uppercase tracking-widest hover:bg-blue-700 transition-all">Add to Exhibition</button>
                    </form>
                    <p class="text-[10px] text-slate-400 mt-4 leading-relaxed italic text-center">Tip: You can add an item to multiple exhibitions.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
