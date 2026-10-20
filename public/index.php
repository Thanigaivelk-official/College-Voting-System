<?php
/**
 * College Voting System - Public Landing Page
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/services/ElectionService.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';

$pageTitle = 'Home';

$db = Database::getConnection();
$electionService = new ElectionService($db);
$electionService->syncStatuses();

$electionRepo = new ElectionRepository($db);
$openElections = $electionRepo->getAll(['status' => 'open']);
$publishedElections = $electionRepo->getAll(['status' => 'results_published']);
$stats = $electionRepo->getStats();

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container py-4 py-md-5">
    <!-- Hero Section -->
    <div class="row align-items-center g-4 py-3 py-lg-4 mb-4">
        <div class="col-lg-7">
            <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold mb-3">
                <i class="bi bi-shield-lock me-1"></i> Transparent &bull; Tamper-Resistant &bull; Secret Ballot
            </div>
            <h1 class="display-5 fw-bold text-dark lh-sm mb-3">
                Empowering Student Voices Through Secure Campus Elections
            </h1>
            <p class="lead text-secondary mb-4">
                The official College Voting System delivers an audited, transaction-safe election platform. Cast your vote securely, review verified candidate manifestos, and track certified election turnout.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <?php if (Auth::check()): ?>
                    <a href="<?= Auth::isAdmin() ? admin_url('dashboard.php') : public_url('dashboard.php') ?>" class="btn btn-navy btn-lg px-4 shadow-sm">
                        <i class="bi bi-speedometer2 me-2"></i> Go to Dashboard
                    </a>
                <?php else: ?>
                    <a href="<?= base_url('login.php') ?>" class="btn btn-navy btn-lg px-4 shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Sign In to Vote
                    </a>
                    <a href="<?= base_url('register.php') ?>" class="btn btn-outline-secondary btn-lg px-4">
                        Register Account
                    </a>
                <?php endif; ?>
                <a href="<?= base_url('results.php') ?>" class="btn btn-light btn-lg border px-4">
                    <i class="bi bi-trophy me-2 text-warning"></i> View Results
                </a>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card card-academic p-4 shadow-sm">
                <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-bar-chart-fill text-primary"></i> Election Commission Metrics
                </h5>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 text-center">
                            <div class="fs-3 fw-bold text-dark"><?= $stats['open_elections'] ?></div>
                            <div class="text-muted small fw-medium">Active Elections</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 text-center">
                            <div class="fs-3 fw-bold text-success"><?= $stats['total_votes'] ?></div>
                            <div class="text-muted small fw-medium">Verified Ballots Cast</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 text-center">
                            <div class="fs-3 fw-bold text-info"><?= $stats['completed_elections'] ?></div>
                            <div class="text-muted small fw-medium">Completed Polls</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 text-center">
                            <div class="fs-3 fw-bold text-warning"><?= $stats['scheduled_elections'] ?></div>
                            <div class="text-muted small fw-medium">Upcoming Elections</div>
                        </div>
                    </div>
                </div>

                <hr class="my-3">
                <div class="small text-muted d-flex align-items-center gap-2">
                    <i class="bi bi-check2-circle text-success fs-5"></i>
                    <span>All votes protected with database-level uniqueness constraints and decoupled ballot tokens.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Active Elections Section -->
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h4 fw-bold mb-1">Currently Open Elections</h2>
                <p class="text-muted small mb-0">Cast your vote before the scheduled closing deadline.</p>
            </div>
            <a href="<?= base_url('elections.php') ?>" class="btn btn-outline-primary btn-sm">View All</a>
        </div>

        <?php if (empty($openElections)): ?>
            <div class="card card-academic p-4 text-center text-muted">
                <i class="bi bi-inbox fs-2 mb-2"></i>
                <p class="mb-0">There are currently no active elections open for voting.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach (array_slice($openElections, 0, 3) as $el): ?>
                    <div class="col-md-4">
                        <div class="card card-academic h-100 p-3 p-lg-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge badge-status badge-open">Open</span>
                                <span class="badge bg-secondary-subtle text-secondary small"><?= e($el['academic_year']) ?></span>
                            </div>
                            <h5 class="fw-bold mb-2"><?= e($el['title']) ?></h5>
                            <p class="text-secondary small mb-3 flex-grow-1">
                                <?= e(mb_strimwidth((string)$el['description'], 0, 110, '...')) ?>
                            </p>
                            <div class="small text-muted mb-3">
                                <div><i class="bi bi-clock me-1"></i> Closes: <?= format_datetime($el['end_time']) ?></div>
                                <div><i class="bi bi-people me-1"></i> <?= $el['candidate_count'] ?> Candidates Contesting</div>
                            </div>
                            <a href="<?= base_url('election-details.php?id=' . $el['id']) ?>" class="btn btn-outline-primary w-100">
                                View Details & Vote &rarr;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Core Security & Architecture Pillars -->
    <div class="row g-4 py-3">
        <div class="col-md-4">
            <div class="card card-academic p-4 h-100">
                <div class="stat-icon-wrapper bg-primary-subtle text-primary mb-3">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h5 class="fw-bold">Duplicate Vote Prevention</h5>
                <p class="text-secondary small mb-0">
                    Database-enforced composite uniqueness constraints `UNIQUE(election_id, student_id)` and atomic ACID transactions guarantee that double-submitting or race conditions are strictly blocked.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-academic p-4 h-100">
                <div class="stat-icon-wrapper bg-success-subtle text-success mb-3">
                    <i class="bi bi-incognito"></i>
                </div>
                <h5 class="fw-bold">Decoupled Ballot Privacy</h5>
                <p class="text-secondary small mb-0">
                    Voter participation records are separated from candidate ballot records. Cast ballots store anonymized cryptographic tokens, keeping individual choices private from dashboard operators.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-academic p-4 h-100">
                <div class="stat-icon-wrapper bg-warning-subtle text-warning mb-3">
                    <i class="bi bi-file-earmark-check"></i>
                </div>
                <h5 class="fw-bold">Certified Result Snapshots</h5>
                <p class="text-secondary small mb-0">
                    Upon election conclusion, election outcomes are automatically calculated, tie-checked, and frozen into permanent result snapshots with turnout statistics and CSV exports.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
