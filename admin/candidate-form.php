<?php
/**
 * College Voting System - Candidate Form (Add / Edit)
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-admin.php';
require_once __DIR__ . '/../app/includes/csrf.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/validation.php';
require_once __DIR__ . '/../app/repositories/CandidateRepository.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';

$db = Database::getConnection();
$candidateRepo = new CandidateRepository($db);
$electionRepo = new ElectionRepository($db);
$currentUser = Auth::user();

$allElections = $electionRepo->getAll();
$stmtDepts = $db->query("SELECT * FROM departments ORDER BY name ASC");
$allDepts = $stmtDepts->fetchAll(PDO::FETCH_ASSOC);

// Registered students for optional link
$stmtStuds = $db->query("SELECT id, full_name, registration_no FROM students ORDER BY full_name ASC");
$allStudents = $stmtStuds->fetchAll(PDO::FETCH_ASSOC);

$candidateId = (int)($_GET['id'] ?? 0);
$isEdit = $candidateId > 0;
$candidate = null;

if ($isEdit) {
    $candidate = $candidateRepo->findById($candidateId);
    if (!$candidate) {
        set_flash('danger', 'Candidate not found.');
        redirect(base_url('candidates.php'));
    }
}

$preSelectedElection = (int)($_GET['election_id'] ?? ($candidate['election_id'] ?? 0));
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $electionId = (int)($_POST['election_id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $position = trim((string)($_POST['position'] ?? ''));
    $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $studentId = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
    $manifesto = trim((string)($_POST['manifesto'] ?? ''));
    $status = (string)($_POST['status'] ?? 'active');

    $validator = new Validator($_POST);
    $validator->required('name', 'Candidate Name')
              ->required('position', 'Contested Position')
              ->integer('election_id', 'Election', 1);

    // Validate photo upload if provided
    $photoFilename = $candidate['photo_url'] ?? null;
    if (!empty($_FILES['photo']['name'])) {
        $photoError = Validator::validateImageUpload($_FILES['photo']);
        if ($photoError) {
            $error = $photoError;
        } else {
            // Generate safe filename
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $safeName = 'cand_' . bin2hex(random_bytes(10)) . '.' . $ext;
            $targetDir = ROOT_PATH . '/public/uploads/candidates/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetDir . $safeName)) {
                $photoFilename = $safeName;
            } else {
                $error = "Failed to store uploaded photo file.";
            }
        }
    }

    if (!$error && $validator->passes()) {
        $data = [
            'election_id' => $electionId,
            'student_id' => $studentId,
            'name' => $name,
            'position' => $position,
            'department_id' => $deptId,
            'manifesto' => $manifesto,
            'photo_url' => $photoFilename,
            'status' => $status
        ];

        try {
            if ($isEdit) {
                $candidateRepo->update($candidateId, $data);
                log_audit($db, (int)$currentUser['id'], $currentUser['email'], 'UPDATE_CANDIDATE', 'candidate', $candidateId, "Updated candidate '{$name}'");
                set_flash('success', "Candidate '{$name}' was successfully updated.");
            } else {
                $newId = $candidateRepo->create($data);
                log_audit($db, (int)$currentUser['id'], $currentUser['email'], 'CREATE_CANDIDATE', 'candidate', $newId, "Added candidate '{$name}' for election #{$electionId}");
                set_flash('success', "Candidate '{$name}' was nominated successfully.");
            }
            redirect(base_url('candidates.php?election_id=' . $electionId));
        } catch (Throwable $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else if (!$error) {
        $error = $validator->firstError();
    }
}

$pageTitle = $isEdit ? 'Edit Candidate' : 'Add Candidate';
require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <?php require_once __DIR__ . '/../app/includes/admin-sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="<?= base_url('candidates.php') ?>">Candidates</a></li>
                    <li class="breadcrumb-item active"><?= $isEdit ? 'Edit Candidate' : 'New Nominee' ?></li>
                </ol>
            </nav>

            <div class="card card-academic p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        <h2 class="h4 fw-bold mb-1"><?= $isEdit ? 'Edit Candidate Nomination' : 'Nominate New Election Candidate' ?></h2>
                        <p class="text-secondary small mb-0">Enter candidate profile, campaign statement, and position.</p>
                    </div>
                    <a href="<?= base_url('candidates.php') ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 px-3 small d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= base_url('candidate-form.php' . ($isEdit ? '?id=' . $candidateId : '')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="election_id" class="form-label small fw-semibold">Assigned Election *</label>
                            <select class="form-select" id="election_id" name="election_id" required>
                                <option value="">Select Election...</option>
                                <?php foreach ($allElections as $el): ?>
                                    <option value="<?= $el['id'] ?>" <?= ($preSelectedElection == $el['id']) ? 'selected' : '' ?>>
                                        <?= e($el['title']) ?> (<?= e($el['academic_year']) ?> - <?= e($el['status']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="position" class="form-label small fw-semibold">Contested Position *</label>
                            <input type="text" class="form-control" id="position" name="position" value="<?= e($candidate['position'] ?? '') ?>" placeholder="e.g. Student Council President" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label small fw-semibold">Candidate Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= e($candidate['name'] ?? '') ?>" placeholder="e.g. Rahul Sharma" required>
                        </div>
                        <div class="col-md-6">
                            <label for="department_id" class="form-label small fw-semibold">Candidate Department</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">General / None...</option>
                                <?php foreach ($allDepts as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= (($candidate['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>>
                                        <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="student_id" class="form-label small fw-semibold">Link Registered Student (Optional)</label>
                            <select class="form-select" id="student_id" name="student_id">
                                <option value="">None / External Nominee...</option>
                                <?php foreach ($allStudents as $st): ?>
                                    <option value="<?= $st['id'] ?>" <?= (($candidate['student_id'] ?? '') == $st['id']) ? 'selected' : '' ?>>
                                        <?= e($st['full_name']) ?> (<?= e($st['registration_no']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label small fw-semibold">Nomination Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= ($candidate['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active (On Ballot)</option>
                                <option value="withdrawn" <?= ($candidate['status'] ?? '') === 'withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
                                <option value="disqualified" <?= ($candidate['status'] ?? '') === 'disqualified' ? 'selected' : '' ?>>Disqualified</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="photo" class="form-label small fw-semibold">Profile Photo (JPG, PNG, WebP &bull; Max 2MB)</label>
                        <input type="file" class="form-control" id="photo" name="photo" accept="image/png, image/jpeg, image/webp">
                        <?php if (!empty($candidate['photo_url'])): ?>
                            <div class="form-text mt-1 text-success">
                                Current photo: <code><?= e($candidate['photo_url']) ?></code>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label for="manifesto" class="form-label small fw-semibold">Campaign Manifesto / Platform Statement</label>
                        <textarea class="form-control" id="manifesto" name="manifesto" rows="4" placeholder="Detail the candidate's agenda, campus proposals, and commitments to student voters..."><?= e($candidate['manifesto'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-navy px-4 fw-semibold">
                            <?= $isEdit ? 'Save Changes' : 'Nominate Candidate' ?>
                        </button>
                        <a href="<?= base_url('candidates.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
