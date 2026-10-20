<?php
/**
 * College Voting System - Student Participation History
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-student.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/repositories/BallotRepository.php';

$pageTitle = 'Voting Participation History';

$db = Database::getConnection();
$student = Auth::user()['student'] ?? [];
$studentId = (int)($student['id'] ?? 0);

$ballotRepo = new BallotRepository($db);
$history = $ballotRepo->getStudentVotingHistory($studentId);

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <?php require_once __DIR__ . '/../app/includes/student-sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Voting Participation History</h1>
                    <p class="text-secondary small mb-0">Review verified election participation timestamps and audit receipts.</p>
                </div>
                <a href="<?= base_url('elections.php') ?>" class="btn btn-navy btn-sm">
                    <i class="bi bi-box-seam me-1"></i> Active Elections
                </a>
            </div>

            <div class="card card-academic p-4 mb-4">
                <?php if (empty($history)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-journal-x fs-1 mb-2"></i>
                        <h5 class="fw-bold mb-1">No Participation Records Found</h5>
                        <p class="small mb-3">You have not cast any votes in college elections yet.</p>
                        <a href="<?= base_url('elections.php') ?>" class="btn btn-outline-primary btn-sm">View Open Elections</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Election Title</th>
                                    <th>Academic Year</th>
                                    <th>Submission Timestamp</th>
                                    <th>Audit Verification Hash</th>
                                    <th>Election Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $idx => $row): ?>
                                    <tr>
                                        <td class="text-muted small"><?= $idx + 1 ?></td>
                                        <td class="fw-bold text-dark"><?= e($row['election_title']) ?></td>
                                        <td><span class="badge bg-light text-secondary border"><?= e($row['academic_year']) ?></span></td>
                                        <td class="text-muted small"><?= format_datetime($row['voted_at']) ?></td>
                                        <td>
                                            <code class="bg-light px-2 py-1 rounded text-primary fw-bold">
                                                <?= e($row['receipt_hash']) ?>
                                            </code>
                                        </td>
                                        <td>
                                            <span class="badge badge-status badge-<?= e($row['election_status']) ?>">
                                                <?= e(str_replace('_', ' ', $row['election_status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($row['election_status'] === 'results_published'): ?>
                                                <a href="<?= base_url('results.php?id=' . $row['election_id']) ?>" class="btn btn-sm btn-warning text-dark fw-semibold">
                                                    Results
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= base_url('election-details.php?id=' . $row['election_id']) ?>" class="btn btn-sm btn-outline-secondary">
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

            <div class="card card-academic p-3 bg-light border-0">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-shield-lock-fill text-primary fs-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Audited Secret-Ballot Architecture</h6>
                        <p class="text-muted small mb-0">
                            This history table verifies that your vote was officially recorded. By design, our database decouples student identities from candidate ballots to guarantee that neither other students nor election officers can view your specific candidate ballot choice.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
