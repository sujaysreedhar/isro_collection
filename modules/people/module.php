<?php
// modules/people/module.php

class PeopleModule extends BaseModule {

    public function boot() {
        // Add to main navigation
        HookRegistry::addFilter('frontend_nav_links', function($links) {
            $links['people'] = [
                'url' => SITE_URL . '/people.php',
                'label' => 'People'
            ];
            return $links;
        });

        // Admin menu
        HookRegistry::addAction('admin_menu', function() {
            $url = SITE_URL . '/admin/module_page.php?m=people';
            echo '<a href="' . $url . '" class="sidebar-link text-slate-300">People/Biographies</a>';
        });

        // Register admin page
        HookRegistry::addAction('admin_page_people', function() {
            require_once __DIR__ . '/admin_people.php';
        });

        // Register admin logic
        HookRegistry::addAction('admin_init_people', function() {
            require_once __DIR__ . '/admin_people_logic.php';
        });
        
        // Inject biography into item detail page
        HookRegistry::addAction('item_after_content', [$this, 'injectPeopleInfo']);

        // Routing
        HookRegistry::addFilter('route_request', function($handled, $uri) {
            if ($uri === 'people' || $uri === 'people.php') {
                require __DIR__ . '/people.php';
                return true;
            }
            if (preg_match('#^person/([a-zA-Z0-9_-]+)/?$#', $uri, $matches)) {
                $_GET['slug'] = $matches[1];
                require __DIR__ . '/person.php';
                return true;
            }
            // Handle legacy person.php?slug=... if it hits the index.php router
            if ($uri === 'person.php' && isset($_GET['slug'])) {
                require __DIR__ . '/person.php';
                return true;
            }
            return $handled;
        }, 10, 2);

        // Search Integration
        HookRegistry::addFilter('search_results', function($results, $params) {
            $q = trim($params['q'] ?? '');
            if (!$q) return $results;

            global $pdo;
            $searchTerm = '%' . $q . '%';
            $stmt = $pdo->prepare("
                SELECT * FROM people 
                WHERE is_public = 1 AND (name LIKE ? OR short_description LIKE ? OR biography LIKE ?)
            ");
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            $people = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($people as $person) {
                $results[] = [
                    'id' => 'person_' . $person['id'],
                    '_module_url' => SITE_URL . '/person/' . $person['slug'],
                    '_module_type' => 'Biography',
                    '_module_tags' => [],
                    '_module_image_url' => $person['profile_image'] ? SITE_URL . '/uploads/display/' . $person['profile_image'] : '',
                    'title' => $person['name'],
                    'production_date' => $person['birth_date'] ? $person['birth_date'] . ' - ' . ($person['death_date'] ?: 'Present') : '',
                    'reg_number' => 'Biography',
                    'material' => $person['short_description']
                ];
            }
            return $results;
        }, 10, 2);

        // Home page modular section
        HookRegistry::addAction('home_page_sections', [$this, 'renderHomeSection']);
    }

    public function renderHomeSection() {
        $stmt = $this->pdo->query("SELECT * FROM people WHERE is_public = 1 ORDER BY id DESC LIMIT 4");
        $people = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$people) return;

        echo '<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 border-t border-gray-100 dark:border-gray-800">';
        echo '<div class="flex items-center justify-between mb-8">';
        echo '<h2 class="text-3xl font-bold text-gray-900 dark:text-white serif">Featured People</h2>';
        echo '<a href="' . SITE_URL . '/people.php" class="text-sm font-semibold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white flex items-center">View All <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></a>';
        echo '</div>';
        echo '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">';
        
        foreach ($people as $person) {
            $url = SITE_URL . '/person/' . urlencode($person['slug']);
            $img = $person['profile_image'] ? SITE_URL . '/uploads/display/' . htmlspecialchars($person['profile_image']) : '';
            
            echo '<a href="' . $url . '" class="group flex flex-col items-center p-6 bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 hover:shadow-xl dark:hover:border-gray-600 transition-all text-center">';
            
            if ($img) {
                echo '<img src="' . $img . '" alt="' . htmlspecialchars($person['name']) . '" class="w-24 h-24 rounded-full object-cover mb-4 border-4 border-gray-50 dark:border-gray-700 shadow-md group-hover:scale-105 transition-transform">';
            } else {
                echo '<div class="w-24 h-24 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 dark:text-gray-500 mb-4 border-4 border-gray-50 dark:border-gray-800"><svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></div>';
            }
            
            echo '<h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1 group-hover:text-blue-600 dark:group-hover:text-blue-400">' . htmlspecialchars($person['name']) . '</h3>';
            
            if (!empty($person['short_description'])) {
                echo '<p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">' . htmlspecialchars($person['short_description']) . '</p>';
            }
            
            echo '</a>';
        }
        
        echo '</div></div>';
    }

    public function activate() {
        $schemaPeople = "
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            birth_date VARCHAR(100),
            death_date VARCHAR(100),
            short_description VARCHAR(500),
            biography LONGTEXT,
            infobox_data JSON,
            profile_image VARCHAR(255),
            is_public TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (slug)
        ";

        $schemaItemPeople = "
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            person_id INT NOT NULL,
            role VARCHAR(100) DEFAULT 'Subject',
            INDEX (item_id),
            INDEX (person_id)
        ";

        if (class_exists('ModuleDB')) {
            ModuleDB::createTable($this->pdo, 'people', $schemaPeople);
            ModuleDB::createTable($this->pdo, 'item_people', $schemaItemPeople);
        }
    }

    public function injectPeopleInfo($item) {
        if (!$item) return;
        
        $stmt = $this->pdo->prepare("
            SELECT p.*, ip.role 
            FROM people p
            JOIN item_people ip ON p.id = ip.person_id
            WHERE ip.item_id = ? AND p.is_public = 1
        ");
        $stmt->execute([$item['id']]);
        $people = $stmt->fetchAll();

        if ($people) {
            echo '<div class="mt-12 pt-8 border-t border-slate-200">';
            echo '<h3 class="text-xl font-bold text-slate-900 mb-6 font-serif">Related People & Biographies</h3>';
            echo '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">';
            foreach ($people as $person) {
                $url = SITE_URL . '/person/' . urlencode($person['slug']);
                $img = $person['profile_image'] ? SITE_URL . '/uploads/display/' . $person['profile_image'] : '';
                
                echo '<a href="' . $url . '" class="flex items-center p-4 bg-white border border-slate-200 rounded-2xl hover:shadow-md transition group">';
                if ($img) {
                    echo '<img src="' . htmlspecialchars($img) . '" class="w-16 h-16 rounded-full object-cover border-2 border-slate-100 shadow-sm">';
                } else {
                    echo '<div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center text-slate-400"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></div>';
                }
                echo '<div class="ml-4">';
                echo '<h4 class="font-bold text-slate-900 group-hover:text-blue-600 transition">' . htmlspecialchars($person['name']) . '</h4>';
                if ($person['short_description']) {
                    echo '<p class="text-[10px] text-slate-400 italic truncate max-w-[150px]">' . htmlspecialchars($person['short_description']) . '</p>';
                }
                echo '</div>';
                echo '</a>';
            }
            echo '</div></div>';
        }
    }
}
