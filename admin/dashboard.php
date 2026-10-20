<?php
/**
 * College Voting System - Election Commission Administrator Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-admin.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';
require_once __DIR__ . '/../app/repositories/StudentRepository.php';
require_once __DIR__ . '/../app/repositories/CandidateRepository.php';
require_once __DIR__ . '/../app/services/ElectionService.php';

$pageTitle = 'Commission Dashboard';

$db = Database::getConnection();
$electionService = new ElectionService($db);
$electionService->syncStatuses();

$electionRepo = new ElectionRepository($db);
$studentRepo = new StudentRepository($db);
$candidateRepo = new CandidateRepository($db);

// Real aggregate database statistics
$electionStats = $electionRepo->getStats();
$studentStats = $studentRepo->getStats();
$totalCandidates = $candidateRepo->getTotalCandidates();

// Recent active and scheduled elections
$recentElections = $electionRepo->getAll();

// Recent audit activities
$stmtAudit = $db->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 6");
$recentAudit = $stmtAudit->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <!-- Admin Sidebar -->
        <?php require_once __DIR__ . '/../app/includes/admin-sidebar.php'; ?>

        <!-- Dashboard Content -->
        <div class="col-lg-9 col-xl-10">
            <!-- Header Section -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Election Commission Dashboard</h1>
                    <p class="text-secondary small mb-0">Overview of campus voter turnout, election lifecycle, and administrative audit trails.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= base_url('election-form.php') ?>" class="btn btn-navy btn-sm">
                        <i class="bi bi-plus-circle me-1"></i> New Election
                    </a>
                    <a href="<?= base_url('candidate-form.php') ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-person-plus me-1"></i> Add Nominee
                    </a>
                    <a href="<?= base_url('eligibility.php') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-earmark-arrow-up me-1"></i> Import Voters
                    </a>
                </div>
            </div>

            <!-- Summary KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card card-academic p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper bg-primary-subtle text-primary">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-medium">Registered Students</div>
                                <h3 class="fw-bold mb-0 text-dark"><?= $studentStats['total_students'] ?></h3>
                                <div class="small text-success"><i class="bi bi-check-circle me-1"></i><?= $studentStats['verified_students'] ?> Verified</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card card-academic p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper bg-success-subtle text-success">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-medium">Active Elections</div>
                                <h3 class="fw-bold mb-0 text-success"><?= $electionStats['open_elections'] ?></h3>
                                <div class="small text-muted"><?= $electionStats['total_elections'] ?> Total Scheduled/Completed</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card card-academic p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper bg-warning-subtle text-warning">
                                <i class="bi bi-person-lines-fill"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-medium">Approved Candidates</div>
                                <h3 class="fw-bold mb-0 text-dark"><?= $totalCandidates ?></h3>
                                <div class="small text-muted">Contesting Nominees</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card card-academic p-3 h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper bg-info-subtle text-info">
                                <i class="bi bi-check2-all"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-medium">Total Ballots Cast</div>
                                <h3 class="fw-bold mb-0 text-primary"><?= $electionStats['total_votes'] ?></h3>
                                <div class="small text-success"><i class="bi bi-shield-check me-1"></i>Atomic & Verified</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active & Scheduled Elections Table -->
            <div class="card card-academic p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-calendar3 me-2 text-primary"></i> Current Election Status Overview</h5>
                    <a href="<?= base_url('elections.php') ?>" class="btn btn-outline-secondary btn-sm">Manage All</a>
                </div>

                <?php if (empty($recentElections)): ?>
                    <p class="text-muted text-center py-4 mb-0">No elections configured yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Academic Year</th>
                                    <th>Status</th>
                                    <th>Voting Window</th>
                                    <th>Candidates</th>
                                    <th>Ballots Cast</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recentElections, 0, 5) as $el): ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?= e($el['title']) ?></td>
                                        <td><span class="badge bg-light text-secondary border"><?= e($el['academic_year']) ?></span></td>
                                        <td>
                                            <span class="badge badge-status badge-<?= e($el['status']) ?>">
                                                <?= e(str_replace('_', ' ', $el['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            <?= format_datetime($el['start_time'], 'M d, h:i A') ?> &mdash; <?= format_datetime($el['end_time'], 'M d, h:i A') ?>
                                        </td>
                                        <td><?= (int)$el['candidate_count'] ?></td>
                                        <td class="fw-bold text-primary"><?= (int)$el['total_votes'] ?></td>
                                        <td class="text-end">
                                            <a href="<?= base_url('election-monitoring.php?id=' . $el['id']) ?>" class="btn btn-sm btn-outline-primary" title="Live Monitoring">
                                                <i class="bi bi-activity"></i>
                                            </a>
                                            <a href="<?= base_url('results.php?id=' . $el['id']) ?>" class="btn btn-sm btn-outline-warning text-dark" title="Results & Turnout">
                                                <i class="bi bi-trophy"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Audit Logs Section -->
            <div class="card card-academic p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-journal-text me-2 text-primary"></i> Recent Administrative Audit Trails</h5>
                    <a href="<?= base_url('audit-logs.php') ?>" class="btn btn-outline-secondary btn-sm">Full Audit Trail</a>
                </div>

                <?php if (empty($recentAudit)): ?>
                    <p class="text-muted text-center py-4 mb-0">No administrative events recorded yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Actor</th>
                                    <th>Action</th>
                                    <th>Target Object</th>
                                    <th>Summary Description</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAudit as $log): ?>
                                    <tr>
                                        <td class="text-muted"><?= format_datetime($log['created_at']) ?></td>
                                        <td class="fw-semibold text-dark"><?= e($log['actor_email'] ?? 'System') ?></td>
                                        <td><span class="badge bg-secondary-subtle text-secondary font-monospace"><?= e($log['action']) ?></span></td>
                                        <td><span class="text-capitalize"><?= e($log['object_type']) ?> #<?= (int)$log['object_id'] ?></span></td>
                                        <td class="text-secondary"><?= e($log['summary']) ?></td>
                                        <td class="text-muted font-monospace"><?= e($log['ip_address']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
