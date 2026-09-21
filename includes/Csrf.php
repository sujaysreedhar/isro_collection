<?php
// includes/Csrf.php
// Centralized Cross-Site Request Forgery (CSRF) protection service.

class Csrf {
    /**
     * Ensure PHP session is active before reading/writing tokens.
     */
    public static function initSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!headers_sent()) {
                $httpsEnabled = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                @ini_set('session.cookie_httponly', '1');
                @ini_set('session.cookie_secure', $httpsEnabled ? '1' : '0');
                @ini_set('session.cookie_samesite', 'Lax');
                @session_start();
            }
        }
    }

    /**
     * Retrieve or generate the current CSRF token from the session.
     */
    public static function getToken(): string {
        self::initSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify a supplied CSRF token against the session token.
     */
    public static function verify(?string $token): bool {
        self::initSession();
        return isset($_SESSION['csrf_token'])
            && is_string($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Render a hidden HTML input containing the CSRF token.
     */
    public static function input(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

// Global backward-compatibility helper functions
if (!function_exists('ensureCsrfToken')) {
    function ensureCsrfToken(): string {
        return Csrf::getToken();
    }
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken(?string $token): bool {
        return Csrf::verify($token);
    }
}

if (!function_exists('moduleCsrfAllowed')) {
    function moduleCsrfAllowed(string $method, array $post, array $server): bool {
        if (strtoupper($method) !== 'POST') return true;
        $supplied = $post['csrf_token'] ?? $server['HTTP_X_CSRF_TOKEN'] ?? null;
        return verifyCsrfToken(is_string($supplied) ? $supplied : null);
    }
}
