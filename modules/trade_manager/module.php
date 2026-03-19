<?php
// modules/trade_manager/module.php

class TradeManagerModule extends BaseModule {
    
    public function boot() {
        // 2. Admin Menu
        HookRegistry::addAction('admin_menu', function() {
            echo '<a href="' . SITE_URL . '/admin/module_page.php?m=trade_manager&page=requests" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">Trade Requests</a>';
        });

        HookRegistry::addAction('admin_page_trade_manager', function() {
            $page = $_GET['page'] ?? 'requests';
            if ($page === 'requests') {
                require_once __DIR__ . '/admin_trades.php';
            }
        });

        // 3. Frontend Form Injection
        HookRegistry::addAction('item_after_content', function($item) {
            // Only show if the item allows trades
            if (!isset($item['allow_trade']) || $item['allow_trade'] == 0) {
                return;
            }
            ?>
            <section id="trade-request" class="mt-12 bg-gray-50 rounded-xl p-8 border border-gray-200">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Request a Trade</h2>
                
                <?php if (isset($_GET['trade_success'])): ?>
                <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-sm" role="alert">
                    <p class="font-bold">Success!</p>
                    <p>Your trade request has been submitted successfully. The collector will review it shortly.</p>
                </div>
                <?php endif; ?>

                <p class="text-gray-600 mb-6">Interested in this item? Send a trade request to the collector.</p>
                
                <form action="<?= SITE_URL ?>/modules/trade_manager/process_trade.php" method="POST" class="space-y-4">
                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Your Name</label>
                            <input type="text" name="name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <input type="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                        <textarea name="message" rows="4" placeholder="Tell us what you have to trade..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"></textarea>
                    </div>
                    <button type="submit" class="bg-gray-900 text-white px-8 py-3 rounded-lg font-bold hover:bg-gray-800 transition-colors">
                        Submit Request
                    </button>
                </form>
            </section>
            <?php
        }, 10, 1);
    }

    public function activate() {
        $schemaDef = "
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            message TEXT,
            status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (item_id)
        ";
        
        if (class_exists('ModuleDB')) {
            ModuleDB::createTable($this->pdo, 'trade_requests', $schemaDef);
        }
    }
}
