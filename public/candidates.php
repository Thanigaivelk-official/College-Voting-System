<?php
/**
 * College Voting System - Public Candidate Directory
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/repositories/CandidateRepository.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';

$pageTitle = 'Candidate Directory';
$db = Database::getConnection();
$candidateRepo = new CandidateRepository($db);
$electionRepo = new ElectionRepository($db);

$electionFilter = (int)($_GET['election_id'] ?? 0);
$searchFilter = $_GET['search'] ?? '';

$filters = ['status' => 'active'];
if ($electionFilter > 0) $filters['election_id'] = $electionFilter;
if (!empty($searchFilter)) $filters['search'] = $searchFilter;

$candidates = $candidateRepo->getAll($filters);
$openElections = $electionRepo->getAll();

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container py-4 py-md-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Candidate Directory</h1>
            <p class="text-secondary small mb-0">Learn about student leaders contesting for council and committee representation.</p>
        </div>
        <a href="<?= base_url('elections.php') ?>" class="btn btn-navy btn-sm">
            <i class="bi bi-box-seam me-1"></i> Active Elections
        </a>
    </div>

    <!-- Filter Card -->
    <div class="card card-academic p-3 mb-4">
        <form method="GET" action="<?= base_url('candidates.php') ?>" class="row g-2 align-items-center">
            <div class="col-md-6">
                <select class="form-select form-select-sm" name="election_id" onchange="this.form.submit()">
                    <option value="">All Elections...</option>
                    <?php foreach ($openElections as $el): ?>
                        <option value="<?= $el['id'] ?>" <?= $el['id'] == $electionFilter ? 'selected' : '' ?>>
                            <?= e($el['title']) ?> (<?= e($el['academic_year']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" name="search" value="<?= e($searchFilter) ?>" placeholder="Search candidate by name, position, or manifesto...">
                    <button class="btn btn-navy" type="submit">Search</button>
                    <?php if ($electionFilter > 0 || !empty($searchFilter)): ?>
                        <a href="<?= base_url('candidates.php') ?>" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Candidates Grid -->
    <?php if (empty($candidates)): ?>
        <div class="card card-academic p-5 text-center text-muted">
            <i class="bi bi-people fs-1 mb-2"></i>
            <h5 class="fw-bold mb-1">No Candidates Found</h5>
            <p class="small mb-0">No active nominees found matching your criteria.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($candidates as $cand): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-academic h-100 p-4 d-flex flex-column text-center">
                        <div class="mb-3 d-flex justify-content-center">
                            <?php if (!empty($cand['photo_url']) && file_exists(ROOT_PATH . '/public/uploads/candidates/' . $cand['photo_url'])): ?>
                                <img src="<?= base_url('uploads/candidates/' . e($cand['photo_url'])) ?>" class="candidate-avatar" alt="<?= e($cand['name']) ?>">
                            <?php else: ?>
                                <div class="candidate-avatar-placeholder">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h5 class="fw-bold text-dark mb-1"><?= e($cand['name']) ?></h5>
                        <div class="badge bg-primary-subtle text-primary mb-2 align-self-center"><?= e($cand['position']) ?></div>
                        <div class="text-muted small mb-2">Department: <?= e($cand['department_name'] ?? 'General') ?></div>
                        <div class="small fw-semibold text-secondary mb-3">
                            <i class="bi bi-calendar-event me-1"></i> <?= e($cand['election_title']) ?>
                        </div>

                        <div class="bg-light p-3 rounded-3 small text-secondary text-start mb-3 flex-grow-1" style="max-height: 120px; overflow-y: auto;">
                            <div class="fw-bold text-dark mb-1">Manifesto:</div>
                            <?= nl2br(e($cand['manifesto'] ?? 'No statement provided.')) ?>
                        </div>

                        <a href="<?= base_url('election-details.php?id=' . $cand['election_id']) ?>" class="btn btn-outline-primary btn-sm w-100">
                            View Election & Vote &rarr;
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
