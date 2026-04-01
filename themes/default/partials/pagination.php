<?php
/** @var int $currentPage */
/** @var int $totalPages */

if ($totalPages <= 1) return;

$p = $_GET;
unset($p['page']); // Remove page to add it back with current page value.
$queryString = http_build_query($p);
if ($queryString) $queryString .= '&';
?>

<nav class="flex items-center justify-center space-x-2 mt-12 mb-8 bg-white p-4 rounded-xl border border-gray-100 shadow-sm overflow-x-auto pb-4 hide-scrollbar" aria-label="Pagination">
    <?php if ($currentPage > 1): ?>
        <a href="?<?= $queryString ?>page=<?= $currentPage - 1 ?>" 
           class="inline-flex items-center px-4 py-2 text-sm font-bold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-black hover:text-white transition-all duration-200"
           title="Previous Page">
            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Prev
        </a>
    <?php else: ?>
        <span class="inline-flex items-center px-4 py-2 text-sm font-bold text-gray-300 bg-gray-50 border border-gray-200 rounded-lg cursor-not-allowed">
            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Prev
        </span>
    <?php endif; ?>

    <div class="flex items-center gap-1">
        <?php
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        
        if ($start > 1): ?>
            <a href="?<?= $queryString ?>page=1" class="w-10 h-10 inline-flex items-center justify-center text-sm font-bold text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">1</a>
            <?php if ($start > 2): ?>
                <span class="w-6 h-10 inline-flex items-center justify-center text-gray-300">...</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i == $currentPage): ?>
                <span class="w-10 h-10 inline-flex items-center justify-center text-sm font-bold text-white bg-black rounded-lg shadow-sm" aria-current="page"><?= $i ?></span>
            <?php else: ?>
                <a href="?<?= $queryString ?>page=<?= $i ?>" 
                   class="w-10 h-10 inline-flex items-center justify-center text-sm font-bold text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?>
                <span class="w-6 h-10 inline-flex items-center justify-center text-gray-300">...</span>
            <?php endif; ?>
            <a href="?<?= $queryString ?>page=<?= $totalPages ?>" class="w-10 h-10 inline-flex items-center justify-center text-sm font-bold text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"><?= $totalPages ?></a>
        <?php endif; ?>
    </div>

    <?php if ($currentPage < $totalPages): ?>
        <a href="?<?= $queryString ?>page=<?= $currentPage + 1 ?>" 
           class="inline-flex items-center px-4 py-2 text-sm font-bold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-black hover:text-white transition-all duration-200"
           title="Next Page">
            Next
            <svg class="h-4 w-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    <?php else: ?>
        <span class="inline-flex items-center px-4 py-2 text-sm font-bold text-gray-300 bg-gray-50 border border-gray-200 rounded-lg cursor-not-allowed">
            Next
            <svg class="h-4 w-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </span>
    <?php endif; ?>
</nav>
