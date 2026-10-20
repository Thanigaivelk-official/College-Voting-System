<?php
/**
 * College Voting System - Shared Header Layout
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

initSession();

$currentUser = Auth::user();
$isLoggedIn = Auth::check();
$isAdmin = Auth::isAdmin();
$isStudent = Auth::isStudent();
$flash = get_flash();

// Unread notifications count
$unreadNotifications = 0;
if ($isLoggedIn && $currentUser) {
    try {
        $db = Database::getConnection();
        $stmtNotif = $db->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmtNotif->execute([(int)$currentUser['id']]);
        $unreadNotifications = (int)($stmtNotif->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    } catch (Exception $e) {
        $unreadNotifications = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'College Voting System') ?> | Secure Student Council Elections</title>
    
    <!-- Bootstrap 5.3 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom Application CSS -->
    <link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>

<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark app-navbar py-2 sticky-top">
    <div class="container-fluid px-3 px-md-4">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= base_url('index.php') ?>">
            <i class="bi bi-box2-check-fill text-warning fs-4"></i>
            <span>College Voting System</span>
            <span class="navbar-brand-badge d-none d-sm-inline-block">Academic Portal</span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#appNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="appNav">
            <ul class="navbar-nav ms-auto align-items-center gap-1 gap-lg-2">
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('elections.php') ?>"><i class="bi bi-calendar3 me-1"></i> Elections</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('results.php') ?>"><i class="bi bi-trophy me-1"></i> Results</a>
                </li>

                <?php if ($isLoggedIn): ?>
                    <!-- Notification Bell -->
                    <li class="nav-item">
                        <a class="nav-link position-relative px-2" href="<?= base_url('notifications.php') ?>" title="Notifications">
                            <i class="bi bi-bell fs-5"></i>
                            <?php if ($unreadNotifications > 0): ?>
                                <span class="position-absolute top-1 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                                    <?= $unreadNotifications ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <!-- User Profile Dropdown -->
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 text-white" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                <i class="bi <?= $isAdmin ? 'bi-shield-check' : 'bi-person-fill' ?>"></i>
                            </div>
                            <span class="d-none d-md-inline"><?= e($currentUser['admin']['full_name'] ?? $currentUser['student']['full_name'] ?? $currentUser['email']) ?></span>
                            <span class="badge bg-<?= $isAdmin ? 'danger' : 'primary' ?> text-uppercase ms-1" style="font-size: 0.65rem;">
                                <?= e($currentUser['role']) ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <?php if ($isAdmin): ?>
                                <li><a class="dropdown-item" href="<?= base_url('../admin/dashboard.php') ?>"><i class="bi bi-speedometer2 me-2"></i> Admin Dashboard</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('../admin/elections.php') ?>"><i class="bi bi-sliders me-2"></i> Manage Elections</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('../admin/students.php') ?>"><i class="bi bi-people me-2"></i> Manage Students</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="<?= base_url('dashboard.php') ?>"><i class="bi bi-speedometer2 me-2"></i> Voter Dashboard</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('profile.php') ?>"><i class="bi bi-person me-2"></i> My Profile</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('history.php') ?>"><i class="bi bi-clock-history me-2"></i> Voting History</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= base_url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-outline-light btn-sm px-3" href="<?= base_url('login.php') ?>">Sign In</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-warning btn-sm px-3 fw-semibold text-dark" href="<?= base_url('register.php') ?>">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Global Flash Alert Notification -->
<?php if ($flash): ?>
    <div class="container-fluid px-3 px-md-4 mt-3">
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : ($flash['type'] === 'danger' ? 'bi-exclamation-octagon-fill' : 'bi-info-circle-fill') ?> fs-5"></i>
                <div><?= e($flash['message']) ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>
