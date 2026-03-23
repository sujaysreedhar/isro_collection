<?php
// modules/newsletter/module.php

function newsletter_activate() {
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS subscribers (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return true;
    } catch (PDOException $e) {
        error_log("Failed to activate Newsletter module: " . $e->getMessage());
        return false;
    }
}

function newsletter_deactivate() { return true; }

// Inject Newsletter form into footer
HookRegistry::addAction('frontend_footer', function() {
    $csrf = $_SESSION['csrf_token'] ?? '';
    echo <<<HTML
    <div class="bg-gray-800 text-white py-8 border-t-4 border-blue-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="text-center md:text-left">
                    <h3 class="text-lg font-bold font-serif mb-1">Stay Updated</h3>
                    <p class="text-sm text-gray-400">Subscribe to our newsletter for the latest additions and archive news.</p>
                </div>
                <form action="<?= SITE_URL ?>/modules/newsletter/subscribe.php" method="POST" class="flex w-full md:w-auto min-w-0">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    
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
        document.querySelector('form[action="<?= SITE_URL ?>/modules/newsletter/subscribe.php"]').addEventListener('submit', function(e) {
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
    </script>
HTML;
});

// Admin menu link
HookRegistry::addFilter('admin_menu', function($menu) {
    global $pdo;
    $count = $pdo->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
    $badge = $count > 0 ? " <span class='bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-full ml-auto'>{$count}</span>" : "";
    
    $menu[] = [
        'url' => 'module_page.php?m=newsletter',
        'icon' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>',
        'label' => 'Newsletter' . $badge,
        'active' => isset($_GET['m']) && $_GET['m'] === 'newsletter'
    ];
    return $menu;
});

// Admin route
HookRegistry::addFilter('admin_module_page_newsletter', function($file) {
    return __DIR__ . '/admin_newsletter.php';
});
