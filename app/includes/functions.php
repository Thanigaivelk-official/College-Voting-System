<?php
/**
 * College Voting System - Core Helper Functions
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Escape HTML output for XSS prevention
 */
function e(?string $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect safely
 */
function redirect(string $path): void {
    header("Location: {$path}");
    exit;
}

/**
 * Flash messaging system
 */
function set_flash(string $type, string $message): void {
    initSession();
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash(): ?array {
    initSession();
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Get base URL for asset and page linking
 */
function base_url(string $path = ''): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    // Check if running from root or public folder
    $base = rtrim(dirname($scriptName), '/\\');
    
    // Normalize path
    $trimmedPath = ltrim($path, '/');
    if ($trimmedPath === '') {
        return $base ?: '/';
    }
    
    return ($base === '' || $base === '/') ? '/' . $trimmedPath : $base . '/' . $trimmedPath;
}

/**
 * Format datetime strings nicely
 */
function format_datetime(?string $datetime, string $format = 'M d, Y h:i A'): string {
    if (!$datetime) {
        return 'N/A';
    }
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Generate a non-sensitive verification receipt hash for voters
 */
function generate_receipt_hash(int $electionId, int $studentId): string {
    $secret = env('CSRF_SECRET_KEY', 'college_voting_salt_2026');
    $data = "ELEC_{$electionId}_STUD_{$studentId}_{$secret}_" . microtime();
    return strtoupper(substr(hash('sha256', $data), 0, 16));
}

/**
 * Sanitize text to prevent CSV Excel Formula Injection
 */
function sanitize_csv_field(string $field): string {
    $firstChar = substr($field, 0, 1);
    if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $field;
    }
    return $field;
}

/**
 * Record an administrative audit log entry
 */
function log_audit(
    PDO $db,
    ?int $userId,
    string $actorEmail,
    string $action,
    string $objectType,
    ?int $objectId,
    string $summary
): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, actor_email, action, object_type, object_id, summary, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $actorEmail, $action, $objectType, $objectId, $summary, $ip]);
    } catch (PDOException $e) {
        // Fallback for databases where NOW() is datetime(now) in SQLite
        try {
            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare("
                INSERT INTO audit_logs (user_id, actor_email, action, object_type, object_id, summary, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $actorEmail, $action, $objectType, $objectId, $summary, $ip, $now]);
        } catch (Exception $inner) {
            error_log("Failed to write audit log: " . $inner->getMessage());
        }
    }
}

/**
 * Create an in-app notification
 */
function create_notification(
    PDO $db,
    int $userId,
    string $title,
    string $message,
    string $type = 'info'
): void {
    try {
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
            VALUES (?, ?, ?, ?, 0, ?)
        ");
        $stmt->execute([$userId, $title, $message, $type, $now]);
    } catch (Exception $e) {
        error_log("Failed to insert notification: " . $e->getMessage());
    }
}
