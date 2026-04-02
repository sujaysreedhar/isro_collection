<?php
// modules/blog/module.php

class BlogModule extends BaseModule {

    public function boot() {
        HookRegistry::addAction('admin_menu', function() {
            echo '<div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Content</div>';
            echo '<a href="' . SITE_URL . '/admin/module_page.php?m=blog" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">Blog Posts</a>';
        });

        HookRegistry::addFilter('frontend_nav_links', function($links) {
            $links['blog'] = ['url' => SITE_URL . '/blog', 'label' => 'Blog'];
            return $links;
        });

        HookRegistry::addFilter('route_request', function($handled, $uri) {
            if ($uri === 'blog') {
                require __DIR__ . '/blog_list.php';
                return true;
            }
            if (preg_match('#^blog/([a-zA-Z0-9_-]+)/?$#', $uri, $matches)) {
                $_GET['slug'] = $matches[1];
                require __DIR__ . '/post_detail.php';
                return true;
            }
            return $handled;
        }, 10, 2);

        HookRegistry::addFilter('search_results', function($results, $params) {
            $q = trim($params['q'] ?? '');
            if (!$q) return $results;

            global $pdo;
            $searchTerm = '%' . $q . '%';
            $stmt = $pdo->prepare("
                SELECT bp.* 
                FROM blog_posts bp 
                WHERE bp.status = 'published' AND (bp.title LIKE ? OR bp.content LIKE ?)
            ");
            $stmt->execute([$searchTerm, $searchTerm]);
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch tags for these posts
            $postIds = array_column($posts, 'id');
            $tagsByPost = [];
            if ($postIds) {
                $ph = implode(',', array_fill(0, count($postIds), '?'));
                $tStmt = $pdo->prepare("
                    SELECT bpt.post_id, t.name 
                    FROM blog_post_tag bpt 
                    INNER JOIN tags t ON bpt.tag_id = t.id 
                    WHERE bpt.post_id IN ($ph)
                ");
                $tStmt->execute($postIds);
                foreach ($tStmt->fetchAll() as $row) {
                    $tagsByPost[$row['post_id']][] = $row['name'];
                }
            }

            foreach ($posts as $post) {
                $results[] = [
                    'id' => 'blog_' . $post['id'],
                    '_module_url' => SITE_URL . '/blog/' . $post['slug'],
                    '_module_type' => 'Blog Post',
                    '_module_tags' => $tagsByPost[$post['id']] ?? [],
                    '_module_image_url' => $post['featured_image'] ? SITE_URL . '/uploads/' . $post['featured_image'] : '',
                    'title' => $post['title'],
                    'production_date' => date('M j, Y', strtotime($post['published_at'])),
                    'reg_number' => 'Blog Post',
                    'material' => ''
                ];
            }
            return $results;
        }, 10, 2);

        HookRegistry::addAction('admin_page_blog', function() {
            require_once __DIR__ . '/admin_posts.php';
        });

        // Home page modular section
        HookRegistry::addAction('home_page_sections', [$this, 'renderHomeSection']);
    }

    public function renderHomeSection() {
        $stmt = $this->pdo->query("SELECT * FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC LIMIT 3");
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$posts) return;

        echo '<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 border-t border-gray-100 dark:border-gray-800">';
        echo '<div class="flex items-center justify-between mb-8">';
        echo '<h2 class="text-3xl font-bold text-gray-900 dark:text-white serif">Latest Stories</h2>';
        echo '<a href="' . SITE_URL . '/blog" class="text-sm font-semibold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white flex items-center">Read All <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></a>';
        echo '</div>';
        echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-8">';
        
        foreach ($posts as $post) {
            $url = SITE_URL . '/blog/' . urlencode($post['slug']);
            echo '<a href="' . $url . '" class="group block bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-xl dark:hover:border-gray-600 transition-all flex flex-col h-full">';
            echo '<div class="relative h-48 bg-gray-100 dark:bg-gray-900 overflow-hidden">';
            
            if (!empty($post['featured_image'])) {
                $imgUrl = SITE_URL . '/uploads/' . htmlspecialchars($post['featured_image']);
                echo '<img src="' . $imgUrl . '" alt="' . htmlspecialchars($post['title']) . '" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">';
            } else {
                echo '<div class="absolute inset-0 flex items-center justify-center"><svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1l2.293-2.293A1 1 0 0121 5.414v13.172a1 1 0 01-1.707.707L17 17v1a2 2 0 01-2 2z"></path></svg></div>';
            }
            
            echo '</div>';
            echo '<div class="p-6 flex flex-col flex-grow">';
            $pubDate = !empty($post['published_at']) ? date('M j, Y', strtotime($post['published_at'])) : date('M j, Y', strtotime($post['created_at']));
            echo '<div class="text-xs font-bold tracking-widest text-blue-600 dark:text-blue-400 uppercase mb-2 block">' . $pubDate . '</div>';
            echo '<h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-blue-600 dark:group-hover:text-blue-400 leading-snug line-clamp-2">' . htmlspecialchars($post['title']) . '</h3>';
            echo '<p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-3">' . htmlspecialchars(strip_tags($post['excerpt'] ?? '')) . '</p>';
            echo '</div></a>';
        }
        
        echo '</div></div>';
    }

    public function activate() {
        $schemaDef = "
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content MediumText,
            excerpt TEXT,
            featured_image VARCHAR(255),
            status ENUM('draft', 'published') DEFAULT 'draft',
            author_id INT,
            published_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES admins(id) ON DELETE SET NULL
        ";
        
        if (class_exists('ModuleDB')) {
            ModuleDB::createTable($this->pdo, 'blog_posts', $schemaDef);
            
            $tagSchemaDef = "
                post_id INT NOT NULL,
                tag_id INT NOT NULL,
                PRIMARY KEY(post_id, tag_id),
                FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
            ";
            ModuleDB::createTable($this->pdo, 'blog_post_tag', $tagSchemaDef);
        }
    }
    
    public function deactivate() {
       // We usually don't drop tables on deactivation to prevent data loss.
    }
}
