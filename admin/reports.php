<?php
/**
 * College Voting System - Administrative Reports & Data Summaries
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-admin.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';

$pageTitle = 'Reports & Exports';
$db = Database::getConnection();
$electionRepo = new ElectionRepository($db);

$elections = $electionRepo->getAll();

// Department-wise summary
$stmtDept = $db->query("
    SELECT d.id, d.code, d.name,
           (SELECT COUNT(*) FROM students s WHERE s.department_id = d.id) AS total_students,
           (SELECT COUNT(*) FROM students s WHERE s.department_id = d.id AND s.is_verified = 1) AS verified_students,
           (SELECT COUNT(*) FROM voter_participation vp JOIN students s ON vp.student_id = s.id WHERE s.department_id = d.id) AS total_votes_cast
    FROM departments d
    ORDER BY d.code ASC
");
$deptSummary = $stmtDept->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <?php require_once __DIR__ . '/../app/includes/admin-sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Administrative Reports & Exports</h1>
                    <p class="text-secondary small mb-0">Audited tabular summaries with spreadsheet formula injection protection.</p>
                </div>
            </div>

            <!-- Quick Export Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-md-3">
                    <div class="card card-academic p-3 text-center">
                        <i class="bi bi-calendar-event fs-2 text-primary mb-2"></i>
                        <h6 class="fw-bold mb-2">Elections Master</h6>
                        <a href="<?= base_url('export.php?type=elections') ?>" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-download me-1"></i> Export CSV
                        </a>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="card card-academic p-3 text-center">
                        <i class="bi bi-mortarboard fs-2 text-success mb-2"></i>
                        <h6 class="fw-bold mb-2">Student Roster</h6>
                        <a href="<?= base_url('export.php?type=students') ?>" class="btn btn-outline-success btn-sm w-100">
                            <i class="bi bi-download me-1"></i> Export CSV
                        </a>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="card card-academic p-3 text-center">
                        <i class="bi bi-people-fill fs-2 text-warning mb-2"></i>
                        <h6 class="fw-bold mb-2">Candidate Roster</h6>
                        <a href="<?= base_url('export.php?type=candidates') ?>" class="btn btn-outline-warning text-dark btn-sm w-100">
                            <i class="bi bi-download me-1"></i> Export CSV
                        </a>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="card card-academic p-3 text-center">
                        <i class="bi bi-building fs-2 text-info mb-2"></i>
                        <h6 class="fw-bold mb-2">Department Stats</h6>
                        <a href="<?= base_url('export.php?type=department_participation') ?>" class="btn btn-outline-info btn-sm w-100">
                            <i class="bi bi-download me-1"></i> Export CSV
                        </a>
                    </div>
                </div>
            </div>

            <!-- Department-Wise Participation Breakdown Table -->
            <div class="card card-academic p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-building me-2 text-primary"></i> Department-Wise Student & Voter Summary</h5>
                    <a href="<?= base_url('export.php?type=department_participation') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Download CSV
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Department Code</th>
                                <th>Department Name</th>
                                <th>Enrolled Students</th>
                                <th>Verified Voters</th>
                                <th>Total Ballots Cast</th>
                                <th class="text-end">Verification Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deptSummary as $d): ?>
                                <?php
                                    $rate = $d['total_students'] > 0 ? round(($d['verified_students'] / $d['total_students']) * 100, 1) : 0.0;
                                ?>
                                <tr>
                                    <td><code class="text-primary fw-bold"><?= e($d['code']) ?></code></td>
                                    <td class="fw-semibold text-dark"><?= e($d['name']) ?></td>
                                    <td><?= (int)$d['total_students'] ?></td>
                                    <td><span class="badge bg-success-subtle text-success"><?= (int)$d['verified_students'] ?></span></td>
                                    <td class="fw-bold text-primary"><?= (int)$d['total_votes_cast'] ?></td>
                                    <td class="text-end fw-semibold"><?= $rate ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Election Schedule & Outcome Summary Table -->
            <div class="card card-academic p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-calendar3 me-2 text-primary"></i> Campus Election Overview Report</h5>
                    <a href="<?= base_url('export.php?type=elections') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Download CSV
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Election Title</th>
                                <th>Academic Year</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Ballots Cast</th>
                                <th class="text-end">Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($elections as $el): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= e($el['title']) ?></td>
                                    <td><?= e($el['academic_year']) ?></td>
                                    <td class="text-capitalize"><?= e($el['election_type']) ?></td>
                                    <td>
                                        <span class="badge badge-status badge-<?= e($el['status']) ?>">
                                            <?= e(str_replace('_', ' ', $el['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-primary"><?= (int)$el['total_votes'] ?></td>
                                    <td class="text-end">
                                        <a href="<?= base_url('results.php?id=' . $el['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                            View Report
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
