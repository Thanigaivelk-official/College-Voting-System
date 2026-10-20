<?php
/**
 * College Voting System - Authentication & Session Manager
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

class Auth {
    private static ?array $currentUser = null;

    public static function check(): bool {
        initSession();
        return !empty($_SESSION['user_id']);
    }

    public static function id(): ?int {
        initSession();
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function role(): ?string {
        initSession();
        return $_SESSION['user_role'] ?? null;
    }

    public static function isAdmin(): bool {
        return self::role() === 'admin';
    }

    public static function isStudent(): bool {
        return self::role() === 'student';
    }

    /**
     * Get the full authenticated user record
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }

        if (self::$currentUser !== null) {
            return self::$currentUser;
        }

        $userId = self::id();
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            self::logout();
            return null;
        }

        if ($user['role'] === 'student') {
            $stmtStud = $db->prepare("
                SELECT s.*, d.name AS department_name, d.code AS department_code 
                FROM students s 
                LEFT JOIN departments d ON s.department_id = d.id 
                WHERE s.user_id = ?
            ");
            $stmtStud->execute([$userId]);
            $student = $stmtStud->fetch(PDO::FETCH_ASSOC);
            $user['student'] = $student ?: [];
        } elseif ($user['role'] === 'admin') {
            $stmtAdmin = $db->prepare("SELECT * FROM administrators WHERE user_id = ?");
            $stmtAdmin->execute([$userId]);
            $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
            $user['admin'] = $admin ?: [];
        }

        self::$currentUser = $user;
        return self::$currentUser;
    }

    /**
     * Check if IP/Email is currently rate limited
     */
    public static function isRateLimited(PDO $db, string $email, string $ip): bool {
        $maxAttempts = (int)env('MAX_LOGIN_ATTEMPTS', 5);
        $lockoutMinutes = (int)env('LOGIN_LOCKOUT_MINUTES', 15);
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$lockoutMinutes} minutes"));

        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) AS failed_count 
                FROM login_attempts 
                WHERE (email = ? OR ip_address = ?) 
                  AND is_successful = 0 
                  AND attempted_at >= ?
            ");
            $stmt->execute([$email, $ip, $cutoff]);
            $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['failed_count'] ?? 0);
            return $count >= $maxAttempts;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Record a login attempt
     */
    public static function recordLoginAttempt(PDO $db, string $email, string $ip, bool $success): void {
        try {
            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare("
                INSERT INTO login_attempts (email, ip_address, attempted_at, is_successful) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$email, $ip, $now, $success ? 1 : 0]);
        } catch (Exception $e) {
            // Non-blocking log failure
        }
    }

    /**
     * Clear failed attempts upon successful login
     */
    public static function clearLoginAttempts(PDO $db, string $email, string $ip): void {
        try {
            $stmt = $db->prepare("DELETE FROM login_attempts WHERE email = ? OR ip_address = ?");
            $stmt->execute([$email, $ip]);
        } catch (Exception $e) {
            // Non-blocking
        }
    }

    /**
     * Authenticate user with credentials
     */
    public static function login(string $email, string $password): array {
        initSession();
        $db = Database::getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $email = trim(strtolower($email));

        if (self::isRateLimited($db, $email, $ip)) {
            $lockoutMinutes = env('LOGIN_LOCKOUT_MINUTES', 15);
            return [
                'success' => false,
                'error' => "Too many failed login attempts. Please try again in {$lockoutMinutes} minutes."
            ];
        }

        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::recordLoginAttempt($db, $email, $ip, false);
            return [
                'success' => false,
                'error' => 'Invalid email address or password.'
            ];
        }

        if ($user['status'] === 'suspended') {
            return [
                'success' => false,
                'error' => 'Your account has been suspended. Please contact the Election Commission.'
            ];
        }

        // Authentication success - prevent session fixation
        self::$currentUser = null;
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['last_activity'] = time();

        self::recordLoginAttempt($db, $email, $ip, true);
        self::clearLoginAttempts($db, $email, $ip);

        // Audit log
        log_audit($db, (int)$user['id'], $user['email'], 'LOGIN', 'user', (int)$user['id'], 'User logged in successfully');

        return [
            'success' => true,
            'role' => $user['role'],
            'user' => $user
        ];
    }

    /**
     * Log out current user
     */
    public static function logout(): void {
        initSession();
        $userId = self::id();
        $email = $_SESSION['user_email'] ?? 'unknown';

        if ($userId) {
            try {
                $db = Database::getConnection();
                log_audit($db, $userId, $email, 'LOGOUT', 'user', $userId, 'User logged out');
            } catch (Exception $e) {
                // Ignore during teardown
            }
        }

        self::$currentUser = null;
        $_SESSION = [];

        if (!headers_sent() && ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
    }
}
