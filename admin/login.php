<?php
/**
 * College Voting System - Admin Login Redirect
 * Admins use the same login.php as students.
 * This file simply redirects for convenience.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/includes/functions.php';

redirect(public_url('login.php'));
