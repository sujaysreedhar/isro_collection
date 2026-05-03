<?php

$action = $_GET['action'] ?? 'list';
$themeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$theme = $themeId > 0 ? $this->getTheme($themeId) : null;
$themeTree = $this->getThemeTree(false);
$themeOptions = $this->getThemeOptions(false, $themeId);
$themeCounts = $this->getThemeAggregateCounts(false);
$csrfToken = ensureCsrfToken();
$msg = $_GET['msg'] ?? '';

if (!function_exists('renderThematicTaxonomyRows')) {
    function renderThematicTaxonomyRows(array $nodes, array $counts, string $csrfToken, int $depth = 0): void
    {
        foreach ($nodes as $node) {
            $count = (int)($counts[(int)$node['id']] ?? 0);
            $indent = $depth * 28;
            $statusClass = (int)$node['is_public'] === 1
                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                : 'bg-slate-100 text-slate-600 border-slate-200';
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3" style="padding-left: <?= $indent ?>px;">
                        <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-slate-900 truncate"><?= htmlspecialchars($node['name']) ?></div>
                            <div class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($node['slug']) ?></div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 text-sm text-slate-600">
                    <?= $node['description'] !== '' ? htmlspecialchars($node['description']) : '<span class="text-slate-300">No description</span>' ?>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex min-w-10 items-center justify-center px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-black uppercase tracking-wider">
                        <?= $count ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center px-3 py-1 rounded-full border text-[10px] font-black uppercase tracking-widest <?= $statusClass ?>">
                        <?= (int)$node['is_public'] === 1 ? 'Public' : 'Private' ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="<?= SITE_URL ?>/subject/<?= urlencode($node['slug']) ?>" target="_blank" class="px-3 py-2 text-xs font-bold rounded-xl bg-slate-50 text-slate-700 hover:bg-slate-100 transition">
                            View
                        </a>
                        <a href="<?= SITE_URL ?>/admin/module_page.php?m=thematic_taxonomy&action=edit&id=<?= (int)$node['id'] ?>" class="px-3 py-2 text-xs font-bold rounded-xl bg-blue-50 text-blue-700 hover:bg-blue-100 transition">
                            Edit
                        </a>
                        <a href="<?= SITE_URL ?>/admin/module_page.php?m=thematic_taxonomy&action=delete&id=<?= (int)$node['id'] ?>&csrf_token=<?= urlencode($csrfToken) ?>" class="px-3 py-2 text-xs font-bold rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-100 transition" onclick="return confirm('Delete this subject? Child subjects will be moved to the top level.');">
                            Delete
                        </a>
                    </div>
                </td>
            </tr>
            <?php

            if (!empty($node['children'])) {
                renderThematicTaxonomyRows($node['children'], $counts, $csrfToken, $depth + 1);
            }
        }
    }
}
?>

