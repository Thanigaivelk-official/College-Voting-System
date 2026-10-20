<?php
// ============================================================
// app/config/database.php
// PDO Database Connection Singleton — College Voting System
// PHP 8.x | MySQL 8.x | InnoDB | utf8mb4
// ============================================================

declare(strict_types=1);

/**
 * Database connection singleton class.
 *
 * All application files call Database::getConnection() to obtain
 * a shared PDO instance. Credentials are read from environment
 * variables loaded by app/config/app.php (which processes .env).
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Return the singleton PDO connection.
     *
     * Throws RuntimeException on failure so callers can show a
     * safe error page without leaking credentials or stack traces.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host    = self::env('DB_HOST',    'localhost');
        $port    = self::env('DB_PORT',    '3306');
        $dbname  = self::env('DB_NAME',    'college_voting');
        $user    = self::env('DB_USER',    'root');
        $pass    = self::env('DB_PASS',    '');
        $charset = self::env('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Force correct timezone in every connection
            PDO::MYSQL_ATTR_INIT_COMMAND =>
                "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
        ];

        try {
            self::$instance = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Log the real message server-side; show nothing sensitive to visitors
            error_log('[CVS Database] Connection failed: ' . $e->getMessage());
            throw new RuntimeException(
                'Unable to connect to the database. Please check your configuration or contact the administrator.'
            );
        }

        return self::$instance;
    }

    /**
     * Retrieve an environment variable, falling back to a default.
     * Reads from putenv / $_ENV in case app.php has not loaded yet.
     */
    private static function env(string $key, string $default = ''): string
    {
        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }
        return $_ENV[$key] ?? $default;
    }

    /** Prevent direct instantiation */
    private function __construct() {}
}
