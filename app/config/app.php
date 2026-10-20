<?php
/**
 * College Voting System - Application Configuration & Bootstrap
 */

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');
}

/**
 * Load environment variables from .env file
 */
function loadEnv(string $envFile): void {
    if (!file_exists($envFile)) {
        return;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            if (!array_key_exists($key, $_ENV)) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Load .env or fallback to .env.example
loadEnv(ROOT_PATH . DIRECTORY_SEPARATOR . '.env');
loadEnv(ROOT_PATH . DIRECTORY_SEPARATOR . '.env.example');

// Helper to get environment variable with default
function env(string $key, mixed $default = null): mixed {
    $val = getenv($key);
    if ($val === false) {
        $val = $_ENV[$key] ?? $default;
    }
    if ($val === 'true' || $val === '(true)') return true;
    if ($val === 'false' || $val === '(false)') return false;
    if ($val === 'null' || $val === '(null)') return null;
    return $val;
}

// Timezone setup
$timezone = env('APP_TIMEZONE', 'Asia/Kolkata');
date_default_timezone_set($timezone);

// Error reporting
$isDebug = env('APP_DEBUG', true);
if ($isDebug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Secure session start
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent() && php_sapi_name() !== 'cli') {
            $lifetime = (int)env('SESSION_LIFETIME', 3600);
            $secure = (bool)env('SESSION_SECURE', false);

            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');

            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        @session_start();

        // Implement session timeout tracking
        $now = time();
        $lifetime = (int)env('SESSION_LIFETIME', 3600);
        if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity'] > $lifetime)) {
            session_unset();
            @session_destroy();
            @session_start();
            $_SESSION['timeout_message'] = "Your session has expired due to inactivity. Please log in again.";
        }
        $_SESSION['last_activity'] = $now;
    }
}
