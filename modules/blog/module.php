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
