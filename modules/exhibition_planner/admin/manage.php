<?php
// modules/exhibition_planner/admin/manage.php

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$msg = $_GET['msg'] ?? '';
$csrfToken = ensureCsrfToken();

if (!function_exists('exhibitionPlannerExcerpt')) {
    function exhibitionPlannerExcerpt(?string $text, int $limit = 140): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string)$text)));
        if ($plain === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($plain) > $limit ? mb_substr($plain, 0, $limit - 1) . '...' : $plain;
        }

        return strlen($plain) > $limit ? substr($plain, 0, $limit - 1) . '...' : $plain;
    }
}

if ($action === 'list'):
    $pages = $this->pdo->query("
        SELECT
            p.*,
            (
                SELECT COUNT(*)
                FROM module_exhibition_items ei
                WHERE ei.page_id = p.id
            ) AS item_count
        FROM module_exhibition_pages p
        ORDER BY p.created_at DESC, p.title ASC
    ")->fetchAll();

    $totalExhibitions = count($pages);
    $totalItems = array_sum(array_map(static fn($page) => (int)$page['item_count'], $pages));
    $withBanners = count(array_filter($pages, static fn($page) => trim((string)($page['banner_image'] ?? '')) !== ''));
    ?>
    <div class="space-y-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">Exhibition Planner</h1>
                <p class="text-slate-500 mt-1">Build public-facing exhibition journeys with custom intros, hero art, curated notes, and item sequencing.</p>
            </div>
            <a href="<?= SITE_URL ?>/admin/module_page.php?m=exhibition_planner&action=new" class="inline-flex items-center justify-center gap-2 bg-blue-600 text-white px-5 py-3 rounded-2xl font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                </svg>
                New Exhibition
            </a>
        </div>

        <?php if ($msg === 'deleted'): ?>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-rose-700 font-medium">
                Exhibition deleted successfully.
            </div>
        <?php elseif ($msg === 'invalid_exhibition'): ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-700 font-medium">
                That exhibition could not be found.
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
                <div class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Total Exhibitions</div>
                <div class="text-4xl font-black text-slate-900"><?= $totalExhibitions ?></div>
            </div>
            <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
                <div class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Curated Artifacts</div>
                <div class="text-4xl font-black text-slate-900"><?= $totalItems ?></div>
            </div>
            <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
                <div class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Hero Banners Set</div>
                <div class="text-4xl font-black text-slate-900"><?= $withBanners ?></div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-[30px] shadow-sm overflow-hidden">
            <?php if (!$pages): ?>
                <div class="px-8 py-20 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <p class="text-lg font-semibold text-slate-500">No exhibitions created yet</p>
                    <p class="text-slate-400 mt-2">Start with a title, optional banner image, and a handful of standout items.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 p-6">
                    <?php foreach ($pages as $page): ?>
                        <?php $excerpt = exhibitionPlannerExcerpt($page['description']); ?>
                        <div class="rounded-[28px] border border-slate-200 overflow-hidden bg-slate-50/50">
                            <div class="h-52 bg-slate-900 relative overflow-hidden">
                                <?php if (trim((string)$page['banner_image']) !== ''): ?>
                                    <img src="<?= htmlspecialchars($page['banner_image']) ?>" alt="<?= htmlspecialchars($page['title']) ?>" class="w-full h-full object-cover opacity-80">
                                <?php else: ?>
                                    <div class="absolute inset-0 bg-gradient-to-br from-slate-800 via-slate-900 to-blue-950"></div>
                                <?php endif; ?>
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-900/20 to-transparent"></div>
                                <div class="absolute bottom-0 left-0 right-0 p-6">
                                    <div class="flex items-center gap-2 mb-3">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/15 text-white text-[11px] font-black uppercase tracking-widest">
                                            <?= (int)$page['item_count'] ?> item<?= (int)$page['item_count'] === 1 ? '' : 's' ?>
                                        </span>
                                        <?php if (trim((string)$page['banner_image']) !== ''): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-500/80 text-white text-[11px] font-black uppercase tracking-widest">
                                                Banner Ready
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h2 class="text-2xl font-black text-white tracking-tight"><?= htmlspecialchars($page['title']) ?></h2>
                                </div>
                            </div>
                            <div class="p-6 bg-white">
                                <p class="text-sm text-slate-500 min-h-[44px]">
                                    <?= $excerpt !== '' ? htmlspecialchars($excerpt) : 'Add a curator introduction to frame the public story behind this exhibition.' ?>
                                </p>
                                <div class="mt-5 flex flex-wrap gap-3">
                                    <a href="<?= SITE_URL ?>/admin/module_page.php?m=exhibition_planner&action=edit&id=<?= (int)$page['id'] ?>" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-slate-900 text-white text-sm font-bold hover:bg-slate-800 transition">
                                        Manage
                                    </a>
                                    <a href="<?= SITE_URL ?>/exhibition/<?= rawurlencode($page['slug']) ?>" target="_blank" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-blue-50 text-blue-700 text-sm font-bold hover:bg-blue-100 transition">
                                        View Public Page
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php elseif ($action === 'new' || $action === 'edit'): ?>
    <?php
    $page = [
        'title' => '',
        'slug' => '',
        'description' => '',
        'banner_image' => '',
    ];

    if ($id > 0) {
        $stmt = $this->pdo->prepare("SELECT * FROM module_exhibition_pages WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $fetchedPage = $stmt->fetch();
        if ($fetchedPage) {
            $page = $fetchedPage;
        } else {
            ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-700 font-medium">
                That exhibition could not be found. <a href="<?= SITE_URL ?>/admin/module_page.php?m=exhibition_planner" class="underline font-bold">Return to the list</a>.
            </div>
            <?php
            return;
        }
    }

    $exhibitionItems = [];
    $availableItems = [];
    $itemCount = 0;
    $nextSortOrder = 1;

    if ($id > 0) {
        $itemsStmt = $this->pdo->prepare("
            SELECT
                i.id,
                i.reg_number,
                i.title,
                ei.sort_order,
                ei.annotation,
                media.file_path AS preview_image
            FROM module_exhibition_items ei
            JOIN items i ON i.id = ei.item_id
            LEFT JOIN media media ON media.id = (
                SELECT m.id
                FROM media m
                WHERE m.item_id = i.id AND m.media_type = 'image'
                ORDER BY m.upload_date DESC, m.id DESC
                LIMIT 1
            )
            WHERE ei.page_id = ?
            ORDER BY ei.sort_order ASC, ei.id ASC
        ");
        $itemsStmt->execute([$id]);
        $exhibitionItems = $itemsStmt->fetchAll();
        $itemCount = count($exhibitionItems);

        $availableStmt = $this->pdo->prepare("
            SELECT i.id, i.reg_number, i.title
            FROM items i
            WHERE NOT EXISTS (
                SELECT 1
                FROM module_exhibition_items ei
                WHERE ei.page_id = ? AND ei.item_id = i.id
            )
            ORDER BY i.reg_number ASC, i.title ASC
        ");
        $availableStmt->execute([$id]);
        $availableItems = $availableStmt->fetchAll();

        $nextSortOrder = exhibitionPlannerNextSortOrder($this->pdo, $id);
    }

    $publicUrl = $page['slug'] !== '' ? SITE_URL . '/exhibition/' . rawurlencode($page['slug']) : '';
    ?>
    <div class="space-y-8 pb-16">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
                <a href="<?= SITE_URL ?>/admin/module_page.php?m=exhibition_planner" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-slate-800 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Exhibitions
                </a>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight mt-3"><?= $id > 0 ? 'Edit Exhibition' : 'Create Exhibition' ?></h1>
                <p class="text-slate-500 mt-1"><?= $id > 0 ? 'Shape the public story, ordering, and curator notes for this exhibition.' : 'Create the public shell first, then start adding artifacts.' ?></p>
            </div>
            <?php if ($publicUrl !== ''): ?>
                <a href="<?= htmlspecialchars($publicUrl) ?>" target="_blank" class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-blue-50 text-blue-700 font-bold hover:bg-blue-100 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 3h7m0 0v7m0-7L10 14"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5v14h14"></path>
                    </svg>
                    Open Public Page
                </a>
            <?php endif; ?>
        </div>

        <?php if ($msg === 'saved'): ?>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-700 font-medium">
                Exhibition details saved successfully.
            </div>
        <?php elseif ($msg === 'item_added'): ?>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-700 font-medium">
                Item added to the exhibition.
            </div>
        <?php elseif ($msg === 'item_updated'): ?>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-700 font-medium">
                Item order and curator note updated.
            </div>
        <?php elseif ($msg === 'item_removed'): ?>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-rose-700 font-medium">
                Item removed from the exhibition.
            </div>
        <?php elseif ($msg === 'duplicate_item'): ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-700 font-medium">
                That item is already part of this exhibition.
            </div>
        <?php elseif ($msg === 'invalid_item'): ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-700 font-medium">
                Please choose a valid collection item.
            </div>
        <?php elseif ($msg === 'missing_title'): ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-700 font-medium">
                Exhibition title is required.
            </div>
        <?php elseif ($msg === 'invalid_slug'): ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-700 font-medium">
                Please use letters, numbers, and hyphens for the slug.
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <div class="xl:col-span-2 space-y-8">
                <form method="POST" action="<?= SITE_URL ?>/admin/module_page.php?m=exhibition_planner&action=save&id=<?= $id ?>" class="bg-white border border-slate-200 rounded-[30px] p-8 shadow-sm space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Exhibition Details</h2>
                            <p class="text-sm text-slate-500 mt-1">These fields control the title, URL, intro text, and hero image shown on the public page.</p>
                        </div>
                        <?php if ($id > 0): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-black uppercase tracking-widest">
                                <?= $itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Exhibition Title</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($page['title']) ?>" required class="w-full px-5 py-3 border border-slate-200 rounded-2xl outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition font-medium" placeholder="e.g. India in Orbit">
                        </div>
                        <div>
                            <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">URL Slug</label>
                            <input type="text" name="slug" value="<?= htmlspecialchars($page['slug']) ?>" class="w-full px-5 py-3 border border-slate-200 rounded-2xl outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition font-mono text-sm" placeholder="india-in-orbit">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Banner Image URL</label>
                        <input type="text" name="banner_image" value="<?= htmlspecialchars($page['banner_image']) ?>" class="w-full px-5 py-3 border border-slate-200 rounded-2xl outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition" placeholder="https://... or /uploads/...">
                        <p class="text-xs text-slate-400 mt-2">Use a wide image URL or stored path for the exhibition hero area and card preview.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Curator Introduction</label>
                        <textarea name="description" rows="6" class="w-full px-5 py-3 border border-slate-200 rounded-2xl outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition" placeholder="Describe the exhibition theme, lens, or historical arc."><?= htmlspecialchars($page['description']) ?></textarea>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="inline-flex items-center justify-center px-6 py-3 rounded-2xl bg-slate-900 text-white font-bold hover:bg-slate-800 transition shadow-lg shadow-slate-200">
                            Save Exhibition
                        </button>
                        <a href="<?= SITE_URL ?>/admin/module_page.php?m=exhibition_planner" class="inline-flex items-center justify-center px-6 py-3 rounded-2xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition">
                            Cancel
                        </a>
                    </div>
                </form>

                <?php if ($id > 0): ?>
                    <div class="bg-white border border-slate-200 rounded-[30px] p-8 shadow-sm space-y-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold text-slate-900">Curated Items</h2>
                                <p class="text-sm text-slate-500 mt-1">Order the exhibition flow and add short curator notes for each artifact.</p>
                            </div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-black uppercase tracking-widest">
                                Dragless ordering via sort values
                            </span>
                        </div>

                        <?php if (!$exhibitionItems): ?>
                            <div class="rounded-3xl border-2 border-dashed border-slate-200 bg-slate-50 px-8 py-12 text-center">
                                <p class="text-lg font-semibold text-slate-500">No items added yet</p>
                                <p class="text-slate-400 mt-2">Use the sidebar to add artifacts, then return here to tune the story order and notes.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-5">
                                <?php foreach ($exhibitionItems as $item): ?>
                                    <div class="rounded-[26px] border border-slate-200 overflow-hidden">
                                        <div class="grid grid-cols-1 lg:grid-cols-[220px_minmax(0,1fr)]">
                                            <div class="bg-slate-100 min-h-[220px]">
                                                <?php if (!empty($item['preview_image'])): ?>
                                                    <img src="<?= MediaProcessor::url($item['preview_image'], 'display', 'image', $storage ?? null) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <div class="w-full h-full flex items-center justify-center text-slate-400 text-sm font-bold uppercase tracking-widest">
                                                        No image
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="p-6 space-y-5">
                                                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                                                    <div class="min-w-0">
                                                        <div class="flex flex-wrap items-center gap-2 mb-2">
                                                            <span class="inline-flex items-center px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-[11px] font-black uppercase tracking-widest">
                                                                <?= htmlspecialchars($item['reg_number']) ?>
                                                            </span>
                                                            <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-[11px] font-black uppercase tracking-widest">
                                                                Sort <?= (int)$item['sort_order'] ?>
                                                            </span>
                                                        </div>
                                                        <h3 class="text-xl font-black text-slate-900"><?= htmlspecialchars($item['title']) ?></h3>
                                                    </div>
                                                    <a href="<?= SITE_URL ?>/item/<?= (int)$item['id'] ?>" target="_blank" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-slate-50 text-slate-700 text-sm font-bold hover:bg-slate-100 transition">
                                                        View Item
                                                    </a>
                                                </div>

                                                <form method="POST" action="<?= SITE_URL ?>/admin/module_page.php?m=exhibition_planner&action=edit&id=<?= $id ?>" class="space-y-4">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="update_item_id" value="<?= (int)$item['id'] ?>">

                                                    <div class="grid grid-cols-1 md:grid-cols-[160px_minmax(0,1fr)] gap-4">
                                                        <div>
                                                            <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Sort Order</label>
                                                            <input type="number" name="sort_order" value="<?= (int)$item['sort_order'] ?>" class="w-full px-4 py-3 border border-slate-200 rounded-2xl outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition font-bold">
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Curator Note</label>
                                                            <textarea name="annotation" rows="4" class="w-full px-4 py-3 border border-slate-200 rounded-2xl outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition" placeholder="Add a short narrative note, highlight, or contextual observation."><?= htmlspecialchars($item['annotation']) ?></textarea>
                                                        </div>
                                                    </div>

                                                    <div class="flex flex-wrap gap-3">
                                                        <button type="submit" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold hover:bg-blue-700 transition">
                                                            Save Item Details
                                                        </button>
                                                    </div>
                                                </form>

                                                <form method="POST" action="<?= SITE_URL ?>/admin/module_page.php?m=exhibition_planner&action=edit&id=<?= $id ?>" onsubmit="return confirm('Remove this item from the exhibition?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="remove_item_id" value="<?= (int)$item['id'] ?>">
                                                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-rose-50 text-rose-700 text-sm font-bold hover:bg-rose-100 transition">
                                                        Remove from Exhibition
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="space-y-8">
                <div class="bg-white border border-slate-200 rounded-[30px] p-8 shadow-sm">
                    <h2 class="text-xl font-bold text-slate-900 mb-4">Publishing Snapshot</h2>
                    <div class="space-y-4">
                        <div class="rounded-2xl border border-slate-100 p-4">
                            <div class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Public URL</div>
                            <div class="text-sm font-medium text-slate-700 break-all">
                                <?= $publicUrl !== '' ? htmlspecialchars($publicUrl) : 'Save the exhibition to generate its public route.' ?>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-100 p-4">
                            <div class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Hero Banner</div>
                            <div class="text-sm font-medium text-slate-700">
                                <?= trim((string)$page['banner_image']) !== '' ? 'Configured' : 'Not set yet' ?>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-100 p-4">
                            <div class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Curated Items</div>
                            <div class="text-sm font-medium text-slate-700"><?= $id > 0 ? $itemCount . ' item' . ($itemCount === 1 ? '' : 's') : 'Add items after the first save.' ?></div>
                        </div>
                    </div>
                </div>

                <?php if ($id > 0): ?>
                    <div class="bg-white border border-slate-200 rounded-[30px] p-8 shadow-sm">
                        <h2 class="text-xl font-bold text-slate-900 mb-4">Add Item</h2>
                        <?php if (!$availableItems): ?>
                            <p class="text-sm text-slate-500">All collection items are already part of this exhibition.</p>
                        <?php else: ?>
                            <form method="POST" action="<?= SITE_URL ?>/admin/module_page.php?m=exhibition_planner&action=edit&id=<?= $id ?>" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                                <div>
                                    <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Collection Item</label>
                                    <select name="add_item_id" class="w-full px-4 py-3 border border-slate-200 rounded-2xl outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition bg-white" required>
                                        <option value="">Select an item</option>
                                        <?php foreach ($availableItems as $availableItem): ?>
                                            <option value="<?= (int)$availableItem['id'] ?>">[<?= htmlspecialchars($availableItem['reg_number']) ?>] <?= htmlspecialchars($availableItem['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Initial Sort Order</label>
                                    <input type="number" name="sort_order" value="<?= $nextSortOrder ?>" class="w-full px-4 py-3 border border-slate-200 rounded-2xl outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition font-bold">
                                </div>

                                <div>
                                    <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Optional Curator Note</label>
                                    <textarea name="annotation" rows="4" class="w-full px-4 py-3 border border-slate-200 rounded-2xl outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition" placeholder="Introduce why this item matters in the flow."></textarea>
                                </div>

                                <button type="submit" class="w-full inline-flex items-center justify-center px-5 py-3 rounded-2xl bg-blue-600 text-white font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-200">
                                    Add to Exhibition
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="bg-white border border-rose-200 rounded-[30px] p-8 shadow-sm">
                        <h2 class="text-xl font-bold text-slate-900 mb-2">Danger Zone</h2>
                        <p class="text-sm text-slate-500 mb-5">Deleting the exhibition removes its item mapping and public page.</p>
                        <form method="POST" action="<?= SITE_URL ?>/admin/module_page.php?m=exhibition_planner&action=edit&id=<?= $id ?>" onsubmit="return confirm('Delete this exhibition and all of its curation data?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="delete_exhibition" value="1">
                            <button type="submit" class="w-full inline-flex items-center justify-center px-5 py-3 rounded-2xl bg-rose-50 text-rose-700 font-bold hover:bg-rose-100 transition">
                                Delete Exhibition
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="bg-slate-50 border border-slate-200 rounded-[30px] p-8">
                        <h2 class="text-xl font-bold text-slate-900 mb-2">Next Step</h2>
                        <p class="text-sm text-slate-500">Save this exhibition first, then you will be able to add items, set story order, and write curator notes.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
