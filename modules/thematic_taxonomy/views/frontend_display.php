<?php
/** @var array $themeTrails */
?>

<section class="mt-12 pt-8 border-t border-slate-200">
    <div class="flex items-center justify-between gap-4 mb-6">
        <div>
            <h3 class="text-xl font-bold text-slate-900 font-serif">Collection Subjects</h3>
            <p class="text-sm text-slate-500 mt-1">Browse this item through the curated subjects it belongs to.</p>
        </div>
        <a href="<?= SITE_URL ?>/subjects" class="text-sm font-bold text-blue-700 hover:text-blue-900">
            Browse all subjects
        </a>
    </div>

    <div class="space-y-3">
        <?php foreach ($themeTrails as $trail): ?>
            <div class="bg-white border border-slate-200 rounded-2xl px-4 py-3 shadow-sm">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <?php foreach ($trail as $index => $theme): ?>
                        <a href="<?= SITE_URL ?>/subject/<?= urlencode($theme['slug']) ?>" class="inline-flex items-center px-3 py-1.5 rounded-full bg-slate-50 text-slate-700 hover:bg-blue-50 hover:text-blue-700 transition font-medium">
                            <?= htmlspecialchars($theme['name']) ?>
                        </a>
                        <?php if ($index < count($trail) - 1): ?>
                            <span class="text-slate-300">/</span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
