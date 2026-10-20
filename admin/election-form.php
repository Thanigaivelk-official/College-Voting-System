<?php
/**
 * College Voting System - Election Form (Create / Edit)
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-admin.php';
require_once __DIR__ . '/../app/includes/csrf.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/validation.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';
require_once __DIR__ . '/../app/services/ElectionService.php';

$db = Database::getConnection();
$electionRepo = new ElectionRepository($db);
$electionService = new ElectionService($db);
$currentUser = Auth::user();

// Departments for eligibility checkbox
$stmtDepts = $db->query("SELECT * FROM departments ORDER BY name ASC");
$allDepartments = $stmtDepts->fetchAll(PDO::FETCH_ASSOC);

$electionId = (int)($_GET['id'] ?? 0);
$isEdit = $electionId > 0;
$election = null;

$selectedDepts = [];
$selectedYears = [];

if ($isEdit) {
    $election = $electionRepo->findById($electionId);
    if (!$election) {
        set_flash('danger', 'Election not found.');
        redirect(base_url('elections.php'));
    }
    // Fetch eligibility rules
    $stmtRules = $db->prepare("SELECT department_id, academic_year FROM election_eligibility WHERE election_id = ?");
    $stmtRules->execute([$electionId]);
    $rules = $stmtRules->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rules as $r) {
        if ($r['department_id']) $selectedDepts[] = (int)$r['department_id'];
        if ($r['academic_year']) $selectedYears[] = (int)$r['academic_year'];
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $academicYear = trim((string)($_POST['academic_year'] ?? ''));
    $electionType = (string)($_POST['election_type'] ?? 'general');
    $startTime = str_replace('T', ' ', (string)($_POST['start_time'] ?? ''));
    $endTime = str_replace('T', ' ', (string)($_POST['end_time'] ?? ''));
    $status = (string)($_POST['status'] ?? 'draft');
    $eligibilityType = (string)($_POST['eligibility_type'] ?? 'all_students');

    $deptRules = array_map('intval', $_POST['eligible_departments'] ?? []);
    $yearRules = array_map('intval', $_POST['eligible_years'] ?? []);

    $validator = new Validator($_POST);
    $validator->required('title', 'Election Title')
              ->required('academic_year', 'Academic Year')
              ->required('start_time', 'Start Date & Time')
              ->required('end_time', 'End Date & Time');

    if ($validator->passes()) {
        if (strtotime($endTime) <= strtotime($startTime)) {
            $error = "Election End Date & Time must be later than Start Date & Time.";
        } else {
            $electionData = [
                'title' => $title,
                'description' => $description,
                'academic_year' => $academicYear,
                'election_type' => $electionType,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => $status,
                'eligibility_type' => $eligibilityType,
                'created_by' => (int)$currentUser['id']
            ];

            $rulePayload = [
                'departments' => $deptRules,
                'academic_years' => $yearRules
            ];

            try {
                if ($isEdit) {
                    $electionService->updateElection($electionId, $electionData, $rulePayload);
                    log_audit($db, (int)$currentUser['id'], $currentUser['email'], 'UPDATE_ELECTION', 'election', $electionId, "Updated election '{$title}'");
                    set_flash('success', "Election '{$title}' was successfully updated.");
                } else {
                    $newId = $electionService->createElection($electionData, $rulePayload);
                    log_audit($db, (int)$currentUser['id'], $currentUser['email'], 'CREATE_ELECTION', 'election', $newId, "Created new election '{$title}'");
                    set_flash('success', "Election '{$title}' was successfully created.");
                }
                redirect(base_url('elections.php'));
            } catch (Throwable $e) {
                $error = "Failed to save election: " . $e->getMessage();
            }
        }
    } else {
        $error = $validator->firstError();
    }
}

$pageTitle = $isEdit ? 'Edit Election' : 'Create Election';
require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <?php require_once __DIR__ . '/../app/includes/admin-sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="<?= base_url('elections.php') ?>">Elections</a></li>
                    <li class="breadcrumb-item active"><?= $isEdit ? 'Edit Election' : 'New Election' ?></li>
                </ol>
            </nav>

            <div class="card card-academic p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        <h2 class="h4 fw-bold mb-1"><?= $isEdit ? 'Edit Election Configuration' : 'Create New Campus Election' ?></h2>
                        <p class="text-secondary small mb-0">Define election metadata, schedule windows, and student eligibility parameters.</p>
                    </div>
                    <a href="<?= base_url('elections.php') ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 px-3 small d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= base_url('election-form.php' . ($isEdit ? '?id=' . $electionId : '')) ?>">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="title" class="form-label small fw-semibold">Election Title *</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?= e($election['title'] ?? '') ?>" placeholder="e.g. Student Council General Election 2026-2027" required>
                        </div>
                        <div class="col-md-4">
                            <label for="academic_year" class="form-label small fw-semibold">Academic Year *</label>
                            <input type="text" class="form-control" id="academic_year" name="academic_year" value="<?= e($election['academic_year'] ?? '2026-2027') ?>" placeholder="2026-2027" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label small fw-semibold">Description / Scope</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Outline the objective, positions contested, and guidelines for students..."><?= e($election['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="election_type" class="form-label small fw-semibold">Election Category</label>
                            <select class="form-select" id="election_type" name="election_type">
                                <option value="general" <?= ($election['election_type'] ?? '') === 'general' ? 'selected' : '' ?>>General (Campus-Wide)</option>
                                <option value="departmental" <?= ($election['election_type'] ?? '') === 'departmental' ? 'selected' : '' ?>>Departmental</option>
                                <option value="club" <?= ($election['election_type'] ?? '') === 'club' ? 'selected' : '' ?>>Club & Committee</option>
                                <option value="special" <?= ($election['election_type'] ?? '') === 'special' ? 'selected' : '' ?>>Special Resolution</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label small fw-semibold">Status Lifecycle</label>
                            <select class="form-select" id="status" name="status">
                                <option value="draft" <?= ($election['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="scheduled" <?= ($election['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                <option value="open" <?= ($election['status'] ?? '') === 'open' ? 'selected' : '' ?>>Open (Accepting Votes)</option>
                                <option value="closed" <?= ($election['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                                <option value="cancelled" <?= ($election['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="start_time" class="form-label small fw-semibold">Start Date & Time *</label>
                            <input type="datetime-local" class="form-control" id="start_time" name="start_time" value="<?= !empty($election['start_time']) ? date('Y-m-d\TH:i', strtotime($election['start_time'])) : '' ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_time" class="form-label small fw-semibold">End Date & Time *</label>
                            <input type="datetime-local" class="form-control" id="end_time" name="end_time" value="<?= !empty($election['end_time']) ? date('Y-m-d\TH:i', strtotime($election['end_time'])) : '' ?>" required>
                        </div>
                    </div>

                    <!-- Voter Eligibility Rules Section -->
                    <div class="bg-light p-3 p-md-4 rounded-3 border mb-4">
                        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-shield-check text-primary"></i> Voter Eligibility Rules
                        </h6>

                        <div class="mb-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="eligibility_type" id="elig_all" value="all_students" <?= ($election['eligibility_type'] ?? 'all_students') === 'all_students' ? 'checked' : '' ?> onchange="toggleEligibilityBlocks()">
                                <label class="form-check-label small fw-semibold" for="elig_all">All Verified Students</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="eligibility_type" id="elig_dept" value="by_department" <?= ($election['eligibility_type'] ?? '') === 'by_department' ? 'checked' : '' ?> onchange="toggleEligibilityBlocks()">
                                <label class="form-check-label small fw-semibold" for="elig_dept">Filter by Department</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="eligibility_type" id="elig_year" value="by_year" <?= ($election['eligibility_type'] ?? '') === 'by_year' ? 'checked' : '' ?> onchange="toggleEligibilityBlocks()">
                                <label class="form-check-label small fw-semibold" for="elig_year">Filter by Year</label>
                            </div>
                        </div>

                        <!-- Department Selection Block -->
                        <div id="deptBlock" class="mt-3 pt-3 border-top" style="display: none;">
                            <label class="form-label small fw-semibold">Select Eligible Departments:</label>
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($allDepartments as $d): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="eligible_departments[]" value="<?= $d['id'] ?>" id="dept_<?= $d['id'] ?>" <?= in_array((int)$d['id'], $selectedDepts, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="dept_<?= $d['id'] ?>"><?= e($d['name']) ?> (<?= e($d['code']) ?>)</label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Year Selection Block -->
                        <div id="yearBlock" class="mt-3 pt-3 border-top" style="display: none;">
                            <label class="form-label small fw-semibold">Select Eligible Academic Years:</label>
                            <div class="d-flex flex-wrap gap-3">
                                <?php for ($y = 1; $y <= 5; $y++): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="eligible_years[]" value="<?= $y ?>" id="yr_<?= $y ?>" <?= in_array($y, $selectedYears, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="yr_<?= $y ?>">Year <?= $y ?></label>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-navy px-4 fw-semibold">
                            <?= $isEdit ? 'Save Changes' : 'Create Election' ?>
                        </button>
                        <a href="<?= base_url('elections.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleEligibilityBlocks() {
    const isDept = document.getElementById('elig_dept').checked;
    const isYear = document.getElementById('elig_year').checked;
    document.getElementById('deptBlock').style.display = isDept ? 'block' : 'none';
    document.getElementById('yearBlock').style.display = isYear ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleEligibilityBlocks);
</script>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
