<?php

class SeoMetaModule extends BaseModule {

    public function boot() {
        // Inject meta tags into frontend_head
        HookRegistry::addAction('frontend_head', [$this, 'injectMetaTags']);
        
        // Add SEO fields to admin item page (simplified for now, usually needs more hooks in admin)
        // For now, we'll just demonstrate the frontend part
    }

    public function injectMetaTags() {
        global $item, $category, $pageTitle;

        $description = SITE_TITLE;
        if (isset($item['physical_description'])) {
            $description = mb_substr(strip_tags($item['physical_description']), 0, 160);
        } elseif (isset($item['historical_significance'])) {
            $description = mb_substr(strip_tags($item['historical_significance']), 0, 160);
        }

        echo "\n    <!-- SEO Meta -->\n";
        echo '    <meta name="description" content="' . htmlspecialchars($description) . '">' . "\n";
        echo '    <meta property="og:title" content="' . htmlspecialchars($pageTitle ?? SITE_TITLE) . '">' . "\n";
        echo '    <meta property="og:description" content="' . htmlspecialchars($description) . '">' . "\n";
        echo '    <meta property="og:site_name" content="' . htmlspecialchars(SITE_TITLE) . '">' . "\n";
    }

    public function activate() {
        // Could create a table for custom meta if needed
    }
}
