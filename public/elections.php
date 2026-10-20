<?php
/**
 * College Voting System - Elections Catalog
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';
require_once __DIR__ . '/../app/repositories/BallotRepository.php';
require_once __DIR__ . '/../app/services/EligibilityService.php';
require_once __DIR__ . '/../app/services/ElectionService.php';

$pageTitle = 'Campus Elections';

$db = Database::getConnection();
$electionService = new ElectionService($db);
$electionService->syncStatuses();

$electionRepo = new ElectionRepository($db);
$ballotRepo = new BallotRepository($db);
$eligibilityService = new EligibilityService($db);

$statusFilter = $_GET['status'] ?? '';
$searchFilter = $_GET['search'] ?? '';

$filters = [];
if (!empty($statusFilter)) $filters['status'] = $statusFilter;
if (!empty($searchFilter)) $filters['search'] = $searchFilter;

$elections = $electionRepo->getAll($filters);

$student = Auth::isStudent() ? (Auth::user()['student'] ?? null) : null;
$studentId = $student ? (int)$student['id'] : 0;

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container py-4 py-md-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Campus Elections</h1>
            <p class="text-secondary small mb-0">Browse student council, departmental, and committee elections.</p>
        </div>

        <!-- Filter Buttons -->
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url('elections.php') ?>" class="btn btn-sm <?= empty($statusFilter) ? 'btn-navy' : 'btn-outline-secondary' ?>">All</a>
            <a href="<?= base_url('elections.php?status=open') ?>" class="btn btn-sm <?= $statusFilter === 'open' ? 'btn-success' : 'btn-outline-success' ?>">Open Now</a>
            <a href="<?= base_url('elections.php?status=scheduled') ?>" class="btn btn-sm <?= $statusFilter === 'scheduled' ? 'btn-info' : 'btn-outline-info' ?>">Upcoming</a>
            <a href="<?= base_url('elections.php?status=results_published') ?>" class="btn btn-sm <?= $statusFilter === 'results_published' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' ?>">Published Results</a>
        </div>
    </div>

    <!-- Search Bar -->
    <form method="GET" action="<?= base_url('elections.php') ?>" class="mb-4">
        <?php if (!empty($statusFilter)): ?>
            <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
        <?php endif; ?>
        <div class="input-group">
            <span class="input-group-text bg-white text-secondary"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" name="search" value="<?= e($searchFilter) ?>" placeholder="Search elections by title or keyword...">
            <button class="btn btn-navy" type="submit">Search</button>
            <?php if (!empty($searchFilter) || !empty($statusFilter)): ?>
                <a href="<?= base_url('elections.php') ?>" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Elections Grid -->
    <?php if (empty($elections)): ?>
        <div class="card card-academic p-5 text-center text-muted">
            <i class="bi bi-calendar-x fs-1 mb-2"></i>
            <h5 class="fw-bold mb-1">No Elections Found</h5>
            <p class="small mb-0">There are no elections matching the specified criteria.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($elections as $elec): ?>
                <?php
                    $isEligible = false;
                    $eligibilityReason = '';
                    $hasVoted = false;

                    if ($studentId > 0) {
                        $check = $eligibilityService->check((int)$elec['id'], $studentId);
                        $isEligible = $check['eligible'];
                        $eligibilityReason = $check['reason'];
                        $hasVoted = $ballotRepo->hasStudentVoted((int)$elec['id'], $studentId);
                    }
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-academic h-100 p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge badge-status badge-<?= e($elec['status']) ?>">
                                <?= e(str_replace('_', ' ', $elec['status'])) ?>
                            </span>
                            <span class="badge bg-light text-secondary border small">
                                <?= e($elec['academic_year']) ?>
                            </span>
                        </div>

                        <h5 class="fw-bold text-dark mb-2"><?= e($elec['title']) ?></h5>
                        <p class="text-secondary small mb-3 flex-grow-1">
                            <?= e(mb_strimwidth((string)$elec['description'], 0, 120, '...')) ?>
                        </p>

                        <div class="small bg-light p-3 rounded-3 mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted"><i class="bi bi-clock me-1"></i> Start:</span>
                                <span class="fw-medium"><?= format_datetime($elec['start_time'], 'M d, h:i A') ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted"><i class="bi bi-clock-fill me-1"></i> End:</span>
                                <span class="fw-medium"><?= format_datetime($elec['end_time'], 'M d, h:i A') ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted"><i class="bi bi-people me-1"></i> Candidates:</span>
                                <span class="fw-bold text-primary"><?= (int)$elec['candidate_count'] ?></span>
                            </div>
                        </div>

                        <?php if ($elec['status'] === 'results_published'): ?>
                            <a href="<?= base_url('results.php?id=' . $elec['id']) ?>" class="btn btn-warning fw-semibold text-dark w-100">
                                <i class="bi bi-trophy me-1"></i> View Official Results
                            </a>
                        <?php elseif ($studentId > 0 && $hasVoted): ?>
                            <div class="d-flex gap-2">
                                <span class="btn btn-outline-success btn-sm flex-grow-1 disabled">
                                    <i class="bi bi-check2-circle me-1"></i> Voted
                                </span>
                                <a href="<?= base_url('election-details.php?id=' . $elec['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                    View Info
                                </a>
                            </div>
                        <?php elseif ($studentId > 0 && $elec['status'] === 'open' && $isEligible): ?>
                            <a href="<?= base_url('election-details.php?id=' . $elec['id']) ?>" class="btn btn-navy w-100">
                                <i class="bi bi-box-seam me-1"></i> Cast Vote Now &rarr;
                            </a>
                        <?php else: ?>
                            <a href="<?= base_url('election-details.php?id=' . $elec['id']) ?>" class="btn btn-outline-primary w-100">
                                View Details & Candidates
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
