<?php
// includes/AssetManager.php

class AssetManager {
    // Resolved lazily so CACHE_DIR and SITE_URL constants are available at call time.
    private static function cacheDir(): string { return CACHE_DIR . '/assets'; }
    private static function cacheUrl(): string { return SITE_URL . '/includes/cache/assets'; }

    /**
     * Render a <link> tag for minified CSS.
     */
    public static function renderStyles(array $files): string {
        if (AppConfig::get('enable_cache', '1') === '0') {
            $html = '';
            foreach ($files as $file) {
                $version = file_exists(__DIR__ . '/../' . $file) ? filemtime(__DIR__ . '/../' . $file) : time();
                $html .= '<link rel="stylesheet" href="' . SITE_URL . '/' . ltrim($file, '/') . '?v=' . $version . '">' . "\n";
            }
            return $html;
        }

        $combinedFile = self::combineAndMinify($files, 'css');
        if (!$combinedFile) return '';
        
        $version = filemtime(self::cacheDir() . '/' . $combinedFile);
        return '<link rel="stylesheet" href="' . self::cacheUrl() . '/' . $combinedFile . '?v=' . $version . '">' . "\n";
    }

    /**
     * Render a <script> tag for minified JS.
     */
    public static function renderScripts(array $files): string {
        if (AppConfig::get('enable_cache', '1') === '0') {
            $html = '';
            foreach ($files as $file) {
                $version = file_exists(__DIR__ . '/../' . $file) ? filemtime(__DIR__ . '/../' . $file) : time();
                $html .= '<script src="' . SITE_URL . '/' . ltrim($file, '/') . '?v=' . $version . '"></script>' . "\n";
            }
            return $html;
        }

        $combinedFile = self::combineAndMinify($files, 'js');
        if (!$combinedFile) return '';

        $version = filemtime(self::cacheDir() . '/' . $combinedFile);
        return '<script src="' . self::cacheUrl() . '/' . $combinedFile . '?v=' . $version . '"></script>' . "\n";
    }

    /**
     * Combine and minify files, returning the cache filename.
     */
    private static function combineAndMinify(array $files, string $type): ?string {
        if (empty($files)) return null;

        $cacheDir = self::cacheDir();
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Generate a unique hash for this set of files
        $hash = md5(implode('|', $files));
        $cacheFilename = $hash . '.' . $type;
        $cachePath = $cacheDir . '/' . $cacheFilename;

        // Check if cache needs refresh (if any source file is newer than cache)
        $needsRefresh = !file_exists($cachePath);
        if (!$needsRefresh) {
            $cacheTime = filemtime($cachePath);
            foreach ($files as $file) {
                $fullPath = __DIR__ . '/../' . ltrim($file, '/');
                if (file_exists($fullPath) && filemtime($fullPath) > $cacheTime) {
                    $needsRefresh = true;
                    break;
                }
            }
        }

        if ($needsRefresh) {
            $content = '';
            foreach ($files as $file) {
                $fullPath = __DIR__ . '/../' . ltrim($file, '/');
                if (file_exists($fullPath)) {
                    $content .= file_get_contents($fullPath) . "\n";
                }
            }

            $minified = ($type === 'css') ? self::minifyCss($content) : self::minifyJs($content);
            file_put_contents($cachePath, $minified);
        }

        return $cacheFilename;
    }

    private static function minifyCss(string $css): string {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        // Remove spaces around special characters
        $css = preg_replace('/\s*([{}|:;,])\s*/', '$1', $css);
        // Remove whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        return trim($css);
    }

    private static function minifyJs(string $js): string {
        // Very basic JS minification (remove comments and extra whitespace)
        // Note: This is a simple regex minifier, not a full parser.
        $js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js); // Block comments
        $js = preg_replace('!//.*!', '', $js); // Line comments
        $js = preg_replace('/\s+/', ' ', $js); // Collapse whitespace
        return trim($js);
    }
}
