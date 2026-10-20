<?php
/**
 * College Voting System - Election Details & Candidate Manifestos
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/csrf.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';
require_once __DIR__ . '/../app/repositories/CandidateRepository.php';
require_once __DIR__ . '/../app/repositories/BallotRepository.php';
require_once __DIR__ . '/../app/services/EligibilityService.php';

$electionId = (int)($_GET['id'] ?? 0);
if ($electionId <= 0) {
    redirect(base_url('elections.php'));
}

$db = Database::getConnection();
$electionRepo = new ElectionRepository($db);
$candidateRepo = new CandidateRepository($db);
$ballotRepo = new BallotRepository($db);
$eligibilityService = new EligibilityService($db);

$election = $electionRepo->findById($electionId);
if (!$election) {
    http_response_code(404);
    require_once __DIR__ . '/../app/views/errors/404.php';
    exit;
}

$candidates = $candidateRepo->getByElectionId($electionId);

// Check voter state if student is logged in
$student = Auth::isStudent() ? (Auth::user()['student'] ?? null) : null;
$studentId = $student ? (int)$student['id'] : 0;

$eligibility = ['eligible' => false, 'reason' => 'Sign in as a registered student to vote.'];
$hasVoted = false;

if ($studentId > 0) {
    $eligibility = $eligibilityService->check($electionId, $studentId);
    $hasVoted = $ballotRepo->hasStudentVoted($electionId, $studentId);
}

$pageTitle = $election['title'];
require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container py-4 py-md-5">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= base_url('elections.php') ?>">Elections</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($election['title']) ?></li>
        </ol>
    </nav>

    <!-- Election Header Card -->
    <div class="card card-academic p-4 p-md-5 mb-4 border-top border-4 border-primary">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <span class="badge badge-status badge-<?= e($election['status']) ?> mb-2">
                    <?= e(str_replace('_', ' ', $election['status'])) ?>
                </span>
                <h1 class="h2 fw-bold text-dark mb-1"><?= e($election['title']) ?></h1>
                <div class="text-muted small">
                    Academic Year: <strong class="text-dark"><?= e($election['academic_year']) ?></strong> &bull;
                    Type: <strong class="text-dark text-capitalize"><?= e($election['election_type']) ?></strong>
                </div>
            </div>
            <?php if ($election['status'] === 'results_published'): ?>
                <a href="<?= base_url('results.php?id=' . $election['id']) ?>" class="btn btn-warning text-dark fw-semibold">
                    <i class="bi bi-trophy me-1"></i> View Published Results
                </a>
            <?php endif; ?>
        </div>

        <p class="text-secondary mb-4"><?= nl2br(e($election['description'] ?? 'No description provided.')) ?></p>

        <div class="row g-3 bg-light p-3 rounded-3">
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small">Voting Begins</div>
                <div class="fw-semibold text-dark"><i class="bi bi-clock me-1 text-primary"></i><?= format_datetime($election['start_time']) ?></div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small">Voting Deadline</div>
                <div class="fw-semibold text-dark"><i class="bi bi-clock-fill me-1 text-danger"></i><?= format_datetime($election['end_time']) ?></div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small">Voter Eligibility</div>
                <div class="fw-semibold text-dark"><i class="bi bi-person-check me-1 text-success"></i><?= e(ucwords(str_replace('_', ' ', $election['eligibility_type']))) ?></div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small">Contesting Nominees</div>
                <div class="fw-semibold text-dark"><i class="bi bi-people-fill me-1 text-info"></i><?= count($candidates) ?> Nominees</div>
            </div>
        </div>
    </div>

    <!-- Voter Eligibility Status Notification Box -->
    <?php if (Auth::isStudent()): ?>
        <div class="card card-academic p-3 p-md-4 mb-4 <?= $hasVoted ? 'border-success' : ($eligibility['eligible'] ? 'border-primary' : 'border-warning') ?>">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon-wrapper <?= $hasVoted ? 'bg-success-subtle text-success' : ($eligibility['eligible'] ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning') ?>">
                    <i class="bi <?= $hasVoted ? 'bi-check-circle-fill' : ($eligibility['eligible'] ? 'bi-shield-check' : 'bi-exclamation-triangle-fill') ?>"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="fw-bold mb-1">
                        <?= $hasVoted ? 'Your Ballot is Successfully Recorded' : ($eligibility['eligible'] ? 'You are Eligible to Vote' : 'Voting Ineligible for this Election') ?>
                    </h6>
                    <p class="text-muted small mb-0">
                        <?= $hasVoted ? 'You have already participated in this election. Ballots cannot be modified or re-cast.' : e($eligibility['reason']) ?>
                    </p>
                </div>
            </div>
        </div>
    <?php elseif (!Auth::check()): ?>
        <div class="alert alert-info d-flex align-items-center justify-content-between p-3 mb-4">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <span>Sign in with your verified college credentials to cast your vote.</span>
            </div>
            <a href="<?= base_url('login.php') ?>" class="btn btn-primary btn-sm px-3">Sign In</a>
        </div>
    <?php endif; ?>

    <!-- Contesting Candidates Section -->
    <div class="mb-4">
        <h3 class="h4 fw-bold mb-3"><i class="bi bi-people-fill me-2 text-primary"></i> Certified Candidates</h3>

        <?php if (empty($candidates)): ?>
            <div class="card card-academic p-5 text-center text-muted">
                <i class="bi bi-person-x fs-1 mb-2"></i>
                <h5 class="fw-bold">No Candidates Registered</h5>
                <p class="small mb-0">No active candidates have been approved for this election yet.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($candidates as $cand): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card card-academic h-100 p-4 d-flex flex-column text-center">
                            <!-- Photo or Placeholder -->
                            <div class="mb-3 d-flex justify-content-center">
                                <?php if (!empty($cand['photo_url']) && file_exists(ROOT_PATH . '/public/uploads/candidates/' . $cand['photo_url'])): ?>
                                    <img src="<?= base_url('uploads/candidates/' . e($cand['photo_url'])) ?>" alt="<?= e($cand['name']) ?>" class="candidate-avatar">
                                <?php else: ?>
                                    <div class="candidate-avatar-placeholder">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <h5 class="fw-bold text-dark mb-1"><?= e($cand['name']) ?></h5>
                            <div class="badge bg-primary-subtle text-primary mb-2 align-self-center">
                                <?= e($cand['position']) ?>
                            </div>
                            <div class="text-muted small mb-3">
                                Department: <?= e($cand['department_name'] ?? 'General') ?>
                            </div>

                            <div class="bg-light p-3 rounded-3 small text-secondary text-start mb-3 flex-grow-1" style="max-height: 140px; overflow-y: auto;">
                                <div class="fw-bold text-dark mb-1"><i class="bi bi-chat-quote-fill me-1 text-primary"></i> Campaign Manifesto:</div>
                                <?= nl2br(e($cand['manifesto'] ?? 'No manifesto submitted.')) ?>
                            </div>

                            <!-- Vote Action Button -->
                            <?php if ($election['status'] === 'open' && $eligibility['eligible'] && !$hasVoted): ?>
                                <button type="button" class="btn btn-navy w-100 py-2 fw-semibold" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#voteConfirmationModal"
                                        data-candidate-id="<?= $cand['id'] ?>"
                                        data-candidate-name="<?= e($cand['name']) ?>"
                                        data-candidate-position="<?= e($cand['position']) ?>">
                                    <i class="bi bi-check2-circle me-1"></i> Vote for <?= e($cand['name']) ?>
                                </button>
                            <?php elseif ($hasVoted): ?>
                                <button class="btn btn-outline-success w-100 disabled" disabled>
                                    <i class="bi bi-check-circle-fill me-1"></i> Already Voted
                                </button>
                            <?php else: ?>
                                <button class="btn btn-light border w-100 text-muted disabled" disabled>
                                    Voting Unavailable
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Vote Confirmation Modal -->
<div class="modal fade" id="voteConfirmationModal" tabindex="-1" aria-labelledby="voteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-academic border-0">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="voteModalLabel">
                    <i class="bi bi-shield-check text-primary me-2"></i> Confirm Your Secret Ballot
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= base_url('cast-vote.php') ?>" data-prevent-double-submit="true">
                <?= csrf_field() ?>
                <input type="hidden" name="election_id" value="<?= $election['id'] ?>">
                <input type="hidden" name="candidate_id" id="modalCandidateId" value="">

                <div class="modal-body py-3">
                    <p class="text-secondary small mb-3">
                        You are about to submit your official vote in <strong><?= e($election['title']) ?></strong>.
                    </p>

                    <div class="p-3 bg-light rounded-3 border text-center mb-3">
                        <div class="text-muted small">Selected Nominee:</div>
                        <div class="h5 fw-bold text-dark mb-1" id="modalCandidateName">-</div>
                        <div class="badge bg-primary-subtle text-primary" id="modalCandidatePosition">-</div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 small d-flex align-items-center gap-2 mb-0">
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                        <span>Once submitted, your vote is irrevocable and cannot be updated. Only one vote per eligible student is permitted.</span>
                    </div>
                </div>

                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy px-4 fw-semibold">
                        Confirm & Cast Ballot
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
