<?php

class SitemapModule extends BaseModule {

    public function boot() {
        // Handle sitemap.xml request — guard against CLI where $_SERVER may be absent
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ($uri && strpos($uri, '/sitemap.xml') !== false) {
            $this->generateSitemap();
            exit;
        }
    }

    public function generateSitemap() {
        header('Content-Type: application/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Homepage
        $this->addUrl(SITE_URL . '/');

        // Items
        $stmt = $this->pdo->query("SELECT id FROM items WHERE is_visible = 1");
        while ($id = $stmt->fetchColumn()) {
            $this->addUrl(SITE_URL . '/item_detail.php?id=' . $id);
        }

        // Categories
        $stmt = $this->pdo->query("SELECT id FROM categories");
        while ($id = $stmt->fetchColumn()) {
            $this->addUrl(SITE_URL . '/search.php?category_ids[]=' . $id);
        }

        echo '</urlset>';
    }

    private function addUrl($url) {
        echo "  <url>\n    <loc>" . htmlspecialchars($url) . "</loc>\n    <changefreq>weekly</changefreq>\n  </url>\n";
    }
}
