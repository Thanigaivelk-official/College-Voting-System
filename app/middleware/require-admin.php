<?php
/**
 * College Voting System - Admin Role Required Middleware
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

initSession();

if (!Auth::check()) {
    set_flash('warning', 'Please log in with administrator credentials.');
    redirect(public_url('login.php'));
}

if (!Auth::isAdmin()) {
    http_response_code(403);
    require_once __DIR__ . '/../views/errors/403.php';
    exit;
}
