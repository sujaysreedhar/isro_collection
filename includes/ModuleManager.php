<?php
// includes/ModuleManager.php

class ModuleManager {
    private $pdo;
    private $modulesDir;
    private $activeModulesSlugs = [];
    private $loadedModules = [];

    public function __construct(PDO $pdo, string $modulesDir, array $activeModulesSlugs = []) {
        $this->pdo = $pdo;
        $this->modulesDir = rtrim($modulesDir, '/\\');
        $this->activeModulesSlugs = $activeModulesSlugs;
    }

    /**
     * Scan the modules directory for available modules and their metadata.
     */
    public function discoverModules(bool $forceRefresh = false): array {
        global $appSettings;
        $enableCache = ($appSettings['enable_cache'] ?? '1') === '1';
        $cacheFile = __DIR__ . '/cache/modules_metadata.json';

        if (!$forceRefresh && $enableCache && file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                // Update active status as it might have changed in appSettings
                foreach ($cached as $slug => &$meta) {
                    $meta['is_active'] = in_array($slug, $this->activeModulesSlugs);
                }
                return $cached;
            }
        }

        $available = [];
        if (!is_dir($this->modulesDir)) return [];

        $dirs = array_filter(glob($this->modulesDir . '/*'), 'is_dir');
        foreach ($dirs as $dir) {
            $slug = basename($dir);
            $metaFile = $dir . '/module.json';
            $legacyFile = $dir . '/module.php';

            $metadata = [
                'name' => $slug,
                'description' => '',
                'version' => '1.0',
                'author' => 'Unknown'
            ];

            if (file_exists($metaFile)) {
                $content = json_decode(file_get_contents($metaFile), true);
                if ($content) {
                    $metadata = array_merge($metadata, $content);
                }
            } elseif (file_exists($legacyFile)) {
                // Fallback to reading file header comments
                $content = file_get_contents($legacyFile, false, null, 0, 1000);
                if (preg_match('/Module Name:\s*(.*)/i', $content, $m)) $metadata['name'] = trim($m[1]);
                if (preg_match('/Description:\s*(.*)/i', $content, $m)) $metadata['description'] = trim($m[1]);
                if (preg_match('/Version:\s*(.*)/i', $content, $m)) $metadata['version'] = trim($m[1]);
                if (preg_match('/Author:\s*(.*)/i', $content, $m)) $metadata['author'] = trim($m[1]);
            } else {
                continue; // Not a valid module folder
            }

            $metadata['slug'] = $slug;
            $metadata['is_active'] = in_array($slug, $this->activeModulesSlugs);
            $available[$slug] = $metadata;
        }

        file_put_contents($cacheFile, json_encode($available));
        return $available;
    }

    public function clearCache(): void {
        $cacheFile = __DIR__ . '/cache/modules_metadata.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    /**
     * Boot all active modules.
     */
    public function bootActiveModules() {
        foreach ($this->activeModulesSlugs as $slug) {
            $this->loadModule($slug);
        }
    }

    /**
     * Load and instantiate a specific module.
     */
    public function loadModule(string $slug): ?BaseModule {
        if (isset($this->loadedModules[$slug])) {
            return $this->loadedModules[$slug];
        }

        $modulePath = $this->modulesDir . '/' . $slug;
        $entryFile = $modulePath . '/module.php';

        if (!file_exists($entryFile)) return null;

        require_once $entryFile;

        // Determine class name (StudyCase or original slug)
        // For simplicity, we'll try [Slug]Module (e.g. TradeManagerModule)
        $className = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug))) . 'Module';
        
        if (class_exists($className)) {
            $metadata = $this->getModuleMetadata($slug);
            $instance = new $className($this->pdo, $slug, $metadata);
            if ($instance instanceof BaseModule) {
                $instance->boot();
                $this->loadedModules[$slug] = $instance;
                return $instance;
            }
        }

        return null;
    }

    private function getModuleMetadata(string $slug): array {
        $all = $this->discoverModules();
        return $all[$slug] ?? ['slug' => $slug];
    }
}
