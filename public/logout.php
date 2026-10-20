<?php
/**
 * College Voting System - Logout Controller
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/functions.php';

initSession();
Auth::logout();

set_flash('info', 'You have been securely logged out.');
redirect(base_url('login.php'));
