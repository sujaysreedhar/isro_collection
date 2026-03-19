<?php
// includes/ThemeManager.php

class ThemeManager {
    /**
     * Retrieves the currently active theme slug from settings.
     * Defaults to 'default'.
     */
    public static function getActiveTheme(): string {
        global $appSettings;
        return $appSettings['active_theme'] ?? 'default';
    }

    /**
     * Resolves the absolute path to a template file.
     * Checks the active theme first; if missing, falls back to the default theme.
     * 
     * @param string $templateName e.g. 'index.php', 'header.php', 'partials/card.php'
     * @return string Absolute path to the resolved template
     * @throws \Exception If the template does not exist in active or default themes.
     */
    public static function getTemplatePath(string $templateName): string {
        $activeTheme = self::getActiveTheme();
        $baseDir = realpath(__DIR__ . '/../themes') . DIRECTORY_SEPARATOR;
        
        if (!$baseDir) {
            // In case themes directory doesn't even exist yet
            $baseDir = __DIR__ . '/../themes/';
        } else {
            $baseDir .= DIRECTORY_SEPARATOR;
        }

        // 1. Check active theme
        $activePath = $baseDir . $activeTheme . DIRECTORY_SEPARATOR . $templateName;
        if (file_exists($activePath)) {
            return $activePath;
        }

        // 2. Fallback to 'default' theme
        $fallbackPath = $baseDir . 'default' . DIRECTORY_SEPARATOR . $templateName;
        if (file_exists($fallbackPath)) {
            return $fallbackPath;
        }

        throw new \Exception("Template '{$templateName}' not found in active theme '{$activeTheme}' nor in 'default' fallback.");
    }

    /**
     * Helper to get the header template path
     */
    public static function getHeader(): string {
        return self::getTemplatePath('header.php');
    }

    /**
     * Helper to get the footer template path
     */
    public static function getFooter(): string {
        return self::getTemplatePath('footer.php');
    }
}
