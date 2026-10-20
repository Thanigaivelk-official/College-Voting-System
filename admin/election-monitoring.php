<?php
/**
 * College Voting System - Live Election Monitoring Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-admin.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';
require_once __DIR__ . '/../app/repositories/BallotRepository.php';
require_once __DIR__ . '/../app/repositories/CandidateRepository.php';

$pageTitle = 'Live Election Monitoring';
$db = Database::getConnection();
$electionRepo = new ElectionRepository($db);
$ballotRepo = new BallotRepository($db);
$candidateRepo = new CandidateRepository($db);

$allElections = $electionRepo->getAll();
$electionId = (int)($_GET['id'] ?? ($allElections[0]['id'] ?? 0));

$election = $electionRepo->findById($electionId);
if (!$election) {
    redirect(base_url('elections.php'));
}

$totalVotes = $ballotRepo->getTotalVotes($electionId);
$eligibleCount = $ballotRepo->getEligibleVotersCount($electionId);
$turnoutPct = $eligibleCount > 0 ? round(($totalVotes / $eligibleCount) * 100, 2) : 0.0;

$candidates = $candidateRepo->getByElectionId($electionId);

// Recent participation stream (timestamps & receipt hashes only - preserves ballot secrecy!)
$stmtRecent = $db->prepare("
    SELECT vp.voted_at, vp.receipt_hash, vp.ip_address, s.full_name, s.registration_no, d.code AS dept_code
    FROM voter_participation vp
    JOIN students s ON vp.student_id = s.id
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE vp.election_id = ?
    ORDER BY vp.voted_at DESC
    LIMIT 20
");
$stmtRecent->execute([$electionId]);
$recentParticipants = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <?php require_once __DIR__ . '/../app/includes/admin-sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10">
            <!-- Header & Election Selector -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Live Election Monitoring</h1>
                    <p class="text-secondary small mb-0">Real-time participation tracking, turnout metrics, and verified audit stream.</p>
                </div>
                <div style="min-width: 280px;">
                    <form method="GET" action="<?= base_url('election-monitoring.php') ?>">
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

            <!-- Election Headline Card -->
            <div class="card card-academic p-4 mb-4 border-top border-4 border-primary">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <span class="badge badge-status badge-<?= e($election['status']) ?> mb-2">
                            <?= e(str_replace('_', ' ', $election['status'])) ?>
                        </span>
                        <h2 class="h4 fw-bold text-dark mb-1"><?= e($election['title']) ?></h2>
                        <div class="text-muted small">
                            Academic Year: <strong><?= e($election['academic_year']) ?></strong> &bull;
                            Category: <strong class="text-capitalize"><?= e($election['election_type']) ?></strong>
                        </div>
                    </div>
                    <div>
                        <a href="<?= base_url('results.php?id=' . $electionId) ?>" class="btn btn-outline-warning text-dark btn-sm fw-semibold">
                            <i class="bi bi-award me-1"></i> Results Manager
                        </a>
                    </div>
                </div>

                <div class="row g-3 bg-light p-3 rounded-3 mt-2">
                    <div class="col-sm-6 col-md-3">
                        <div class="text-muted small">Voting Started</div>
                        <div class="fw-semibold text-dark"><i class="bi bi-play-circle me-1 text-primary"></i><?= format_datetime($election['start_time']) ?></div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="text-muted small">Voting Deadline</div>
                        <div class="fw-semibold text-dark"><i class="bi bi-stop-circle me-1 text-danger"></i><?= format_datetime($election['end_time']) ?></div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="text-muted small">Candidates Contesting</div>
                        <div class="fw-semibold text-dark"><i class="bi bi-people me-1 text-info"></i><?= count($candidates) ?> Nominees</div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="text-muted small">Integrity Model</div>
                        <div class="fw-semibold text-success"><i class="bi bi-shield-check me-1"></i>Decoupled Ballots</div>
                    </div>
                </div>
            </div>

            <!-- Real-Time Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card card-academic p-3 text-center">
                        <div class="text-muted small fw-medium">Eligible Voters</div>
                        <h3 class="fw-bold mb-0 text-dark"><?= $eligibleCount ?></h3>
                        <div class="small text-muted">Verified & Qualified Students</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-academic p-3 text-center">
                        <div class="text-muted small fw-medium">Committed Ballots</div>
                        <h3 class="fw-bold mb-0 text-primary"><?= $totalVotes ?></h3>
                        <div class="small text-success"><i class="bi bi-check-circle me-1"></i>ACID Transactions</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-academic p-3 text-center">
                        <div class="text-muted small fw-medium">Current Turnout</div>
                        <h3 class="fw-bold mb-0 text-success"><?= $turnoutPct ?>%</h3>
                        <div class="progress progress-vote bg-light border mt-2">
                            <div class="progress-bar bg-success" style="width: <?= $turnoutPct ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Live Voter Check-Ins Table -->
            <div class="card card-academic p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-broadcast me-2 text-danger"></i> Live Voter Participation Stream</h5>
                    <span class="text-muted small">Latest 20 voter check-ins</span>
                </div>

                <?php if (empty($recentParticipants)): ?>
                    <p class="text-muted text-center py-4 mb-0">No votes cast yet in this election.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Voter Registration No</th>
                                    <th>Student Name</th>
                                    <th>Department</th>
                                    <th>Verification Receipt Hash</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentParticipants as $p): ?>
                                    <tr>
                                        <td class="small text-muted"><?= format_datetime($p['voted_at'], 'M d, h:i:s A') ?></td>
                                        <td><code class="text-primary fw-bold"><?= e($p['registration_no']) ?></code></td>
                                        <td class="fw-semibold text-dark"><?= e($p['full_name']) ?></td>
                                        <td><span class="badge bg-light text-secondary border"><?= e($p['dept_code']) ?></span></td>
                                        <td><code class="text-dark bg-light px-2 py-1 rounded small"><?= e($p['receipt_hash']) ?></code></td>
                                        <td><span class="badge bg-success-subtle text-success"><i class="bi bi-shield-check me-1"></i> Recorded</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 small text-muted">
                        <i class="bi bi-shield-lock-fill text-primary me-1"></i>
                        <strong>Privacy Notice:</strong> In compliance with electoral integrity standards, candidate selections are never shown in live monitoring or logged on this stream.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
