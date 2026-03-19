<?php
// modules/user_galleries/module.php

class UserGalleriesModule extends BaseModule {
    
    public function boot() {
        // 1. Inject User Galleries link into frontend navigation
        HookRegistry::addFilter('frontend_nav_links', function($links) {
            $links['user_galleries'] = ['url' => SITE_URL . '/modules/user_galleries/my_galleries.php', 'label' => 'My Galleries'];
            return $links;
        });

        // 2. Inject CSS/JS into frontend head
        HookRegistry::addAction('frontend_head', function() {
            echo '<style>
                .gallery-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 500; font-size: 0.875rem; background-color: #f3f4f6; color: #374151; transition: all 0.2s; border: 1px solid #d1d5db; cursor: pointer; }
                .gallery-btn:hover { background-color: #e5e7eb; color: #111827; }
                /* Modal Styles */
                #ug-modal { display: none; position: fixed; inset: 0; z-index: 100; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
                #ug-modal.active { display: flex; }
                #ug-modal-content { background: white; padding: 1.5rem; border-radius: 0.75rem; width: 100%; max-width: 24rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
            </style>';
            echo '<script src="' . SITE_URL . '/modules/user_galleries/gallery.js" defer></script>';
            echo '<script>const SITE_URL = "' . SITE_URL . '";</script>';
        });

        // 3. Inject Add to Gallery button on item detail pages
        HookRegistry::addAction('item_after_content', function($item) {
            if (!$item) return;
            $itemId = htmlspecialchars($item['id']);
            echo '<div class="mt-8 pt-6 border-t border-gray-200">';
            echo '  <button type="button" class="gallery-btn" onclick="openGalleryModal(' . $itemId . ')">';
            echo '    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>';
            echo '    Add to Gallery';
            echo '  </button>';
            echo '</div>';
            
            // Render the hidden modal
            echo '<div id="ug-modal">';
            echo '  <div id="ug-modal-content">';
            echo '    <div class="flex justify-between items-center mb-4">';
            echo '      <h3 class="text-lg font-bold text-gray-900">Add to Gallery</h3>';
            echo '      <button type="button" onclick="closeGalleryModal()" class="text-gray-400 hover:text-gray-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>';
            echo '    </div>';
            echo '    <div id="ug-modal-body" class="space-y-4">';
            echo '      <p class="text-sm text-gray-500 text-center py-4">Loading your galleries...</p>';
            echo '    </div>';
            echo '  </div>';
            echo '</div>';
        });
    }

    public function activate() {
        $schemaGalleries = "
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_token VARCHAR(64) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            share_token VARCHAR(32) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE INDEX (share_token),
            INDEX (user_token)
        ";
        
        $schemaGalleryItems = "
            id INT AUTO_INCREMENT PRIMARY KEY,
            gallery_id INT NOT NULL,
            item_id INT DEFAULT NULL,
            media_id INT DEFAULT NULL,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (gallery_id),
            INDEX (item_id)
        ";
        
        if (class_exists('ModuleDB')) {
            ModuleDB::createTable($this->pdo, 'user_galleries', $schemaGalleries);
            ModuleDB::createTable($this->pdo, 'user_gallery_items', $schemaGalleryItems);
        }
    }
}