<div class="min-h-screen bg-[#f7fafc]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 mb-8">
            <div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">Thematic <span class="text-blue-600">Taxonomy</span></h1>
                <p class="text-slate-500 mt-1">Curate hierarchical subjects for areas like Space, Wildlife, Postal History, and numismatic eras.</p>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($action === 'list'): ?>
                    <a href="<?= SITE_URL ?>/admin/module_page.php?m=thematic_taxonomy&action=edit" class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-3 rounded-2xl font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                        </svg>
                        New Subject
                    </a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/admin/module_page.php?m=thematic_taxonomy" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl border border-slate-200 bg-white text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Subjects
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($msg === 'saved'): ?>
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-700 font-medium">
                Subject saved successfully.
            </div>
        <?php elseif ($msg === 'deleted'): ?>
            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-rose-700 font-medium">
                Subject deleted successfully.
            </div>
        <?php elseif ($msg === 'missing_name'): ?>
            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-700 font-medium">
                Subject name is required.
            </div>
        <?php elseif ($msg === 'invalid_slug'): ?>
            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-700 font-medium">
                Please provide a valid slug using letters, numbers, and hyphens.
            </div>
        <?php elseif ($msg === 'slug_taken'): ?>
            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-700 font-medium">
                Another subject already uses that slug.
            </div>
        <?php elseif ($msg === 'self_parent'): ?>
            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-700 font-medium">
                A subject cannot be its own parent.
            </div>
        <?php elseif ($msg === 'cycle'): ?>
            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-700 font-medium">
                That parent selection would create a hierarchy loop.
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
                    <div class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Total Subjects</div>
                    <div class="text-4xl font-black text-slate-900"><?= count($this->getAllThemes(false)) ?></div>
                </div>
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
                    <div class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Public Subjects</div>
                    <div class="text-4xl font-black text-slate-900"><?= count($this->getAllThemes(true)) ?></div>
                </div>
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
                    <div class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Top-Level Subjects</div>
                    <div class="text-4xl font-black text-slate-900"><?= count($this->getChildThemes(null, false)) ?></div>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-[28px] shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Subject Library</h2>
                        <p class="text-sm text-slate-500 mt-1">Build a collector-friendly subject hierarchy and reuse it across items.</p>
                    </div>
                </div>

                <?php if (!$themeTree): ?>
                    <div class="px-6 py-20 text-center text-slate-400">
                        <svg class="mx-auto h-12 w-12 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        <p class="text-lg font-semibold text-slate-500">No subjects created yet</p>
                        <p class="mt-2">Start with broad buckets like Space, Birds, Republic of India, or Postal History.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-black uppercase tracking-widest text-slate-500">Subject</th>
                                    <th class="px-6 py-4 text-left text-xs font-black uppercase tracking-widest text-slate-500">Description</th>
                                    <th class="px-6 py-4 text-center text-xs font-black uppercase tracking-widest text-slate-500">Items</th>
                                    <th class="px-6 py-4 text-center text-xs font-black uppercase tracking-widest text-slate-500">Status</th>
                                    <th class="px-6 py-4 text-right text-xs font-black uppercase tracking-widest text-slate-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php renderThematicTaxonomyRows($themeTree, $themeCounts, $csrfToken); ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php
            $formTheme = $theme ?: [
                'name' => '',
                'slug' => '',
                'description' => '',
                'parent_id' => null,
                'sort_order' => 0,
                'is_public' => 1
            ];
            $publicUrl = $formTheme['slug'] !== '' ? SITE_URL . '/subject/' . urlencode($formTheme['slug']) : '';
            ?>
            <form method="POST" class="grid grid-cols-1 xl:grid-cols-3 gap-8 pb-16">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="save_theme" value="1">

                <div class="xl:col-span-2 space-y-8">
                    <div class="bg-white border border-slate-200 rounded-[32px] p-8 shadow-sm">
                        <h2 class="text-xl font-bold text-slate-900 mb-6">Subject Details</h2>
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Subject Name</label>
                                    <input type="text" name="name" id="theme-name" value="<?= htmlspecialchars($formTheme['name']) ?>" required class="w-full px-5 py-3 border border-slate-200 rounded-2xl outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition font-medium">
                                </div>
                                <div>
                                    <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">URL Slug</label>
                                    <input type="text" name="slug" id="theme-slug" value="<?= htmlspecialchars($formTheme['slug']) ?>" class="w-full px-5 py-3 border border-slate-200 rounded-2xl outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition font-mono text-sm">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Description</label>
                                <textarea name="description" rows="5" class="w-full px-5 py-3 border border-slate-200 rounded-2xl outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition"><?= htmlspecialchars($formTheme['description']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-[32px] p-8 shadow-sm">
                        <h2 class="text-xl font-bold text-slate-900 mb-6">Hierarchy</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Parent Subject</label>
                                <select name="parent_id" class="w-full px-5 py-3 border border-slate-200 rounded-2xl outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition bg-white">
                                    <option value="0">Top-level subject</option>
                                    <?php foreach ($themeOptions as $option): ?>
                                        <?php $selected = (int)($formTheme['parent_id'] ?? 0) === (int)$option['id']; ?>
                                        <option value="<?= (int)$option['id'] ?>" <?= $selected ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($option['trail_label'] ?? str_repeat('-- ', (int)$option['depth']) . $option['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-slate-400 mt-2">Use nesting for structures like Space > ISRO > Launch Vehicles.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Sort Order</label>
                                <input type="number" name="sort_order" value="<?= (int)$formTheme['sort_order'] ?>" class="w-full px-5 py-3 border border-slate-200 rounded-2xl outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition font-bold">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-8">
                    <div class="bg-white border border-slate-200 rounded-[32px] p-8 shadow-sm">
                        <h2 class="text-xl font-bold text-slate-900 mb-6">Publishing</h2>
                        <label class="flex items-center gap-3 p-4 border border-slate-100 rounded-2xl hover:bg-slate-50 transition cursor-pointer">
                            <input type="checkbox" name="is_public" value="1" <?= (int)$formTheme['is_public'] === 1 ? 'checked' : '' ?> class="w-5 h-5 rounded-lg border-slate-300 text-blue-600 focus:ring-blue-500">
                            <div>
                                <span class="block font-bold text-slate-800">Visible on the public site</span>
                                <span class="block text-sm text-slate-500">Private subjects stay available for internal cataloging only.</span>
                            </div>
                        </label>

                        <div class="mt-6 space-y-3">
                            <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black uppercase tracking-widest text-sm hover:bg-blue-700 transition shadow-xl shadow-blue-200 active:scale-95 duration-75">
                                Save Subject
                            </button>
                            <a href="<?= SITE_URL ?>/admin/module_page.php?m=thematic_taxonomy" class="block text-center text-sm font-bold text-slate-400 hover:text-slate-600">
                                Cancel
                            </a>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-[32px] p-8 shadow-sm">
                        <h2 class="text-xl font-bold text-slate-900 mb-4">Public URL</h2>
                        <?php if ($publicUrl !== ''): ?>
                            <div class="p-3 rounded-2xl border border-slate-200 bg-slate-50 text-xs font-mono text-blue-700 break-all">
                                <?= htmlspecialchars($publicUrl) ?>
                            </div>
                            <a href="<?= $publicUrl ?>" target="_blank" class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-blue-700 hover:text-blue-900">
                                Open subject page
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 4h6m0 0v6m0-6L10 14"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10v9a1 1 0 001 1h9"></path>
                                </svg>
                            </a>
                        <?php else: ?>
                            <p class="text-sm text-slate-500">The public URL will appear after the subject has a valid slug.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <script>
                (function () {
                    const nameInput = document.getElementById('theme-name');
                    const slugInput = document.getElementById('theme-slug');
                    if (!nameInput || !slugInput) {
                        return;
                    }

                    function slugify(value) {
                        return value
                            .toLowerCase()
                            .replace(/[^a-z0-9]+/g, '-')
                            .replace(/^-+|-+$/g, '');
                    }

                    nameInput.addEventListener('input', function () {
                        if (slugInput.dataset.touched === '1') {
                            return;
                        }
                        slugInput.value = slugify(nameInput.value);
                    });

                    slugInput.addEventListener('input', function () {
                        slugInput.dataset.touched = '1';
                    });
                })();
            </script>
        <?php endif; ?>
    </div>
</div>
