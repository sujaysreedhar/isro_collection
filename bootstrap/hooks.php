<?php
// bootstrap/hooks.php
// ── Stage 5: Core Hook Registrations ─────────────────────────────────────────
// Registers application-level hooks that are not owned by any specific module.
// Currently: search vocabulary cache invalidation on catalog changes.
// Prerequisites: $pdo, HookRegistry, SearchEngine all loaded.

/** @var PDO $pdo — injected by bootstrap/database.php */
if (class_exists('HookRegistry')) {
    HookRegistry::addAction('item_saved', function() use ($pdo) {
        (new SearchEngine($pdo))->rebuildVocabularyCache();
        // Invalidate year range cache so search facets stay fresh
        $yearCacheFile = CACHE_DIR . '/year_range.json';
        if (file_exists($yearCacheFile)) @unlink($yearCacheFile);
    });
    HookRegistry::addAction('category_saved', function() use ($pdo) {
        (new SearchEngine($pdo))->rebuildVocabularyCache();
    });
    HookRegistry::addAction('category_deleted', function() use ($pdo) {
        (new SearchEngine($pdo))->rebuildVocabularyCache();
    });
}
