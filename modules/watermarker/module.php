<?php
class WatermarkerModule extends BaseModule {

    public function boot() {
        // Register the watermarking filter
        HookRegistry::addFilter('after_image_resize', [$this, 'applyWatermark'], 10, 2);

        // Add settings link to the admin sidebar
        HookRegistry::addAction('admin_menu', function() {
            $url = SITE_URL . '/admin/module_page.php?m=watermarker';
            echo '<a href="' . $url . '" class="sidebar-link text-slate-300">Watermark Settings</a>';
        });

        // Register the admin settings page
        HookRegistry::addAction('admin_page_watermarker', [$this, 'renderSettingsPage']);
    }

    public function applyWatermark($img, $folder) {
        $enabled = $this->getSetting('watermark_enabled', '1');
        if ($enabled !== '1' || $folder !== 'display') {
            return $img;
        }

        $text = $this->getSetting('watermark_text', '© ' . date('Y') . ' ' . (defined('SITE_TITLE') ? SITE_TITLE : 'Archival Collection'));
        $opacity = (int)$this->getSetting('watermark_opacity', '50'); // 0-127 in GD (inverted alpha)
        
        $font = 5;
        $width = imagesx($img);
        $height = imagesy($img);
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);

        $x = $width - $textWidth - 20;
        $y = $height - $textHeight - 20;
        if ($x < 0) $x = 10;
        if ($y < 0) $y = 10;

        $shadowColor = imagecolorallocatealpha($img, 0, 0, 0, 60);
        $textColor = imagecolorallocatealpha($img, 255, 255, 255, $opacity);
        
        imagealphablending($img, true);
        imagestring($img, $font, $x + 1, $y + 1, $text, $shadowColor);
        imagestring($img, $font, $x, $y, $text, $textColor);

        return $img;
    }

    public function renderSettingsPage() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_watermark'])) {
            if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) die('Invalid CSRF token.');

            $this->setSetting('watermark_enabled', isset($_POST['watermark_enabled']) ? '1' : '0');
            $this->setSetting('watermark_text', trim($_POST['watermark_text']));
            $this->setSetting('watermark_opacity', (int)$_POST['watermark_opacity']);
            echo '<div class="mb-4 p-3 bg-green-100 text-green-700 rounded-md">Settings updated successfully!</div>';
        }

        $enabled = $this->getSetting('watermark_enabled', '1');
        $text = $this->getSetting('watermark_text', '© ' . date('Y') . ' ' . (defined('SITE_TITLE') ? SITE_TITLE : 'Archival Collection'));
        $opacity = $this->getSetting('watermark_opacity', '50');

        ?>
        <form method="POST" class="space-y-6 max-w-2xl">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensureCsrfToken()) ?>">
            <input type="hidden" name="save_watermark" value="1">

            <div>
                <label class="flex items-center gap-3 cursor-pointer group">
                    <div class="relative">
                        <input type="checkbox" name="watermark_enabled" value="1" <?= $enabled === '1' ? 'checked' : '' ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </div>
                    <span class="text-sm font-medium text-gray-900">Enable Image Watermarking</span>
                </label>
                <p class="text-xs text-gray-500 mt-2 ml-14">Apply text overlay to high-resolution display images during upload.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Watermark Text</label>
                <input type="text" name="watermark_text" value="<?= htmlspecialchars($text) ?>" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                <p class="text-xs text-gray-400 mt-2">Example: © <?= date('Y') ?> My Archive</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Watermark Opacity (0-127)</label>
                <div class="flex items-center gap-4">
                    <input type="range" name="watermark_opacity" min="0" max="127" value="<?= htmlspecialchars($opacity) ?>" 
                           class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer" oninput="this.nextElementSibling.value = this.value">
                    <output class="text-sm font-mono text-gray-600 w-8"><?= htmlspecialchars($opacity) ?></output>
                </div>
                <p class="text-xs text-gray-400 mt-2">0 is fully opaque, 127 is fully transparent. Recommended: 40-70.</p>
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-all shadow-md active:scale-[0.98]">
                    Save Watermark Settings
                </button>
            </div>
        </form>
        <?php
    }

    private function getSetting($key, $default = '') {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        return $stmt->fetchColumn() ?: $default;
    }

    private function setSetting($key, $value) {
        $stmt = $this->pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
}
