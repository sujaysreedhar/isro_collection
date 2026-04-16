<?php
// modules/set_manager/views/frontend_detail.php
/**
 * @var array $set
 * @var array $structure
 * @var array $progress
 * @var array $ownedByStructure
 */
require_once ThemeManager::getHeader(); ?>

<main class="min-h-screen bg-[#f8fafc] pb-24">
    <!-- Hero / Header Section -->
    <div class="relative bg-white border-b border-slate-200 overflow-hidden">
        <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,white,rgba(255,255,255,0.6))] -z-10"></div>
        <div class="container mx-auto px-6 py-12 md:py-16 relative">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-8">
                <div class="max-w-3xl">
                    <nav class="flex mb-6 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
                        <ol class="flex items-center space-x-2">
                            <li><a href="<?= SITE_URL ?>" class="hover:text-blue-600 transition">Home</a></li>
                            <li class="flex items-center space-x-2">
                                <svg class="h-5 w-5 text-slate-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                <a href="<?= SITE_URL ?>/checklists" class="hover:text-blue-600 transition">Checklists</a>
                            </li>
                            <li class="flex items-center space-x-2">
                                <svg class="h-5 w-5 text-slate-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                                <span class="text-slate-900"><?= htmlspecialchars($set['name']) ?></span>
                            </li>
                        </ol>
                    </nav>
                    <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight mb-4"><?= htmlspecialchars($set['name']) ?></h1>
                    <p class="text-lg text-slate-600 leading-relaxed mb-0">
                        <?= htmlspecialchars($set['description'] ?: 'Complete your collection by tracking items in this set.') ?>
                    </p>
                </div>
                
                <!-- Progress Card -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm min-w-[280px]">
                    <div class="flex justify-between items-end mb-4">
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Completion</div>
                            <div class="text-3xl font-black text-slate-900"><?= $progress['percent'] ?>%</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold text-slate-600"><?= $progress['count'] ?> <span class="text-slate-400">/ <?= $progress['target'] ?></span></div>
                            <div class="text-[10px] text-slate-400 font-medium">Items Owned</div>
                        </div>
                    </div>
                    <div class="h-3 w-full bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-blue-600 to-indigo-600 rounded-full transition-all duration-1000 shadow-[0_0_12px_rgba(37,99,235,0.3)]" style="width: <?= $progress['percent'] ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Checklist Content -->
    <div class="container mx-auto px-6 py-12">
        <div class="flex flex-col lg:flex-row gap-12">
            <!-- Main Content Area -->
            <div class="flex-1">
                <div class="mb-8 flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-slate-900">Checklist Items</h2>
                    <div class="flex gap-2">
                        <button class="bg-white px-4 py-2 rounded-xl border border-slate-200 text-sm font-bold text-slate-600 hover:bg-slate-50 transition shadow-sm">Filter: All</button>
                    </div>
                </div>

                <div class="space-y-4">
                    <?php if (empty($structure)): ?>
                        <!-- If no structure, show items directly assigned -->
                        <?php foreach ($ownedByStructure['unmapped'] ?? [] as $item): ?>
                             <div class="bg-white border border-slate-200 rounded-2xl p-5 flex items-center gap-6 shadow-sm hover:shadow-md transition">
                                <div class="w-20 h-20 bg-slate-50 rounded-xl flex-shrink-0 overflow-hidden border border-slate-100">
                                    <img src="<?= SITE_URL ?>/uploads/thumbnails/item_<?= $item['id'] ?>_preview.webp" class="w-full h-full object-cover" onerror="this.src='https://placehold.co/200x200?text=Item'">
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-bold text-slate-900 text-lg"><?= htmlspecialchars($item['title'] ?? 'Unknown Item') ?></h4>
                                    <p class="text-sm text-slate-500"><?= htmlspecialchars(mb_strimwidth($item['description'] ?? '', 0, 100, '...')) ?></p>
                                </div>
                                <div class="flex-shrink-0">
                                    <span class="bg-emerald-100 text-emerald-700 font-bold px-4 py-1.5 rounded-full text-sm flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                        Owned
                                    </span>
                                </div>
                             </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Rich Structure Checklist -->
                        <?php foreach ($structure as $req): ?>
                            <?php $item = $ownedByStructure[$req['id']] ?? null; ?>
                            <div class="group bg-white border <?= $item ? 'border-emerald-200 bg-emerald-50/10' : 'border-slate-200' ?> rounded-3xl p-6 flex flex-col md:flex-row items-start md:items-center gap-6 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300">
                                <!-- Status Icon -->
                                <div class="flex-shrink-0">
                                    <?php if ($item): ?>
                                        <div class="w-14 h-14 bg-emerald-500 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-emerald-200 ring-4 ring-emerald-50">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-14 h-14 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center border-2 border-dashed border-slate-300">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Text Content -->
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-1">
                                        <h3 class="text-xl font-bold <?= $item ? 'text-slate-900' : 'text-slate-500' ?>">
                                            <?= htmlspecialchars($req['label']) ?>
                                        </h3>
                                        <?php if ($item): ?>
                                            <span class="text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded">Collected</span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-black uppercase bg-slate-100 text-slate-400 px-2 py-0.5 rounded">Wanted</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-slate-500 text-sm italic group-hover:text-slate-600 transition">
                                        <?= htmlspecialchars($req['description'] ?: 'Checklist requirement details...') ?>
                                    </p>
                                </div>

                                <!-- Item Preview / Link -->
                                <div class="flex-shrink-0 w-full md:w-auto mt-4 md:mt-0">
                                    <?php if ($item): ?>
                                        <a href="<?= SITE_URL ?>/item/<?= $item['id'] ?>" class="flex items-center gap-4 bg-white p-2 pr-4 rounded-2xl border border-emerald-100 shadow-sm hover:shadow-md hover:border-emerald-300 transition group/item">
                                            <div class="w-12 h-12 rounded-xl overflow-hidden bg-slate-50">
                                                <img src="<?= SITE_URL ?>/uploads/thumbnails/item_<?= $item['id'] ?>_preview.webp" class="w-full h-full object-cover" onerror="this.src='https://placehold.co/200x200?text=Item'">
                                            </div>
                                            <div>
                                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Your Item</div>
                                                <div class="text-sm font-bold text-slate-800 line-clamp-1">View Details</div>
                                            </div>
                                            <svg class="w-4 h-4 text-emerald-400 group-hover/item:translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                        </a>
                                    <?php else: ?>
                                        <button class="w-full md:w-auto flex items-center justify-center gap-2 bg-slate-50 py-3 px-6 rounded-2xl border border-slate-200 text-sm font-bold text-slate-400 cursor-not-allowed">
                                            Not in Collection
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="w-full lg:w-[380px] space-y-8">
                <!-- Info Card -->
                <div class="bg-gradient-to-br from-slate-900 to-indigo-950 rounded-[40px] p-8 text-white shadow-2xl relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mr-16 -mt-16 w-48 h-48 bg-blue-500/20 rounded-full blur-3xl"></div>
                    
                    <h4 class="text-xl font-bold mb-6 flex items-center gap-3">
                        <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        About this Set
                    </h4>
                    <div class="space-y-6 text-slate-300 text-sm leading-relaxed">
                        <p>Complete this checklist by adding items from your collection. We automatically detect matching items assigned to this set.</p>
                        
                        <div class="pt-6 border-t border-white/10 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="font-bold">Total Requirements</span>
                                <span class="text-white font-mono"><?= count($structure) ?: $progress['target'] ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-bold">Items Owned</span>
                                <span class="text-emerald-400 font-mono"><?= $progress['count'] ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-bold">Missing</span>
                                <span class="text-rose-400 font-mono"><?= (count($structure) ?: $progress['target']) - $progress['count'] ?></span>
                            </div>
                        </div>
                        
                        <button class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-4 rounded-2xl transition shadow-lg shadow-blue-900/40">
                            Download Checklist PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once ThemeManager::getFooter(); ?>
