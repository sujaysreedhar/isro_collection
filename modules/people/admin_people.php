<?php
// modules/people/admin_people.php
$action = $_GET['action'] ?? 'list';
$personId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = $_GET['msg'] ?? '';

// Fetch person if editing
$person = null;
if ($personId > 0) {
    $stmt = $this->pdo->prepare("SELECT * FROM people WHERE id = ?");
    $stmt->execute([$personId]);
    $person = $stmt->fetch();
    
    // Fetch linked items
    $stmtItems = $this->pdo->prepare("SELECT i.id, i.title, i.reg_number, ip.role FROM items i JOIN item_people ip ON i.id = ip.item_id WHERE ip.person_id = ?");
    $stmtItems->execute([$personId]);
    $linkedItems = $stmtItems->fetchAll();
}

// Fetch all people for list view
$people = [];
if ($action === 'list') {
    $people = $this->pdo->query("SELECT * FROM people ORDER BY name ASC")->fetchAll();
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
?>

<div class="p-6 max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">People & Biographies</h2>
            <p class="text-slate-500 mt-1">Manage historical figures and link them to collection items.</p>
        </div>
        <?php if ($action === 'list'): ?>
            <a href="?m=people&action=add" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-bold shadow-sm transition flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Person
            </a>
        <?php else: ?>
            <a href="?m=people" class="text-slate-500 hover:text-slate-700 flex items-center gap-1 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to List
            </a>
        <?php endif; ?>
    </div>

    <?php if ($msg === 'saved'): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            Profile saved successfully.
        </div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            Person deleted successfully.
        </div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Person</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($people)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-slate-400 italic">No biographical profiles found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($people as $p): ?>
                            <tr class="hover:bg-slate-50 transition-colors group">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-4">
                                        <?php if ($p['profile_image']): ?>
                                            <img src="<?= SITE_URL ?>/uploads/display/<?= htmlspecialchars($p['profile_image']) ?>" class="w-12 h-12 rounded-full object-cover border border-slate-200">
                                        <?php else: ?>
                                            <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-300">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="font-bold text-slate-900"><?= htmlspecialchars($p['name']) ?></div>
                                            <div class="text-xs text-slate-400 font-medium"><?= htmlspecialchars($p['slug']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($p['is_public']): ?>
                                        <span class="px-2.5 py-1 rounded-full bg-green-100 text-green-700 text-[10px] font-bold uppercase tracking-wider">Public</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold uppercase tracking-wider">Private</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="?m=people&action=edit&id=<?= $p['id'] ?>" class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="Edit">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 00-2 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                        <a href="?m=people&action=delete&id=<?= $p['id'] ?>&csrf_token=<?= $csrfToken ?>" onclick="return confirm('Really delete this profile? Connections to items will also be removed.')" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-20">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="save_person" value="1">

            <!-- Profile Info Main -->
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 space-y-6">
                    <h3 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4">Biographical Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Full Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($person['name'] ?? '') ?>" required
                                   class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all text-slate-900"
                                   id="person-name" onkeyup="updateSlug()">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">URL Slug</label>
                            <input type="text" name="slug" value="<?= htmlspecialchars($person['slug'] ?? '') ?>" required
                                   class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all text-slate-900 font-mono"
                                   id="person-slug">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Birth Date (e.g., 1850)</label>
                            <input type="text" name="birth_date" value="<?= htmlspecialchars($person['birth_date'] ?? '') ?>"
                                   class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all text-slate-900">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Death Date (leave blank if alive)</label>
                            <input type="text" name="death_date" value="<?= htmlspecialchars($person['death_date'] ?? '') ?>"
                                   class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all text-slate-900">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Short Description / One-liner</label>
                        <input type="text" name="short_description" value="<?= htmlspecialchars($person['short_description'] ?? '') ?>" placeholder="e.g. Renowned Philatelist and Collector"
                               class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all text-slate-900">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Biography (Wikipedia Style)</label>
                        <textarea name="biography" rows="12" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all text-slate-900 font-serif leading-relaxed"><?= htmlspecialchars($person['biography'] ?? '') ?></textarea>
                        <p class="text-xs text-slate-400 mt-2 italic">Standard paragraphs and simple HTML are supported.</p>
                    </div>
                </div>

                <!-- Related Artifacts Tool (Only if editing) -->
                <?php if ($personId > 0): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 space-y-6" id="items">
                        <h3 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4">Linked Collection Items</h3>
                        
                        <div class="flex gap-4">
                            <div class="relative flex-grow">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </span>
                                <input type="text" id="item-search" placeholder="Search items by Title or Reg Number..." 
                                       class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 transition-all outline-none">
                                <div id="search-results" class="absolute z-50 w-full mt-2 bg-white border border-slate-200 rounded-xl shadow-xl hidden max-h-64 overflow-y-auto"></div>
                            </div>
                        </div>

                        <div class="space-y-3" id="linked-items-list">
                            <?php if (empty($linkedItems)): ?>
                                <p class="text-center text-slate-400 py-6 italic text-sm border-2 border-dashed border-slate-100 rounded-2xl">No items linked to this person yet.</p>
                            <?php else: ?>
                                <?php foreach ($linkedItems as $li): ?>
                                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100 group">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-slate-900"><?= htmlspecialchars($li['title']) ?></span>
                                            <span class="text-xs text-slate-500"><?= htmlspecialchars($li['reg_number']) ?></span>
                                        </div>
                                        <button type="button" onclick="unlinkItem(<?= $li['id'] ?>)" class="text-red-400 hover:text-red-600 p-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar Widgets -->
            <div class="lg:col-span-1 space-y-8">
                <!-- Public URL Widget -->
                <?php if ($personId > 0 && !empty($person['slug'])): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 border-b border-slate-50 pb-3">Public Profile URL</h3>
                    <?php $publicUrl = SITE_URL . '/person/' . urlencode($person['slug']); ?>
                    <div class="flex items-center gap-2 p-2 bg-slate-50 border border-slate-200 rounded-lg group">
                        <input type="text" readonly value="<?= htmlspecialchars($publicUrl) ?>" id="public-url-input" class="bg-transparent text-[10px] text-indigo-600 font-mono w-full outline-none">
                        <button type="button" onclick="copyPublicUrl(this)" class="p-1 px-2 bg-white border border-slate-200 text-slate-500 text-[10px] font-bold rounded-md hover:bg-slate-100 transition shadow-sm whitespace-nowrap">Copy</button>
                        <a href="<?= $publicUrl ?>" target="_blank" class="p-1 px-2 bg-white border border-slate-200 text-slate-500 text-[10px] font-bold rounded-md hover:bg-slate-100 transition shadow-sm whitespace-nowrap">Open</a>
                    </div>
                </div>
                <script>
                    function copyPublicUrl(btn) {
                        const input = document.getElementById('public-url-input');
                        input.select();
                        document.execCommand('copy');
                        const originalText = btn.innerHTML;
                        btn.innerHTML = 'Copied!';
                        setTimeout(() => btn.innerHTML = originalText, 2000);
                    }
                </script>
                <?php endif; ?>

                <!-- Save Widget -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-bold text-slate-700">Display Publicly</label>
                        <button type="button" onclick="document.getElementById('is_public_check').click()" id="toggle-btn" 
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none <?= ($person['is_public'] ?? 1) ? 'bg-indigo-600' : 'bg-slate-200' ?>">
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out <?= ($person['is_public'] ?? 1) ? 'translate-x-5' : 'translate-x-0' ?>"></span>
                        </button>
                        <input type="checkbox" name="is_public" id="is_public_check" class="hidden" <?= ($person['is_public'] ?? 1) ? 'checked' : '' ?> onchange="updateToggleUI()">
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-indigo-200 transition-all flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                        Save Profile
                    </button>
                </div>

                <!-- Profile Photo -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 border-b border-slate-50 pb-3">Profile Photo</h3>
                    <div class="flex flex-col items-center">
                        <div class="w-40 h-40 rounded-full bg-slate-50 border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden mb-4 relative group">
                            <?php if ($person['profile_image'] ?? false): ?>
                                <img src="<?= SITE_URL ?>/uploads/display/<?= htmlspecialchars($person['profile_image']) ?>" class="w-full h-full object-cover" id="preview-img">
                            <?php else: ?>
                                <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer" onclick="document.getElementById('photo-input').click()">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </div>
                        </div>
                        <input type="file" name="profile_image" id="photo-input" class="hidden" onchange="previewFile(this)">
                        <button type="button" onclick="document.getElementById('photo-input').click()" class="text-sm font-bold text-indigo-600 hover:text-indigo-800 transition">Update Photo</button>
                    </div>
                </div>

                <!-- Wikipedia Infobox Editor -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 border-b border-slate-50 pb-3 flex justify-between items-center">
                        Standard Infobox Facts
                        <button type="button" onclick="addInfoRow()" class="text-indigo-600 hover:text-indigo-800"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg></button>
                    </h3>
                    <div id="infobox-rows" class="space-y-3">
                        <?php 
                        $infobox = json_decode($person['infobox_data'] ?? '[]', true);
                        if (empty($infobox)) {
                            // Default rows for Wikipedia feel
                            $infobox = [['label' => 'Born', 'value' => ''], ['label' => 'Occupation', 'value' => ''], ['label' => 'Nationality', 'value' => '']];
                        }
                        foreach ($infobox as $row):
                        ?>
                            <div class="flex gap-2 group/row">
                                <input type="text" name="info_key[]" value="<?= htmlspecialchars($row['label']) ?>" placeholder="Label" class="w-1/3 text-xs font-bold px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg outline-none">
                                <input type="text" name="info_val[]" value="<?= htmlspecialchars($row['value']) ?>" placeholder="Value" class="w-2/3 text-xs px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg outline-none">
                                <button type="button" onclick="this.parentElement.remove()" class="text-slate-300 hover:text-red-400 group-hover/row:opacity-100 transition-opacity"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-[10px] text-slate-400">These will appear in the "Fact Card" on the profile page.</p>
                </div>
            </div>
        </form>

        <script>
            function updateSlug() {
                const name = document.getElementById('person-name').value;
                const slugInput = document.getElementById('person-slug');
                if (!slugInput.dataset.touched) {
                    slugInput.value = name.toLowerCase()
                        .replace(/[^\w\s-]/g, '')
                        .replace(/[\s_-]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                }
            }
            document.getElementById('person-slug').oninput = function() { this.dataset.touched = true; };

            function updateToggleUI() {
                const check = document.getElementById('is_public_check');
                const btn = document.getElementById('toggle-btn');
                const dot = btn.querySelector('span');
                if (check.checked) {
                    btn.classList.replace('bg-slate-200', 'bg-indigo-600');
                    dot.classList.replace('translate-x-0', 'translate-x-5');
                } else {
                    btn.classList.replace('bg-indigo-600', 'bg-slate-200');
                    dot.classList.replace('translate-x-5', 'translate-x-0');
                }
            }

            function previewFile(input) {
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.getElementById('preview-img');
                        if (img) img.src = e.target.result;
                        else {
                            const container = input.previousElementSibling;
                            container.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
                        }
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            }

            function addInfoRow() {
                const container = document.getElementById('infobox-rows');
                const div = document.createElement('div');
                div.className = 'flex gap-2 group/row';
                div.innerHTML = `
                    <input type="text" name="info_key[]" placeholder="Label" class="w-1/3 text-xs font-bold px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg outline-none">
                    <input type="text" name="info_val[]" placeholder="Value" class="w-2/3 text-xs px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg outline-none">
                    <button type="button" onclick="this.parentElement.remove()" class="text-slate-300 hover:text-red-400 opacity-100 transition-opacity"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                `;
                container.appendChild(div);
            }

            // AJAX Search for items
            const searchInput = document.getElementById('item-search');
            const searchResults = document.getElementById('search-results');
            if (searchInput) {
                let timer;
                searchInput.oninput = function() {
                    clearTimeout(timer);
                    const q = this.value;
                    if (q.length < 2) { searchResults.classList.add('hidden'); return; }
                    
                    timer = setTimeout(() => {
                        fetch(`?m=people&action=search_items_ajax&exclude_person=<?= $personId ?>&q=${encodeURIComponent(q)}`)
                            .then(r => r.json())
                            .then(json => {
                                searchResults.innerHTML = '';
                                if (json.data && json.data.length > 0) {
                                    json.data.forEach(item => {
                                        const div = document.createElement('div');
                                        div.className = 'px-4 py-3 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0';
                                        div.innerHTML = `<div class="font-bold text-sm text-slate-800">${item.title}</div><div class="text-[10px] text-slate-400 uppercase font-bold">${item.reg_number}</div>`;
                                        div.onclick = () => linkItem(item.id);
                                        searchResults.appendChild(div);
                                    });
                                    searchResults.classList.remove('hidden');
                                } else {
                                    searchResults.classList.add('hidden');
                                }
                            });
                    }, 300);
                };
            }

            function linkItem(itemId) {
                const fd = new FormData();
                fd.append('item_id', itemId);
                fd.append('csrf_token', '<?= $csrfToken ?>');
                
                fetch(`?m=people&action=link_item_ajax&id=<?= $personId ?>`, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            searchResults.classList.add('hidden');
                            searchInput.value = '';
                            loadLinkedItems();
                        } else alert('Error linking item: ' + (res.error || 'Unknown error'));
                    });
            }

            function unlinkItem(itemId) {
                if (!confirm('Unlink this item?')) return;
                const fd = new FormData();
                fd.append('item_id', itemId);
                fd.append('csrf_token', '<?= $csrfToken ?>');
                
                fetch(`?m=people&action=unlink_item_ajax&id=<?= $personId ?>`, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) loadLinkedItems();
                    });
            }

            function loadLinkedItems() {
                const list = document.getElementById('linked-items-list');
                fetch(`?m=people&action=get_linked_items_ajax&id=<?= $personId ?>`)
                    .then(r => r.json())
                    .then(json => {
                        if (json.data && json.data.length > 0) {
                            list.innerHTML = json.data.map(li => `
                                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100 group">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-900">${escapeHtml(li.title)}</span>
                                        <span class="text-xs text-slate-500">${escapeHtml(li.reg_number)}</span>
                                    </div>
                                    <button type="button" onclick="unlinkItem(${li.id})" class="text-red-400 hover:text-red-600 p-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            `).join('');
                        } else {
                            list.innerHTML = `<p class="text-center text-slate-400 py-6 italic text-sm border-2 border-dashed border-slate-100 rounded-2xl">No items linked to this person yet.</p>`;
                        }
                    });
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        </script>
    <?php endif; ?>
</div>
