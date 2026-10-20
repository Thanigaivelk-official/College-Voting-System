<?php
/**
 * College Voting System - Student / Voter Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-student.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';
require_once __DIR__ . '/../app/repositories/BallotRepository.php';
require_once __DIR__ . '/../app/services/EligibilityService.php';
require_once __DIR__ . '/../app/services/ElectionService.php';

$pageTitle = 'Voter Dashboard';

$db = Database::getConnection();
$user = Auth::user();
$student = $user['student'] ?? [];
$studentId = (int)($student['id'] ?? 0);

$electionService = new ElectionService($db);
$electionService->syncStatuses();

$electionRepo = new ElectionRepository($db);
$ballotRepo = new BallotRepository($db);
$eligibilityService = new EligibilityService($db);

// Open elections
$allOpenElections = $electionRepo->getAll(['status' => 'open']);
$eligibleOpenElections = [];

foreach ($allOpenElections as $elec) {
    $check = $eligibilityService->check((int)$elec['id'], $studentId);
    $hasVoted = $ballotRepo->hasStudentVoted((int)$elec['id'], $studentId);
    $elec['is_eligible'] = $check['eligible'];
    $elec['eligibility_reason'] = $check['reason'];
    $elec['has_voted'] = $hasVoted;
    $eligibleOpenElections[] = $elec;
}

// Student voting history
$votingHistory = $ballotRepo->getStudentVotingHistory($studentId);

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <!-- Student Sidebar -->
        <?php require_once __DIR__ . '/../app/includes/student-sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="col-lg-9 col-xl-10">
            <!-- Voter Profile Greeting Banner -->
            <div class="card card-academic p-4 mb-4 border-0" style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%); color: #ffffff;">
                <div class="row align-items-center gy-3">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-white text-dark small text-uppercase fw-bold">Student Voter</span>
                            <?php if (!empty($student['is_verified'])): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold">
                                    <i class="bi bi-patch-check-fill me-1"></i> Verified by Commission
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark fw-semibold">
                                    <i class="bi bi-clock-history me-1"></i> Verification Pending
                                </span>
                            <?php endif; ?>
                        </div>
                        <h2 class="h3 fw-bold mb-1">Hello, <?= e($student['full_name'] ?? 'Student') ?>!</h2>
                        <p class="text-white-50 mb-0">
                            Reg No: <strong class="text-white"><?= e($student['registration_no'] ?? 'N/A') ?></strong> &bull;
                            Department: <strong class="text-white"><?= e($student['department_name'] ?? 'General') ?></strong> &bull;
                            Year <?= e((string)($student['academic_year'] ?? 1)) ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="<?= base_url('elections.php') ?>" class="btn btn-warning fw-semibold px-3 text-dark">
                            <i class="bi bi-box-seam me-1"></i> Browse Elections
                        </a>
                    </div>
                </div>
            </div>

            <?php if (empty($student['is_verified'])): ?>
                <div class="alert alert-warning d-flex align-items-center gap-3 p-3 mb-4 shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Student Account Verification Required</h6>
                        <p class="mb-0 small">
                            Your account is currently awaiting administrative approval from the Election Commission. Once an election officer verifies your registration details against the college roster, you will be eligible to cast votes.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Dashboard Summary Counters -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-md-4">
                    <div class="card card-academic p-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper bg-primary-subtle text-primary">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-medium">Open Elections</div>
                                <h3 class="fw-bold mb-0"><?= count($allOpenElections) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-4">
                    <div class="card card-academic p-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper bg-success-subtle text-success">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-medium">Ballots Cast by You</div>
                                <h3 class="fw-bold mb-0 text-success"><?= count($votingHistory) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-4">
                    <div class="card card-academic p-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper bg-info-subtle text-info">
                                <i class="bi bi-patch-check"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-medium">Voter Eligibility</div>
                                <h5 class="fw-bold mb-0 text-<?= !empty($student['is_verified']) ? 'success' : 'warning' ?>">
                                    <?= !empty($student['is_verified']) ? 'Eligible' : 'Pending Review' ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Eligible Elections Section -->
            <div class="card card-academic p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-calendar-check me-2 text-primary"></i> Active Campus Elections</h5>
                    <a href="<?= base_url('elections.php') ?>" class="btn btn-sm btn-outline-secondary">View Full Catalog</a>
                </div>

                <?php if (empty($eligibleOpenElections)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-2 mb-2"></i>
                        <p class="mb-0">No active elections currently running.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Election</th>
                                    <th>Type</th>
                                    <th>Voting Closes</th>
                                    <th>Status / Participation</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($eligibleOpenElections as $elec): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($elec['title']) ?></div>
                                            <div class="text-muted small"><?= e(mb_strimwidth((string)$elec['description'], 0, 80, '...')) ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border text-capitalize"><?= e($elec['election_type']) ?></span></td>
                                        <td><small class="text-muted"><i class="bi bi-clock me-1"></i><?= format_datetime($elec['end_time']) ?></small></td>
                                        <td>
                                            <?php if ($elec['has_voted']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <i class="bi bi-check-circle-fill me-1"></i> Vote Recorded
                                                </span>
                                            <?php elseif (!$elec['is_eligible']): ?>
                                                <span class="badge bg-danger-subtle text-danger" title="<?= e($elec['eligibility_reason']) ?>">
                                                    <i class="bi bi-slash-circle me-1"></i> Ineligible
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-status badge-open">Eligible to Vote</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($elec['has_voted']): ?>
                                                <a href="<?= base_url('election-details.php?id=' . $elec['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                                    Details
                                                </a>
                                            <?php elseif ($elec['is_eligible']): ?>
                                                <a href="<?= base_url('election-details.php?id=' . $elec['id']) ?>" class="btn btn-sm btn-navy">
                                                    Cast Ballot &rarr;
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= base_url('election-details.php?id=' . $elec['id']) ?>" class="btn btn-sm btn-light border">
                                                    Details
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Participation Receipts Section -->
            <div class="card card-academic p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i> Your Voting History & Receipts</h5>
                <?php if (empty($votingHistory)): ?>
                    <p class="text-muted small mb-0">You have not cast any votes in recorded elections yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Election Title</th>
                                    <th>Submission Timestamp</th>
                                    <th>Audit Verification Receipt</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($votingHistory as $hist): ?>
                                    <tr>
                                        <td class="fw-semibold text-dark"><?= e($hist['election_title']) ?></td>
                                        <td class="text-muted small"><?= format_datetime($hist['voted_at']) ?></td>
                                        <td>
                                            <code class="bg-light px-2 py-1 rounded text-primary fw-bold">
                                                <?= e($hist['receipt_hash']) ?>
                                            </code>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="bi bi-shield-check me-1"></i> Confirmed Recorded
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 small text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        In compliance with secret-ballot privacy protocols, your verification receipt confirms your participation without displaying your candidate selection.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
