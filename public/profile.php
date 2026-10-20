<?php
/**
 * College Voting System - User Profile & Security Settings
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-login.php';
require_once __DIR__ . '/../app/includes/csrf.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/validation.php';

$pageTitle = 'My Profile';
$db = Database::getConnection();
$user = Auth::user();
$student = $user['student'] ?? [];

$passwordError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $currentPass = (string)($_POST['current_password'] ?? '');
    $newPass = (string)($_POST['new_password'] ?? '');
    $confirmPass = (string)($_POST['confirm_password'] ?? '');

    $validator = new Validator($_POST);
    $validator->required('current_password', 'Current Password')
              ->required('new_password', 'New Password')
              ->passwordStrength('new_password', 'New Password')
              ->matches('new_password', 'confirm_password', 'Password Confirmation');

    if ($validator->passes()) {
        if (!password_verify($currentPass, $user['password_hash'])) {
            $passwordError = "Current password is incorrect.";
        } else {
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newHash, (int)$user['id']]);
            log_audit($db, (int)$user['id'], $user['email'], 'CHANGE_PASSWORD', 'user', (int)$user['id'], 'User changed account password');
            set_flash('success', 'Your password has been successfully updated.');
            redirect(base_url('profile.php'));
        }
    } else {
        $passwordError = $validator->firstError();
    }
}

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <?php if (Auth::isAdmin()): ?>
            <?php require_once __DIR__ . '/../app/includes/admin-sidebar.php'; ?>
        <?php else: ?>
            <?php require_once __DIR__ . '/../app/includes/student-sidebar.php'; ?>
        <?php endif; ?>

        <div class="col-lg-9 col-xl-10">
            <h1 class="h3 fw-bold mb-1">Account & Security Profile</h1>
            <p class="text-secondary small mb-4">View verified voter credentials and manage account credentials.</p>

            <div class="row g-4">
                <!-- Profile Information Card -->
                <div class="col-md-6">
                    <div class="card card-academic p-4 h-100">
                        <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-person-badge-fill text-primary"></i> Identification Details
                        </h5>

                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">Full Name</label>
                            <div class="fw-bold text-dark fs-5">
                                <?= e($student['full_name'] ?? $user['admin']['full_name'] ?? 'User') ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">Email Address</label>
                            <div class="fw-semibold text-dark"><?= e($user['email']) ?></div>
                        </div>

                        <?php if (Auth::isStudent()): ?>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label text-muted small mb-1">Registration No</label>
                                    <div class="fw-bold text-primary"><?= e($student['registration_no'] ?? 'N/A') ?></div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-muted small mb-1">Department</label>
                                    <div class="fw-semibold text-dark"><?= e($student['department_name'] ?? 'General') ?></div>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label text-muted small mb-1">Degree / Course</label>
                                    <div class="fw-semibold text-dark"><?= e($student['course'] ?? 'N/A') ?></div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-muted small mb-1">Year of Study</label>
                                    <div class="fw-semibold text-dark">Year <?= e((string)($student['academic_year'] ?? 1)) ?></div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted small mb-1">Commission Verification</label>
                                <div>
                                    <?php if (!empty($student['is_verified'])): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-3 fw-semibold">
                                            <i class="bi bi-patch-check-fill me-1"></i> Officially Verified Voter
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark py-2 px-3 fw-semibold">
                                            <i class="bi bi-clock-history me-1"></i> Verification Pending Review
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label text-muted small mb-1">Administrative Role</label>
                                <span class="badge bg-danger text-uppercase px-3 py-2">Election Commission Admin</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Password Update Card -->
                <div class="col-md-6">
                    <div class="card card-academic p-4 h-100">
                        <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-key-fill text-primary"></i> Change Password
                        </h5>

                        <?php if ($passwordError): ?>
                            <div class="alert alert-danger py-2 px-3 small d-flex align-items-center gap-2 mb-3">
                                <i class="bi bi-exclamation-circle-fill"></i>
                                <span><?= e($passwordError) ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= base_url('profile.php') ?>">
                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <label for="current_password" class="form-label small fw-semibold">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label small fw-semibold">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Min. 8 characters with Upper, Lower & Number" required>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label small fw-semibold">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>

                            <button type="submit" class="btn btn-navy w-100 py-2 fw-semibold">
                                <i class="bi bi-shield-lock me-1"></i> Update Security Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
