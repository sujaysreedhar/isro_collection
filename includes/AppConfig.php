<?php
// includes/AppConfig.php

/**
 * AppConfig — Lightweight static registry for application settings.
 *
 * Eliminates the need for `global $appSettings` inside class methods.
 * Loaded once during bootstrap via AppConfig::load($appSettings).
 *
 * Usage:
 *   AppConfig::get('enable_cache', '1');
 *   AppConfig::all();
 */
class AppConfig {
    private static array $data = [];
    private static bool  $loaded = false;

    /**
     * Load the settings array (called once in config/config.php).
     */
    public static function load(array $settings): void {
        self::$data   = $settings;
        self::$loaded = true;
    }

    /**
     * Retrieve a single setting value with an optional default.
     */
    public static function get(string $key, mixed $default = null): mixed {
        return self::$data[$key] ?? $default;
    }

    /**
     * Return the full settings array.
     */
    public static function all(): array {
        return self::$data;
    }

    /**
     * Check whether settings have been loaded.
     */
    public static function isLoaded(): bool {
        return self::$loaded;
    }
}
