<?php
/**
 * College Voting System - Student Role Required Middleware
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

initSession();

if (!Auth::check()) {
    set_flash('warning', 'Please log in as a student to access this page.');
    redirect(public_url('login.php'));
}

if (!Auth::isStudent()) {
    http_response_code(403);
    require_once __DIR__ . '/../views/errors/403.php';
    exit;
}
