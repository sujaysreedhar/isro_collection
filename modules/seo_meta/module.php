<?php

class SeoMetaModule extends BaseModule {

    public function boot() {
        // Register hooks via the helper method
        $this->registerHooks();
    }

    public function registerHooks() {
        // Frontend
        HookRegistry::addAction('frontend_head', [$this, 'injectMetaTags']);

        // Admin Edit Item
        HookRegistry::addAction('admin_item_edit_after_fields', [$this, 'injectSeoFields'], 10, 2);
        HookRegistry::addAction('item_saved', [$this, 'saveSeoMeta']);

        // Admin Edit Category
        HookRegistry::addAction('admin_category_edit_after_fields', [$this, 'injectSeoFields'], 10, 2);
        HookRegistry::addAction('category_saved', [$this, 'saveSeoMetaCategory']);
    }

    public function activate() {
        // Use hook-like structure to create the database when activated
        $sql = "CREATE TABLE IF NOT EXISTS seo_meta (
            id INT AUTO_INCREMENT PRIMARY KEY,
            linked_type VARCHAR(20) NOT NULL,
            linked_id INT NOT NULL,
            seo_title TEXT,
            meta_description TEXT,
            meta_keywords TEXT,
            canonical_url TEXT,
            UNIQUE INDEX idx_linked (linked_type, linked_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("SEO Module Activation Error: " . $e->getMessage());
        }
    }

    /**
     * Renders SEO meta fields in the admin edit pages.
     */
    public function injectSeoFields($id, $data = null) {
        $type = 'item';
        // Basic detection if we are in category or item based on available data or current script
        if (str_contains($_SERVER['SCRIPT_NAME'], 'edit_category.php')) {
            $type = 'category';
        }

        $meta = [
            'seo_title' => '',
            'meta_description' => '',
            'meta_keywords' => '',
            'canonical_url' => ''
        ];

        if ($id > 0) {
            $stmt = $this->pdo->prepare("SELECT * FROM seo_meta WHERE linked_type = ? AND linked_id = ?");
            $stmt->execute([$type, $id]);
            $found = $stmt->fetch();
            if ($found) {
                $meta = $found;
            }
        }
        ?>
        <div class="mt-8 border-t border-gray-200 pt-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                SEO Metadata Overrides
            </h3>
            <div class="grid grid-cols-1 gap-5 bg-blue-50/30 p-5 rounded-xl border border-blue-100">
                <div>
                    <label class="block text-xs font-bold text-blue-800 uppercase tracking-wider mb-1">SEO Title Tag</label>
                    <input type="text" name="seo_title" value="<?= htmlspecialchars($meta['seo_title'] ?? '') ?>" 
                           placeholder="Leave empty to use default title" class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-[10px] text-gray-400 mt-1">Recommended length: 50-60 characters.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-blue-800 uppercase tracking-wider mb-1">Meta Description</label>
                    <textarea name="meta_description" rows="3" class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Leave empty to auto-generate from snippet"><?= htmlspecialchars($meta['meta_description'] ?? '') ?></textarea>
                    <p class="text-[10px] text-gray-400 mt-1">Recommended length: 150-160 characters.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-blue-800 uppercase tracking-wider mb-1">Meta Keywords</label>
                        <input type="text" name="meta_keywords" value="<?= htmlspecialchars($meta['meta_keywords'] ?? '') ?>" 
                               placeholder="keyword1, keyword2" class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-blue-800 uppercase tracking-wider mb-1">Canonical URL</label>
                        <input type="url" name="canonical_url" value="<?= htmlspecialchars($meta['canonical_url'] ?? '') ?>" 
                               placeholder="https://example.com/item" class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function saveSeoMeta($id) {
        $this->handleSave('item', $id);
    }

    public function saveSeoMetaCategory($id) {
        $this->handleSave('category', $id);
    }

    private function handleSave($type, $id) {
        $title = trim($_POST['seo_title'] ?? '');
        $description = trim($_POST['meta_description'] ?? '');
        $keywords = trim($_POST['meta_keywords'] ?? '');
        $canonical = trim($_POST['canonical_url'] ?? '');

        // Use REPLACE INTO or ON DUPLICATE KEY UPDATE
        $sql = "INSERT INTO seo_meta (linked_type, linked_id, seo_title, meta_description, meta_keywords, canonical_url)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    seo_title = VALUES(seo_title),
                    meta_description = VALUES(meta_description),
                    meta_keywords = VALUES(meta_keywords),
                    canonical_url = VALUES(canonical_url)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$type, $id, $title, $description, $keywords, $canonical]);
    }

    public function injectMetaTags() {
        global $item, $category, $pageTitle;

        $type = null;
        $id = null;

        if (isset($item['id'])) {
            $type = 'item';
            $id = $item['id'];
        } elseif (isset($category['id'])) {
            $type = 'category';
            $id = $category['id'];
        }

        $customMeta = null;
        if ($type && $id) {
            $stmt = $this->pdo->prepare("SELECT * FROM seo_meta WHERE linked_type = ? AND linked_id = ?");
            $stmt->execute([$type, $id]);
            $customMeta = $stmt->fetch();
        }

        $title = $customMeta['seo_title'] ?? ($pageTitle ?? SITE_TITLE);
        $description = $customMeta['meta_description'] ?? '';
        
        if (!$description) {
            if (isset($item['physical_description'])) {
                $description = mb_substr(strip_tags($item['physical_description']), 0, 160);
            } elseif (isset($item['historical_significance'])) {
                $description = mb_substr(strip_tags($item['historical_significance']), 0, 160);
            } else {
                $description = SITE_TITLE;
            }
        }

        echo "\n    <!-- SEO Meta Overrides -->\n";
        if (!empty($customMeta['seo_title'])) {
            // We echo the title tag IF it's custom. Usually the theme handles the title.
            // But if we want to override the <title> tag, we might need a filter instead of an action.
            // However, most themes in this system use $pageTitle variable.
            // Let's just output the meta tags for now.
        }
        
        echo '    <meta name="description" content="' . htmlspecialchars($description) . '">' . "\n";
        if (!empty($customMeta['meta_keywords'])) {
            echo '    <meta name="keywords" content="' . htmlspecialchars($customMeta['meta_keywords']) . '">' . "\n";
        }
        if (!empty($customMeta['canonical_url'])) {
            echo '    <link rel="canonical" href="' . htmlspecialchars($customMeta['canonical_url']) . '">' . "\n";
        }

        // Open Graph
        echo '    <meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
        echo '    <meta property="og:description" content="' . htmlspecialchars($description) . '">' . "\n";
        echo '    <meta property="og:site_name" content="' . htmlspecialchars(SITE_TITLE) . '">' . "\n";
        
        // OG Image if available
        if (isset($item['file_path'])) {
            $imgUrl = SITE_URL . '/uploads/display/' . $item['file_path'];
            echo '    <meta property="og:image" content="' . htmlspecialchars($imgUrl) . '">' . "\n";
        }
    }
}
