<?php
/**
 * College Voting System - Admin Sidebar Navigation
 */

declare(strict_types=1);

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<div class="col-lg-3 col-xl-2 p-0 app-sidebar d-none d-lg-block">
    <div class="p-3">
        <div class="text-uppercase text-muted fw-bold small mb-2 px-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">
            Commission Controls
        </div>
        <nav class="nav flex-column gap-1">
            <a href="<?= base_url('dashboard.php') ?>" class="sidebar-link <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="<?= base_url('elections.php') ?>" class="sidebar-link <?= in_array($currentScript, ['elections.php', 'election-form.php'], true) ? 'active' : '' ?>">
                <i class="bi bi-calendar-event"></i> Elections
            </a>
            <a href="<?= base_url('candidates.php') ?>" class="sidebar-link <?= in_array($currentScript, ['candidates.php', 'candidate-form.php'], true) ? 'active' : '' ?>">
                <i class="bi bi-person-lines-fill"></i> Candidates
            </a>
            <a href="<?= base_url('students.php') ?>" class="sidebar-link <?= $currentScript === 'students.php' ? 'active' : '' ?>">
                <i class="bi bi-mortarboard"></i> Student Roster
            </a>
            <a href="<?= base_url('eligibility.php') ?>" class="sidebar-link <?= $currentScript === 'eligibility.php' ? 'active' : '' ?>">
                <i class="bi bi-patch-check"></i> Voter Whitelist
            </a>
            <a href="<?= base_url('election-monitoring.php') ?>" class="sidebar-link <?= $currentScript === 'election-monitoring.php' ? 'active' : '' ?>">
                <i class="bi bi-activity"></i> Live Monitoring
            </a>
            <a href="<?= base_url('results.php') ?>" class="sidebar-link <?= $currentScript === 'results.php' ? 'active' : '' ?>">
                <i class="bi bi-award"></i> Results Manager
            </a>
            <a href="<?= base_url('reports.php') ?>" class="sidebar-link <?= $currentScript === 'reports.php' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> Reports & Export
            </a>
            <a href="<?= base_url('audit-logs.php') ?>" class="sidebar-link <?= $currentScript === 'audit-logs.php' ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i> Audit Logs
            </a>
        </nav>

        <hr class="my-3 text-muted">

        <div class="px-2">
            <a href="<?= public_url('logout.php') ?>" class="sidebar-link text-danger">
                <i class="bi bi-box-arrow-right"></i> Sign Out
            </a>
        </div>
    </div>
</div>
