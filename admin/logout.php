<?php
/**
 * College Voting System - Admin Logout Endpoint
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/functions.php';

Auth::logout();
set_flash('info', 'You have been signed out successfully.');
redirect(public_url('login.php'));
