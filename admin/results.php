<?php
/**
 * College Voting System - Admin Election Results & Publication Manager
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-admin.php';
require_once __DIR__ . '/../app/includes/csrf.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/services/ResultService.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';

$pageTitle = 'Results Manager';
$db = Database::getConnection();
$resultService = new ResultService($db);
$electionRepo = new ElectionRepository($db);
$currentUser = Auth::user();

$allElections = $electionRepo->getAll();
$electionId = (int)($_GET['id'] ?? ($allElections[0]['id'] ?? 0));

$election = $electionRepo->findById($electionId);
if (!$election) {
    redirect(base_url('elections.php'));
}

// Handle publication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish') {
    require_csrf();
    try {
        $resultService->publishResults($electionId, (int)$currentUser['id']);
        set_flash('success', "Official results for '{$election['title']}' have been finalized and published.");
    } catch (Throwable $e) {
        set_flash('danger', "Publication failed: " . $e->getMessage());
    }
    redirect(base_url('results.php?id=' . $electionId));
}

$results = $resultService->calculateResults($electionId);

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <?php require_once __DIR__ . '/../app/includes/admin-sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Results & Outcome Certification</h1>
                    <p class="text-secondary small mb-0">Compute vote distributions, detect ties, and publish official election outcomes.</p>
                </div>
                <div style="min-width: 280px;">
                    <form method="GET" action="<?= base_url('results.php') ?>">
                        <select class="form-select form-select-sm" name="id" onchange="this.form.submit()">
                            <?php foreach ($allElections as $el): ?>
                                <option value="<?= $el['id'] ?>" <?= $el['id'] == $electionId ? 'selected' : '' ?>>
                                    <?= e($el['title']) ?> (<?= e($el['status']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Election Status & Actions Banner -->
            <div class="card card-academic p-4 mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <span class="badge badge-status badge-<?= e($election['status']) ?> mb-2">
                            <?= e(str_replace('_', ' ', $election['status'])) ?>
                        </span>
                        <h2 class="h4 fw-bold text-dark mb-1"><?= e($election['title']) ?></h2>
                        <div class="text-muted small">
                            Academic Year: <strong><?= e($election['academic_year']) ?></strong> &bull;
                            Voting Ended: <?= format_datetime($election['end_time']) ?>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= base_url('../public/results.php?id=' . $electionId . '&export=csv') ?>" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
                        </a>
                        <?php if ($election['status'] !== 'results_published'): ?>
                            <form method="POST" action="<?= base_url('results.php?id=' . $electionId) ?>" onsubmit="return confirm('Publish certified election results to the student portal and notify voters?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="publish">
                                <button type="submit" class="btn btn-navy btn-sm">
                                    <i class="bi bi-trophy-fill me-1"></i> Finalize & Publish Results
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-3 fw-semibold">
                                <i class="bi bi-check-circle-fill me-1"></i> Certified & Published to Students
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Winner or Tie Alert -->
            <?php if ($results['is_tie']): ?>
                <div class="alert alert-warning p-4 mb-4">
                    <h5 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Official Election Tie Detected</h5>
                    <p class="mb-0"><?= e($results['tie_details']) ?></p>
                </div>
            <?php elseif ($results['winner']): ?>
                <div class="card card-academic border-success p-4 mb-4" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-success text-white mb-2"><i class="bi bi-trophy-fill me-1"></i> Projected Winner</span>
                            <h3 class="fw-bold text-dark mb-1"><?= e($results['winner']['name']) ?></h3>
                            <div class="text-secondary small">
                                Position: <strong><?= e($results['winner']['position']) ?></strong> &bull;
                                Secured <strong><?= $results['winner']['votes'] ?> votes</strong> (<?= $results['winner']['percentage'] ?>% share)
                            </div>
                        </div>
                        <div class="display-6 fw-bold text-success"><?= $results['winner']['percentage'] ?>%</div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-light border p-3 mb-4 text-muted">
                    <i class="bi bi-info-circle me-1"></i> Zero votes have been submitted for this election.
                </div>
            <?php endif; ?>

            <!-- Metric Counters -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card card-academic p-3 text-center">
                        <div class="text-muted small">Total Eligible Voters</div>
                        <h3 class="fw-bold mb-0 text-dark"><?= $results['total_eligible_voters'] ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-academic p-3 text-center">
                        <div class="text-muted small">Valid Ballots Cast</div>
                        <h3 class="fw-bold mb-0 text-primary"><?= $results['total_votes_cast'] ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-academic p-3 text-center">
                        <div class="text-muted small">Official Voter Turnout</div>
                        <h3 class="fw-bold mb-0 text-success"><?= $results['turnout_percentage'] ?>%</h3>
                    </div>
                </div>
            </div>

            <!-- Candidate Breakdown Table -->
            <div class="card card-academic p-4">
                <h5 class="fw-bold mb-3">Candidate Vote Tally Breakdown</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Candidate Name</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Votes</th>
                                <th style="width: 40%;">Vote Share</th>
                                <th class="text-end">Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['candidates'] as $c): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= e($c['name']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= e($c['position']) ?></span></td>
                                    <td><small class="text-muted"><?= e($c['department_name']) ?></small></td>
                                    <td class="fw-bold text-primary"><?= $c['votes'] ?></td>
                                    <td>
                                        <div class="progress progress-vote bg-light border">
                                            <div class="progress-bar bg-primary" style="width: <?= $c['percentage'] ?>%;"></div>
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold"><?= $c['percentage'] ?>%</td>
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
