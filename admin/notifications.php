<?php
/**
 * College Voting System - Admin Notifications Redirect
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/includes/functions.php';

redirect(public_url('notifications.php'));
