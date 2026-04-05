<?php
// includes/HookRegistry.php

class HookRegistry {
    private static $actions = [];
    private static $filters = [];

    /**
     * Clear all registered actions and filters.
     * Useful for testing or re-bootstrapping.
     */
    public static function reset(): void {
        self::$actions = [];
        self::$filters = [];
    }

    public static function addAction($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        self::$actions[$tag][$priority][] = [
            'function' => $function_to_add,
            'accepted_args' => $accepted_args
        ];
        ksort(self::$actions[$tag]);
    }

    public static function doAction($tag, ...$args) {
        if (!isset(self::$actions[$tag])) {
            return;
        }

        foreach (self::$actions[$tag] as $priority => $functions) {
            foreach ($functions as $function) {
                if (is_callable($function['function'])) {
                    call_user_func_array($function['function'], array_slice($args, 0, (int)$function['accepted_args']));
                }
            }
        }
    }

    public static function addFilter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        self::$filters[$tag][$priority][] = [
            'function' => $function_to_add,
            'accepted_args' => $accepted_args
        ];
        ksort(self::$filters[$tag]);
    }

    public static function applyFilters($tag, $value, ...$args) {
        if (!isset(self::$filters[$tag])) {
            return $value;
        }

        foreach (self::$filters[$tag] as $priority => $functions) {
            foreach ($functions as $function) {
                if (is_callable($function['function'])) {
                    $passed_args = array_merge([$value], array_slice($args, 0, (int)$function['accepted_args'] - 1));
                    $value = call_user_func_array($function['function'], $passed_args);
                }
            }
        }

        return $value;
    }
}
