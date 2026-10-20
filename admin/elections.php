<?php
/**
 * College Voting System - Admin Election Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-admin.php';
require_once __DIR__ . '/../app/includes/csrf.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';
require_once __DIR__ . '/../app/services/ElectionService.php';

$pageTitle = 'Manage Elections';
$db = Database::getConnection();
$electionRepo = new ElectionRepository($db);
$electionService = new ElectionService($db);
$electionService->syncStatuses();

$currentUser = Auth::user();

// Handle status updates or deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $electionId = (int)($_POST['election_id'] ?? 0);

    if ($electionId > 0) {
        $elec = $electionRepo->findById($electionId);
        if ($elec) {
            if ($action === 'close') {
                $electionRepo->updateStatus($electionId, 'closed');
                log_audit($db, (int)$currentUser['id'], $currentUser['email'], 'CLOSE_ELECTION', 'election', $electionId, "Closed election '{$elec['title']}'");
                set_flash('success', "Election '{$elec['title']}' was manually closed.");
            } elseif ($action === 'open') {
                $electionRepo->updateStatus($electionId, 'open');
                log_audit($db, (int)$currentUser['id'], $currentUser['email'], 'OPEN_ELECTION', 'election', $electionId, "Manually opened election '{$elec['title']}'");
                set_flash('success', "Election '{$elec['title']}' was opened for voting.");
            } elseif ($action === 'cancel') {
                $electionRepo->updateStatus($electionId, 'cancelled');
                log_audit($db, (int)$currentUser['id'], $currentUser['email'], 'CANCEL_ELECTION', 'election', $electionId, "Cancelled election '{$elec['title']}'");
                set_flash('warning', "Election '{$elec['title']}' was cancelled.");
            } elseif ($action === 'delete') {
                if ((int)$elec['total_votes'] > 0) {
                    set_flash('danger', "Cannot delete an election that already has recorded ballots. Archive or close it instead to preserve audit integrity.");
                } else {
                    $electionRepo->delete($electionId);
                    log_audit($db, (int)$currentUser['id'], $currentUser['email'], 'DELETE_ELECTION', 'election', $electionId, "Deleted draft election '{$elec['title']}'");
                    set_flash('success', "Election '{$elec['title']}' was successfully deleted.");
                }
            }
        }
    }
    redirect(base_url('elections.php'));
}

$statusFilter = $_GET['status'] ?? '';
$filters = [];
if (!empty($statusFilter)) $filters['status'] = $statusFilter;
$elections = $electionRepo->getAll($filters);

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <?php require_once __DIR__ . '/../app/includes/admin-sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Election Management</h1>
                    <p class="text-secondary small mb-0">Configure election timelines, candidate nominations, and polling state transitions.</p>
                </div>
                <a href="<?= base_url('election-form.php') ?>" class="btn btn-navy btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Create New Election
                </a>
            </div>

            <!-- Filter Pills -->
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="<?= base_url('elections.php') ?>" class="btn btn-sm <?= empty($statusFilter) ? 'btn-navy' : 'btn-outline-secondary' ?>">All</a>
                <a href="<?= base_url('elections.php?status=open') ?>" class="btn btn-sm <?= $statusFilter === 'open' ? 'btn-success' : 'btn-outline-success' ?>">Open</a>
                <a href="<?= base_url('elections.php?status=scheduled') ?>" class="btn btn-sm <?= $statusFilter === 'scheduled' ? 'btn-info' : 'btn-outline-info' ?>">Scheduled</a>
                <a href="<?= base_url('elections.php?status=closed') ?>" class="btn btn-sm <?= $statusFilter === 'closed' ? 'btn-secondary' : 'btn-outline-secondary' ?>">Closed</a>
                <a href="<?= base_url('elections.php?status=results_published') ?>" class="btn btn-sm <?= $statusFilter === 'results_published' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' ?>">Published</a>
            </div>

            <div class="card card-academic p-4">
                <?php if (empty($elections)): ?>
                    <p class="text-center text-muted py-5 mb-0">No elections match the filter.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Election Title</th>
                                    <th>Year</th>
                                    <th>Status</th>
                                    <th>Timeline Window</th>
                                    <th>Candidates</th>
                                    <th>Ballots</th>
                                    <th class="text-end">Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($elections as $el): ?>
                                    <tr>
                                        <td class="text-muted small">#<?= $el['id'] ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($el['title']) ?></div>
                                            <span class="badge bg-light text-secondary border small text-capitalize"><?= e($el['election_type']) ?></span>
                                        </td>
                                        <td><?= e($el['academic_year']) ?></td>
                                        <td>
                                            <span class="badge badge-status badge-<?= e($el['status']) ?>">
                                                <?= e(str_replace('_', ' ', $el['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            <div><i class="bi bi-play-circle me-1"></i><?= format_datetime($el['start_time']) ?></div>
                                            <div><i class="bi bi-stop-circle me-1"></i><?= format_datetime($el['end_time']) ?></div>
                                        </td>
                                        <td>
                                            <a href="<?= base_url('candidates.php?election_id=' . $el['id']) ?>" class="badge bg-light text-primary border text-decoration-none">
                                                <?= (int)$el['candidate_count'] ?> Candidates
                                            </a>
                                        </td>
                                        <td class="fw-bold text-primary"><?= (int)$el['total_votes'] ?></td>
                                        <td class="text-end">
                                            <div class="dropdown">
                                                <button class="btn btn-light btn-sm border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <li><a class="dropdown-item" href="<?= base_url('election-form.php?id=' . $el['id']) ?>"><i class="bi bi-pencil me-2"></i> Edit Details</a></li>
                                                    <li><a class="dropdown-item" href="<?= base_url('candidates.php?election_id=' . $el['id']) ?>"><i class="bi bi-people me-2"></i> Manage Candidates</a></li>
                                                    <li><a class="dropdown-item" href="<?= base_url('election-monitoring.php?id=' . $el['id']) ?>"><i class="bi bi-activity me-2"></i> Live Monitor</a></li>
                                                    <li><a class="dropdown-item" href="<?= base_url('results.php?id=' . $el['id']) ?>"><i class="bi bi-award me-2"></i> Results Manager</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <?php if ($el['status'] === 'open'): ?>
                                                        <li>
                                                            <form method="POST" action="<?= base_url('elections.php') ?>" onsubmit="return confirm('Manually close this election to stop accepting new votes?');">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="action" value="close">
                                                                <input type="hidden" name="election_id" value="<?= $el['id'] ?>">
                                                                <button type="submit" class="dropdown-item text-warning"><i class="bi bi-stop-circle me-2"></i> Close Election</button>
                                                            </form>
                                                        </li>
                                                    <?php elseif ($el['status'] === 'scheduled' || $el['status'] === 'closed'): ?>
                                                        <li>
                                                            <form method="POST" action="<?= base_url('elections.php') ?>" onsubmit="return confirm('Open this election for voting right now?');">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="action" value="open">
                                                                <input type="hidden" name="election_id" value="<?= $el['id'] ?>">
                                                                <button type="submit" class="dropdown-item text-success"><i class="bi bi-play-circle me-2"></i> Open Election</button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ((int)$el['total_votes'] === 0): ?>
                                                        <li>
                                                            <form method="POST" action="<?= base_url('elections.php') ?>" onsubmit="return confirm('Are you sure you want to permanently delete this election?');">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="election_id" value="<?= $el['id'] ?>">
                                                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i> Delete Election</button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
