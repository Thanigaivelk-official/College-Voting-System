<?php
/**
 * College Voting System - Student Sidebar Navigation
 */

declare(strict_types=1);

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<div class="col-lg-3 col-xl-2 p-0 app-sidebar d-none d-lg-block">
    <div class="p-3">
        <div class="text-uppercase text-muted fw-bold small mb-2 px-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">
            Voter Menu
        </div>
        <nav class="nav flex-column gap-1">
            <a href="<?= base_url('dashboard.php') ?>" class="sidebar-link <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="<?= base_url('elections.php') ?>" class="sidebar-link <?= in_array($currentScript, ['elections.php', 'election-details.php', 'cast-vote.php'], true) ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i> Active Elections
            </a>
            <a href="<?= base_url('history.php') ?>" class="sidebar-link <?= $currentScript === 'history.php' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> Participation History
            </a>
            <a href="<?= base_url('results.php') ?>" class="sidebar-link <?= $currentScript === 'results.php' ? 'active' : '' ?>">
                <i class="bi bi-trophy"></i> Election Results
            </a>
            <a href="<?= base_url('notifications.php') ?>" class="sidebar-link <?= $currentScript === 'notifications.php' ? 'active' : '' ?>">
                <i class="bi bi-bell"></i> Notifications
            </a>
            <a href="<?= base_url('profile.php') ?>" class="sidebar-link <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person-badge"></i> My Profile
            </a>
        </nav>

        <hr class="my-3 text-muted">

        <div class="px-2">
            <a href="<?= base_url('logout.php') ?>" class="sidebar-link text-danger">
                <i class="bi bi-box-arrow-right"></i> Sign Out
            </a>
        </div>
    </div>
</div>
