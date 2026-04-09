<?php

class SitemapModule extends BaseModule {

    public function boot() {
        // Handle sitemap.xml request — guard against CLI where $_SERVER may be absent
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Site URL path for normalization
        $basePath = parse_url(SITE_URL, PHP_URL_PATH) ?? '';
        $cleanUri = $uri;
        if ($basePath && strpos($uri, $basePath) === 0) {
            $cleanUri = substr($uri, strlen($basePath));
        }
        $cleanUri = ltrim($cleanUri, '/');

        if ($cleanUri === 'sitemap.xml') {
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
        $stmt = $this->pdo->query("SELECT id, title, upload_date FROM items WHERE is_visible = 1");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $slug = $this->slugify($row['title']);
            $this->addUrl(SITE_URL . '/item/' . $row['id'] . '/' . $slug, $row['upload_date']);
        }

        // Categories (using slugs)
        $stmt = $this->pdo->query("SELECT slug FROM categories WHERE slug IS NOT NULL AND slug != ''");
        while ($slug = $stmt->fetchColumn()) {
            $this->addUrl(SITE_URL . '/category/' . $slug);
        }

        // Tags
        $stmt = $this->pdo->query("SELECT slug FROM tags");
        while ($slug = $stmt->fetchColumn()) {
            $this->addUrl(SITE_URL . '/tag/' . $slug);
        }

        // Stories / Narratives
        $stmt = $this->pdo->query("SELECT id FROM narratives");
        while ($id = $stmt->fetchColumn()) {
            $this->addUrl(SITE_URL . '/story/' . $id);
        }

        echo '</urlset>';
    }

    private function addUrl($url, $lastmod = null) {
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($url) . "</loc>\n";
        if ($lastmod) {
            $date = date('Y-m-d', strtotime($lastmod));
            echo "    <lastmod>" . $date . "</lastmod>\n";
        }
        echo "    <changefreq>weekly</changefreq>\n";
        echo "  </url>\n";
    }

    private function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        if (empty($text)) return 'n-a';
        return $text;
    }
}
