<?php
// f:\xampp\htdocs\collection\tmp\debug_hooks.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/HookRegistry.php';
require_once __DIR__ . '/../includes/ModuleManager.php';

// The HookRegistry class use private static variables, so I can't easily dump them unless I add a debug method
// or use Reflection. Let's use Reflection.

echo "--- ACTIVE MODULES ---\n";
print_r($activeModulesSlugs);

echo "\n--- REGISTERED ACTIONS ---\n";
$ref = new ReflectionClass('HookRegistry');
$prop = $ref->getProperty('actions');
$prop->setAccessible(true);
$actions = $prop->getValue();

if (isset($actions['home_page_sections'])) {
    echo "Hook 'home_page_sections' has registered actions:\n";
    foreach ($actions['home_page_sections'] as $priority => $functions) {
        foreach ($functions as $f) {
            if (is_array($f['function'])) {
                echo " - Priority $priority: " . get_class($f['function'][0]) . "->" . $f['function'][1] . "\n";
            } else {
                echo " - Priority $priority: (anonymous/string function)\n";
            }
        }
    }
} else {
    echo "Hook 'home_page_sections' is NOT registered in HookRegistry.\n";
}

echo "\n--- THEME TEMPLATE PATHS ---\n";
try {
    echo "Header: " . ThemeManager::getHeader() . "\n";
    echo "Index: " . ThemeManager::getTemplatePath('index.php') . "\n";
    echo "Footer: " . ThemeManager::getFooter() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
