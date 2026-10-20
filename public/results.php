<?php
/**
 * College Voting System - Certified Election Results & Statistics
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/services/ResultService.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';

$electionId = (int)($_GET['id'] ?? 0);
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

$db = Database::getConnection();
$resultService = new ResultService($db);
$electionRepo = new ElectionRepository($db);

// Handle direct CSV download
if ($exportCsv && $electionId > 0) {
    $election = $electionRepo->findById($electionId);
    if ($election && ($election['status'] === 'results_published' || Auth::isAdmin())) {
        $csvContent = $resultService->exportResultsCsv($electionId);
        $filename = 'election_results_' . $electionId . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $csvContent;
        exit;
    }
}

$pageTitle = 'Election Results';

// Single election result view
$singleResult = null;
if ($electionId > 0) {
    $election = $electionRepo->findById($electionId);
    if ($election) {
        if ($election['status'] === 'results_published' || Auth::isAdmin()) {
            $singleResult = $resultService->calculateResults($electionId);
        } else {
            set_flash('warning', 'Results for this election have not yet been published by the Election Commission.');
            redirect(base_url('results.php'));
        }
    }
}

// All published elections list
$publishedElections = $electionRepo->getAll(['status' => 'results_published']);

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container py-4 py-md-5">
    <?php if ($singleResult): ?>
        <!-- Specific Election Results View -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= base_url('results.php') ?>">All Results</a></li>
                <li class="breadcrumb-item active"><?= e($singleResult['election']['title']) ?></li>
            </ol>
        </nav>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <span class="badge badge-status badge-published mb-2">Results Certified & Published</span>
                <h1 class="h2 fw-bold text-dark mb-1"><?= e($singleResult['election']['title']) ?></h1>
                <p class="text-secondary small mb-0">
                    Academic Year: <strong><?= e($singleResult['election']['academic_year']) ?></strong> &bull;
                    Voting Concluded: <?= format_datetime($singleResult['election']['end_time']) ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= base_url('results.php?id=' . $electionId . '&export=csv') ?>" class="btn btn-outline-success btn-sm px-3">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
                </a>
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print Summary
                </button>
            </div>
        </div>

        <!-- Outcome / Winner Highlight Banner -->
        <?php if ($singleResult['is_tie']): ?>
            <div class="card card-academic border-warning p-4 mb-4 bg-warning-subtle">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-1"></i>
                    <div>
                        <h4 class="fw-bold text-dark mb-1">Official Tie Detected</h4>
                        <p class="mb-0 text-secondary">
                            <?= e($singleResult['tie_details']) ?> The Election Commission regulations dictate runoff polling or committee resolution.
                        </p>
                    </div>
                </div>
            </div>
        <?php elseif ($singleResult['winner']): ?>
            <div class="card card-academic border-success p-4 mb-4" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                <div class="row align-items-center gy-3">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success text-white px-3 py-1"><i class="bi bi-trophy-fill me-1"></i> Declared Winner</span>
                            <span class="badge bg-white text-secondary border small"><?= e($singleResult['winner']['position']) ?></span>
                        </div>
                        <h2 class="h3 fw-bold text-dark mb-1"><?= e($singleResult['winner']['name']) ?></h2>
                        <p class="text-secondary small mb-0">
                            Department: <strong><?= e($singleResult['winner']['department_name']) ?></strong> &bull;
                            Secured <strong class="text-success"><?= $singleResult['winner']['votes'] ?> votes</strong> (<?= $singleResult['winner']['percentage'] ?>% of valid ballots)
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="display-6 fw-bold text-success"><?= $singleResult['winner']['percentage'] ?>%</div>
                        <div class="text-muted small">Winning Vote Share</div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card card-academic p-4 mb-4 text-center text-muted">
                <i class="bi bi-info-circle fs-2 mb-2"></i>
                <p class="mb-0">No valid ballots were submitted during the election period.</p>
            </div>
        <?php endif; ?>

        <!-- Metric Counter Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card card-academic p-3 text-center">
                    <div class="text-muted small fw-medium">Total Eligible Voters</div>
                    <h3 class="fw-bold mb-0 text-dark"><?= $singleResult['total_eligible_voters'] ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-academic p-3 text-center">
                    <div class="text-muted small fw-medium">Valid Ballots Cast</div>
                    <h3 class="fw-bold mb-0 text-primary"><?= $singleResult['total_votes_cast'] ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-academic p-3 text-center">
                    <div class="text-muted small fw-medium">Official Voter Turnout</div>
                    <h3 class="fw-bold mb-0 text-success"><?= $singleResult['turnout_percentage'] ?>%</h3>
                </div>
            </div>
        </div>

        <!-- Candidate Vote Distribution Table -->
        <div class="card card-academic p-4 mb-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-bar-chart-fill me-2 text-primary"></i> Certified Candidate Vote Tally</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Candidate</th>
                            <th>Position</th>
                            <th>Department</th>
                            <th>Votes Received</th>
                            <th style="width: 35%;">Vote Share</th>
                            <th class="text-end">Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($singleResult['candidates'] as $c): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= e($c['name']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($c['position']) ?></span></td>
                                <td class="text-muted small"><?= e($c['department_name']) ?></td>
                                <td class="fw-bold text-primary"><?= $c['votes'] ?></td>
                                <td>
                                    <div class="progress progress-vote bg-light border">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $c['percentage'] ?>%;" aria-valuenow="<?= $c['percentage'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </td>
                                <td class="text-end fw-bold"><?= $c['percentage'] ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-center">
            <a href="<?= base_url('results.php') ?>" class="btn btn-outline-secondary">
                &larr; View All Published Results
            </a>
        </div>

    <?php else: ?>
        <!-- All Published Elections Overview -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Published Election Results</h1>
                <p class="text-secondary small mb-0">Official outcomes certified by the Election Commission.</p>
            </div>
            <a href="<?= base_url('elections.php') ?>" class="btn btn-outline-primary btn-sm">
                View All Elections
            </a>
        </div>

        <?php if (empty($publishedElections)): ?>
            <div class="card card-academic p-5 text-center text-muted">
                <i class="bi bi-trophy fs-1 mb-2"></i>
                <h5 class="fw-bold mb-1">No Published Results Yet</h5>
                <p class="small mb-0">Once active elections conclude and results are certified, they will be listed here.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($publishedElections as $elec): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card card-academic h-100 p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge badge-status badge-published">Results Published</span>
                                <span class="badge bg-light text-secondary border small"><?= e($elec['academic_year']) ?></span>
                            </div>

                            <h5 class="fw-bold text-dark mb-2"><?= e($elec['title']) ?></h5>
                            <p class="text-secondary small mb-3 flex-grow-1">
                                <?= e(mb_strimwidth((string)$elec['description'], 0, 110, '...')) ?>
                            </p>

                            <div class="small bg-light p-3 rounded-3 mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Concluded:</span>
                                    <span class="fw-medium"><?= format_datetime($elec['end_time'], 'M d, Y') ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Total Votes Cast:</span>
                                    <span class="fw-bold text-primary"><?= (int)$elec['total_votes'] ?></span>
                                </div>
                            </div>

                            <a href="<?= base_url('results.php?id=' . $elec['id']) ?>" class="btn btn-warning fw-semibold text-dark w-100">
                                <i class="bi bi-trophy-fill me-1"></i> View Detailed Results &rarr;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
