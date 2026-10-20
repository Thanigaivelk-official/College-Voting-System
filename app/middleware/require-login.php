<?php
/**
 * College Voting System - Login Required Middleware
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

initSession();

if (!Auth::check()) {
    set_flash('warning', 'Please log in to access this page.');
    redirect(public_url('login.php'));
}
