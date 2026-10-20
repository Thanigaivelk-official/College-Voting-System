<?php
/**
 * College Voting System - CSRF Protection Utilities
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

function csrf_token(): string {
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function validate_csrf(?string $token = null): bool {
    initSession();
    $submittedToken = $token ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $sessionToken = $_SESSION['csrf_token'] ?? null;

    if (empty($submittedToken) || empty($sessionToken)) {
        return false;
    }

    return hash_equals($sessionToken, $submittedToken);
}

function require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validate_csrf()) {
            http_response_code(403);
            die('Invalid or expired CSRF security token. Please refresh the page and try again.');
        }
    }
}
