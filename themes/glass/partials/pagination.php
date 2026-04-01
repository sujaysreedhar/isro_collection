<?php
/** @var int $currentPage */
/** @var int $totalPages */

if ($totalPages <= 1) return;

$p = $_GET;
unset($p['page']);
$queryString = http_build_query($p);
if ($queryString) $queryString .= '&';
?>

<nav class="flex items-center justify-center space-x-2 mt-16 mb-12 bg-white/5 backdrop-blur-md border border-white/10 p-5 rounded-2xl shadow-[0_8px_32px_0_rgba(0,0,0,0.37)] overflow-x-auto pb-4 hide-scrollbar" aria-label="Pagination">
    <?php if ($currentPage > 1): ?>
        <a href="?<?= $queryString ?>page=<?= $currentPage - 1 ?>" 
           class="inline-flex items-center px-5 py-2.5 text-sm font-bold text-white/70 bg-white/5 border border-white/10 rounded-xl hover:bg-glass-500/30 hover:text-white hover:border-glass-400/50 transition-all duration-300"
           title="Previous Page">
            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            Prev
        </a>
    <?php else: ?>
        <span class="inline-flex items-center px-5 py-2.5 text-sm font-bold text-white/20 bg-white/5 border border-white/5 rounded-xl cursor-not-allowed">
            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            Prev
        </span>
    <?php endif; ?>

    <div class="flex items-center gap-1.5">
        <?php
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        
        if ($start > 1): ?>
            <a href="?<?= $queryString ?>page=1" class="w-11 h-11 inline-flex items-center justify-center text-sm font-bold text-white/50 hover:bg-white/10 rounded-xl transition-all">1</a>
            <?php if ($start > 2): ?>
                <span class="w-6 h-11 inline-flex items-center justify-center text-white/20">...</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i == $currentPage): ?>
                <span class="w-11 h-11 inline-flex items-center justify-center text-sm font-bold text-white bg-glass-600 rounded-xl shadow-[0_0_20px_rgba(var(--glass-glow),0.4)] border border-glass-400/50" aria-current="page"><?= $i ?></span>
            <?php else: ?>
                <a href="?<?= $queryString ?>page=<?= $i ?>" 
                   class="w-11 h-11 inline-flex items-center justify-center text-sm font-bold text-white/50 hover:bg-white/10 rounded-xl transition-all"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?>
                <span class="w-6 h-11 inline-flex items-center justify-center text-white/20">...</span>
            <?php endif; ?>
            <a href="?<?= $queryString ?>page=<?= $totalPages ?>" class="w-11 h-11 inline-flex items-center justify-center text-sm font-bold text-white/50 hover:bg-white/10 rounded-xl transition-all"><?= $totalPages ?></a>
        <?php endif; ?>
    </div>

    <?php if ($currentPage < $totalPages): ?>
        <a href="?<?= $queryString ?>page=<?= $currentPage + 1 ?>" 
           class="inline-flex items-center px-5 py-2.5 text-sm font-bold text-white/70 bg-white/5 border border-white/10 rounded-xl hover:bg-glass-500/30 hover:text-white hover:border-glass-400/50 transition-all duration-300"
           title="Next Page">
            Next
            <svg class="h-4 w-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
        </a>
    <?php else: ?>
        <span class="inline-flex items-center px-5 py-2.5 text-sm font-bold text-white/20 bg-white/5 border border-white/5 rounded-xl cursor-not-allowed">
            Next
            <svg class="h-4 w-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
        </span>
    <?php endif; ?>
</nav>
