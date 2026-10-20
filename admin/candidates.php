<?php
/**
 * College Voting System - Admin Candidate Roster
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-admin.php';
require_once __DIR__ . '/../app/includes/csrf.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/repositories/CandidateRepository.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';

$pageTitle = 'Candidate Management';
$db = Database::getConnection();
$candidateRepo = new CandidateRepository($db);
$electionRepo = new ElectionRepository($db);
$currentUser = Auth::user();

// Handle deletion or status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $candidateId = (int)($_POST['candidate_id'] ?? 0);

    if ($candidateId > 0) {
        $cand = $candidateRepo->findById($candidateId);
        if ($cand) {
            if ($action === 'withdraw') {
                $candidateRepo->update($candidateId, array_merge($cand, ['status' => 'withdrawn']));
                log_audit($db, (int)$currentUser['id'], $currentUser['email'], 'WITHDRAW_CANDIDATE', 'candidate', $candidateId, "Marked candidate '{$cand['name']}' as withdrawn");
                set_flash('info', "Candidate '{$cand['name']}' has been marked as withdrawn.");
            } elseif ($action === 'activate') {
                $candidateRepo->update($candidateId, array_merge($cand, ['status' => 'active']));
                log_audit($db, (int)$currentUser['id'], $currentUser['email'], 'ACTIVATE_CANDIDATE', 'candidate', $candidateId, "Activated candidate '{$cand['name']}'");
                set_flash('success', "Candidate '{$cand['name']}' is now active.");
            } elseif ($action === 'delete') {
                if ((int)$cand['vote_count'] > 0) {
                    set_flash('danger', "Cannot delete a candidate who has received recorded ballots. Mark as withdrawn instead.");
                } else {
                    $candidateRepo->delete($candidateId);
                    log_audit($db, (int)$currentUser['id'], $currentUser['email'], 'DELETE_CANDIDATE', 'candidate', $candidateId, "Deleted candidate nomination '{$cand['name']}'");
                    set_flash('success', "Candidate '{$cand['name']}' was removed.");
                }
            }
        }
    }
    redirect(base_url('candidates.php' . (!empty($_GET['election_id']) ? '?election_id=' . (int)$_GET['election_id'] : '')));
}

$electionFilter = (int)($_GET['election_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$searchFilter = $_GET['search'] ?? '';

$filters = [];
if ($electionFilter > 0) $filters['election_id'] = $electionFilter;
if (!empty($statusFilter)) $filters['status'] = $statusFilter;
if (!empty($searchFilter)) $filters['search'] = $searchFilter;

$candidates = $candidateRepo->getAll($filters);
$allElections = $electionRepo->getAll();

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <?php require_once __DIR__ . '/../app/includes/admin-sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Candidate Management</h1>
                    <p class="text-secondary small mb-0">Approve nominee profiles, campaign manifestos, and position assignments.</p>
                </div>
                <a href="<?= base_url('candidate-form.php' . ($electionFilter > 0 ? '?election_id=' . $electionFilter : '')) ?>" class="btn btn-navy btn-sm">
                    <i class="bi bi-person-plus-fill me-1"></i> Add Nominee
                </a>
            </div>

            <!-- Filter Controls -->
            <div class="card card-academic p-3 mb-4">
                <form method="GET" action="<?= base_url('candidates.php') ?>" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <select class="form-select form-select-sm" name="election_id" onchange="this.form.submit()">
                            <option value="">All Elections...</option>
                            <?php foreach ($allElections as $el): ?>
                                <option value="<?= $el['id'] ?>" <?= $el['id'] == $electionFilter ? 'selected' : '' ?>>
                                    <?= e($el['title']) ?> (<?= e($el['status']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                            <option value="">All Statuses...</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                            <option value="withdrawn" <?= $statusFilter === 'withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" name="search" value="<?= e($searchFilter) ?>" placeholder="Search candidate or position...">
                            <button class="btn btn-navy" type="submit">Filter</button>
                            <?php if ($electionFilter > 0 || !empty($statusFilter) || !empty($searchFilter)): ?>
                                <a href="<?= base_url('candidates.php') ?>" class="btn btn-outline-secondary">Reset</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Candidates Table -->
            <div class="card card-academic p-4">
                <?php if (empty($candidates)): ?>
                    <p class="text-center text-muted py-5 mb-0">No candidates found for the chosen filters.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Candidate</th>
                                    <th>Position Contested</th>
                                    <th>Department</th>
                                    <th>Assigned Election</th>
                                    <th>Status</th>
                                    <th>Votes Received</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidates as $cand): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <?php if (!empty($cand['photo_url']) && file_exists(ROOT_PATH . '/public/uploads/candidates/' . $cand['photo_url'])): ?>
                                                    <img src="<?= base_url('uploads/candidates/' . e($cand['photo_url'])) ?>" class="rounded-circle border" style="width: 42px; height: 42px; object-fit: cover;" alt="">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-light border text-secondary d-flex align-items-center justify-content-center fw-bold" style="width: 42px; height: 42px;">
                                                        <?= strtoupper(substr($cand['name'], 0, 1)) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-bold text-dark"><?= e($cand['name']) ?></div>
                                                    <small class="text-muted"><?= e($cand['student_reg_no'] ?? 'External Nomination') ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-primary-subtle text-primary"><?= e($cand['position']) ?></span></td>
                                        <td><small class="text-secondary"><?= e($cand['department_name'] ?? 'General') ?></small></td>
                                        <td>
                                            <div class="small fw-semibold text-dark"><?= e($cand['election_title']) ?></div>
                                            <span class="badge badge-status badge-<?= e($cand['election_status']) ?>" style="font-size: 0.65rem;"><?= e($cand['election_status']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $cand['status'] === 'active' ? 'success' : 'secondary' ?>-subtle text-<?= $cand['status'] === 'active' ? 'success' : 'secondary' ?> text-capitalize">
                                                <?= e($cand['status']) ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-primary"><?= (int)$cand['vote_count'] ?></td>
                                        <td class="text-end">
                                            <div class="dropdown">
                                                <button class="btn btn-light btn-sm border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Manage
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <li><a class="dropdown-item" href="<?= base_url('candidate-form.php?id=' . $cand['id']) ?>"><i class="bi bi-pencil me-2"></i> Edit Details</a></li>
                                                    <?php if ($cand['status'] === 'active'): ?>
                                                        <li>
                                                            <form method="POST" action="<?= base_url('candidates.php') ?>" onsubmit="return confirm('Withdraw candidate from election?');">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="action" value="withdraw">
                                                                <input type="hidden" name="candidate_id" value="<?= $cand['id'] ?>">
                                                                <button type="submit" class="dropdown-item text-warning"><i class="bi bi-x-circle me-2"></i> Withdraw</button>
                                                            </form>
                                                        </li>
                                                    <?php else: ?>
                                                        <li>
                                                            <form method="POST" action="<?= base_url('candidates.php') ?>">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="action" value="activate">
                                                                <input type="hidden" name="candidate_id" value="<?= $cand['id'] ?>">
                                                                <button type="submit" class="dropdown-item text-success"><i class="bi bi-check-circle me-2"></i> Reactivate</button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ((int)$cand['vote_count'] === 0): ?>
                                                        <li>
                                                            <form method="POST" action="<?= base_url('candidates.php') ?>" onsubmit="return confirm('Permanently delete candidate?');">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="candidate_id" value="<?= $cand['id'] ?>">
                                                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i> Delete</button>
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
