<?php
// modules/set_manager/views/frontend_list.php
/**
 * @var array $sets
 * @var string $pageTitle
 * @var string $pageDescription
 */
require_once ThemeManager::getHeader(); ?>

<main class="min-h-screen bg-[#f8fafc] pb-24">
    <!-- Hero Section -->
    <div class="relative bg-white border-b border-slate-200 overflow-hidden">
        <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,white,rgba(255,255,255,0.6))] -z-10"></div>
        <div class="container mx-auto px-6 py-16 md:py-24 relative">
            <div class="max-w-3xl">
                <nav class="flex mb-6 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="<?= SITE_URL ?>" class="hover:text-blue-600 transition">Home</a></li>
                        <li class="flex items-center space-x-2">
                            <svg class="h-5 w-5 text-slate-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                            <span class="text-slate-900">Checklists</span>
                        </li>
                    </ol>
                </nav>
                <h1 class="text-4xl md:text-5xl font-extrabold text-slate-900 tracking-tight mb-6">
                    Collection <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600">Checklists</span>
                </h1>
                <p class="text-lg text-slate-600 leading-relaxed mb-8">
                    Discover and track curated sets of stamps, coins, and philatelic history. 
                    Monitor your completion progress and find what's missing in your collection.
                </p>
                <div class="flex flex-wrap gap-4">
                    <div class="bg-white px-4 py-2 rounded-full border border-slate-200 shadow-sm text-sm font-semibold text-slate-700">
                        <span class="text-blue-600"><?= count($sets) ?></span> Active Checklists
                    </div>
                </div>
            </div>
        </div>
        <!-- Abstract Decoration -->
        <div class="absolute right-0 top-0 -mr-24 -mt-24 w-96 h-96 bg-blue-50 rounded-full blur-3xl opacity-50 -z-10"></div>
    </div>

    <!-- Checklists Grid -->
    <div class="container mx-auto px-6 -mt-10 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($sets as $set): ?>
                <?php $prog = $this->module->getSetProgress($set['id']); ?>
                <a href="<?= SITE_URL ?>/checklist/<?= $set['slug'] ?>" class="group bg-white rounded-3xl border border-slate-200 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden flex flex-col">
                    <!-- Banner -->
                    <div class="relative h-48 bg-slate-100 overflow-hidden">
                        <?php if ($set['banner_image']): ?>
                            <img src="<?= $set['banner_image'] ?>" alt="<?= htmlspecialchars($set['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <?php else: ?>
                            <div class="w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600 opacity-80 flex items-center justify-center">
                                <svg class="w-16 h-16 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Progress Overlay Badge -->
                        <div class="absolute bottom-4 right-4 bg-white/90 backdrop-blur-md px-3 py-1 rounded-full text-xs font-bold text-slate-900 shadow-sm border border-white/20">
                            <?= $prog['percent'] ?>% Complete
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-8 flex-1 flex flex-col">
                        <h3 class="text-xl font-bold text-slate-900 mb-3 group-hover:text-blue-600 transition">
                            <?= htmlspecialchars($set['name']) ?>
                        </h3>
                        <p class="text-slate-500 text-sm leading-relaxed mb-6 line-clamp-2">
                            <?= htmlspecialchars($set['description'] ?: 'No description provided.') ?>
                        </p>
                        
                        <div class="mt-auto space-y-4">
                            <!-- Progress Bar -->
                            <div class="space-y-1.5">
                                <div class="flex justify-between text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                    <span>Progress</span>
                                    <span><?= $prog['count'] ?> / <?= $prog['target'] ?> Items</span>
                                </div>
                                <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-600 rounded-full transition-all duration-1000" style="width: <?= $prog['percent'] ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                                <span class="text-blue-600 text-sm font-bold flex items-center gap-1">
                                    View Checklist
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </span>
                                <?php if ($set['is_featured']): ?>
                                    <span class="bg-amber-100 text-amber-700 text-[10px] font-black uppercase px-2 py-0.5 rounded leading-tight">Featured</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>

            <?php if (empty($sets)): ?>
                <div class="col-span-full bg-white rounded-3xl border-2 border-dashed border-slate-200 p-20 text-center">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-2">No checklists found</h3>
                    <p class="text-slate-500">Check back later for new collection sets.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once ThemeManager::getFooter(); ?>
