<?php
/**
 * College Voting System - Student Roster & Verification Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-admin.php';
require_once __DIR__ . '/../app/includes/csrf.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/repositories/StudentRepository.php';

$pageTitle = 'Student Roster';
$db = Database::getConnection();
$studentRepo = new StudentRepository($db);
$currentUser = Auth::user();

// Handle manual student verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);

    if ($studentId > 0 && $action === 'verify') {
        $student = $studentRepo->findById($studentId);
        if ($student) {
            $studentRepo->verifyStudent($studentId, (int)$currentUser['id']);
            log_audit($db, (int)$currentUser['id'], $currentUser['email'], 'VERIFY_STUDENT', 'student', $studentId, "Verified student '{$student['full_name']}' ({$student['registration_no']})");
            set_flash('success', "Student '{$student['full_name']}' has been officially verified and can now vote.");
        }
    }
    redirect(base_url('students.php'));
}

$deptFilter = (int)($_GET['department_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$searchFilter = $_GET['search'] ?? '';

$filters = [];
if ($deptFilter > 0) $filters['department_id'] = $deptFilter;
if ($statusFilter === 'verified') $filters['is_verified'] = 1;
if ($statusFilter === 'pending') $filters['is_verified'] = 0;
if (!empty($searchFilter)) $filters['search'] = $searchFilter;

$students = $studentRepo->getAll($filters);
$stats = $studentRepo->getStats();

$stmtDepts = $db->query("SELECT * FROM departments ORDER BY name ASC");
$allDepts = $stmtDepts->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <?php require_once __DIR__ . '/../app/includes/admin-sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Student Roster & Verification</h1>
                    <p class="text-secondary small mb-0">Verify student identities, maintain roster integrity, and control voting permissions.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= base_url('eligibility.php') ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-earmark-arrow-up me-1"></i> Import Voter Whitelist
                    </a>
                </div>
            </div>

            <!-- Summary Badges -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card card-academic p-3 text-center">
                        <div class="text-muted small">Total Registered</div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $stats['total_students'] ?></h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-academic p-3 text-center">
                        <div class="text-muted small">Verified by Commission</div>
                        <h4 class="fw-bold mb-0 text-success"><?= $stats['verified_students'] ?></h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-academic p-3 text-center">
                        <div class="text-muted small">Pending Verification</div>
                        <h4 class="fw-bold mb-0 text-warning"><?= $stats['pending_students'] ?></h4>
                    </div>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="card card-academic p-3 mb-4">
                <form method="GET" action="<?= base_url('students.php') ?>" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <select class="form-select form-select-sm" name="department_id" onchange="this.form.submit()">
                            <option value="">All Departments...</option>
                            <?php foreach ($allDepts as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $d['id'] == $deptFilter ? 'selected' : '' ?>>
                                    <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                            <option value="">All Statuses...</option>
                            <option value="verified" <?= $statusFilter === 'verified' ? 'selected' : '' ?>>Verified Only</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" name="search" value="<?= e($searchFilter) ?>" placeholder="Search student name, reg no, or email...">
                            <button class="btn btn-navy" type="submit">Filter</button>
                            <?php if ($deptFilter > 0 || !empty($statusFilter) || !empty($searchFilter)): ?>
                                <a href="<?= base_url('students.php') ?>" class="btn btn-outline-secondary">Reset</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Students Table -->
            <div class="card card-academic p-4">
                <?php if (empty($students)): ?>
                    <p class="text-center text-muted py-5 mb-0">No registered students found matching filters.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Reg No.</th>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Year</th>
                                    <th>Voter Status</th>
                                    <th class="text-end">Verification</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $st): ?>
                                    <tr>
                                        <td>
                                            <code class="text-primary fw-bold"><?= e($st['registration_no']) ?></code>
                                        </td>
                                        <td class="fw-semibold text-dark"><?= e($st['full_name']) ?></td>
                                        <td class="small text-muted"><?= e($st['email']) ?></td>
                                        <td><span class="badge bg-light text-secondary border"><?= e($st['department_code']) ?></span></td>
                                        <td>Year <?= (int)$st['academic_year'] ?></td>
                                        <td>
                                            <?php if ($st['is_verified']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <i class="bi bi-patch-check-fill me-1"></i> Verified
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-clock-history me-1"></i> Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if (!$st['is_verified']): ?>
                                                <form method="POST" action="<?= base_url('students.php') ?>" class="d-inline" onsubmit="return confirm('Verify student <?= e($st['full_name']) ?> to participate in elections?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="verify">
                                                    <input type="hidden" name="student_id" value="<?= $st['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success px-3">
                                                        <i class="bi bi-check2 me-1"></i> Verify Student
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <small class="text-muted"><i class="bi bi-check-all text-success"></i> Approved</small>
                                            <?php endif; ?>
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
