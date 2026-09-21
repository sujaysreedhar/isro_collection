<?php
// modules/newsletter/module.php

if (!class_exists('BaseModule')) {
    require_once __DIR__ . '/../../includes/BaseModule.php';
}

class NewsletterModule extends BaseModule {

    public function activate() {
        try {
            if (class_exists('ModuleDB')) {
                ModuleDB::createTable($this->pdo, 'subscribers', "
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ");
            } else {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS subscribers (
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }
            return true;
        } catch (PDOException $e) {
            error_log("Failed to activate Newsletter module: " . $e->getMessage());
            return false;
        }
    }

    public function deactivate() {
        return true;
    }

    public function registerAdminMenu() {
        $pdo = $this->pdo;

        // Admin menu link (modern admin_sidebar_links filter)
        HookRegistry::addFilter('admin_sidebar_links', function(array $sections) use ($pdo) {
            $count = 0;
            try {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
            } catch (\PDOException) {}
            $badge = $count > 0 ? " ({$count})" : "";

            $sections['overview']['links']['newsletter'] = [
                'url'   => SITE_URL . '/admin/module_page.php?m=newsletter',
                'label' => 'Newsletter' . $badge,
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
            ];
            return $sections;
        });

        // Admin page action
        HookRegistry::addAction('admin_page_newsletter', function() {
            require_once __DIR__ . '/admin_newsletter.php';
        });
    }

    public function registerHooks() {
        // Inject Newsletter form into footer
        HookRegistry::addAction('frontend_footer', function() {
            $csrfToken = htmlspecialchars(Csrf::getToken(), ENT_QUOTES, 'UTF-8');
            $subscribeUrl = htmlspecialchars(SITE_URL . '/modules/newsletter/subscribe.php', ENT_QUOTES, 'UTF-8');

            echo <<<HTML
            <div class="bg-gray-800 text-white py-8 border-t-4 border-blue-500">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                        <div class="text-center md:text-left">
                            <h3 class="text-lg font-bold font-serif mb-1">Stay Updated</h3>
                            <p class="text-sm text-gray-400">Subscribe to our newsletter for the latest additions and archive news.</p>
                        </div>
                        <form id="newsletter-form" action="{$subscribeUrl}" method="POST" class="flex w-full md:w-auto min-w-0">
                            <input type="hidden" name="csrf_token" value="{$csrfToken}">
                            
                            <!-- Honeypot -->
                            <div style="display:none;"><input type="text" name="hp_name"></div>
                            
                            <input type="email" name="email" required placeholder="Enter your email" class="flex-grow md:w-64 px-4 py-2 rounded-l-md border-0 text-gray-900 focus:ring-2 focus:ring-blue-500">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-r-md transition">Subscribe</button>
                        </form>
                    </div>
                    <div id="newsletter-msg" class="text-center mt-3 text-sm hidden"></div>
                </div>
            </div>
            <script>
                // Simple AJAX intercept for the newsletter form
                const nlForm = document.getElementById('newsletter-form');
                if (nlForm) {
                    nlForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const form = this;
                        const msgObj = document.getElementById('newsletter-msg');
                        msgObj.classList.remove('hidden', 'text-red-400', 'text-green-400');
                        
                        fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        })
                        .then(res => res.json())
                        .then(data => {
                            msgObj.textContent = data.message;
                            msgObj.classList.add(data.success ? 'text-green-400' : 'text-red-400');
                            if (data.success) form.reset();
                        }).catch(err => {
                            msgObj.textContent = 'Network error. Please try again later.';
                            msgObj.classList.add('text-red-400');
                        });
                    });
                }
            </script>
HTML;
        });
    }
}

// Procedural backward compatibility wrappers
function newsletter_activate() {
    global $pdo;
    $module = new NewsletterModule($pdo, 'newsletter', []);
    return $module->activate();
}

function newsletter_deactivate() {
    return true;
}
