<?php
/**
 * College Voting System - Student Registration
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/csrf.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/validation.php';
require_once __DIR__ . '/../app/repositories/StudentRepository.php';

initSession();

if (Auth::check()) {
    redirect(base_url(Auth::isAdmin() ? '../admin/dashboard.php' : 'dashboard.php'));
}

$db = Database::getConnection();
$studentRepo = new StudentRepository($db);

// Fetch departments for dropdown
$stmtDepts = $db->query("SELECT * FROM departments ORDER BY name ASC");
$departments = $stmtDepts->fetchAll(PDO::FETCH_ASSOC);

$error = null;
$formData = [
    'full_name' => '',
    'email' => '',
    'registration_no' => '',
    'department_id' => '',
    'course' => '',
    'academic_year' => '1'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $formData = [
        'full_name' => trim((string)($_POST['full_name'] ?? '')),
        'email' => trim(strtolower((string)($_POST['email'] ?? ''))),
        'registration_no' => trim(strtoupper((string)($_POST['registration_no'] ?? ''))),
        'department_id' => (string)($_POST['department_id'] ?? ''),
        'course' => trim((string)($_POST['course'] ?? '')),
        'academic_year' => (string)($_POST['academic_year'] ?? '1')
    ];
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirmation'] ?? '');

    $validator = new Validator($_POST);
    $validator->required('full_name', 'Full Name')
              ->minLength('full_name', 3, 'Full Name')
              ->required('email', 'Email Address')
              ->email('email', 'Email Address')
              ->required('registration_no', 'Registration Number')
              ->minLength('registration_no', 4, 'Registration Number')
              ->required('department_id', 'Department')
              ->required('course', 'Course / Degree')
              ->required('academic_year', 'Academic Year')
              ->integer('academic_year', 'Academic Year', 1, 5)
              ->required('password', 'Password')
              ->passwordStrength('password', 'Password')
              ->matches('password', 'password_confirmation', 'Password Confirmation');

    if ($validator->passes()) {
        // Check uniqueness of email
        $stmtCheckEmail = $db->prepare("SELECT COUNT(*) AS cnt FROM users WHERE email = ?");
        $stmtCheckEmail->execute([$formData['email']]);
        if ((int)$stmtCheckEmail->fetch(PDO::FETCH_ASSOC)['cnt'] > 0) {
            $error = "The email address '{$formData['email']}' is already registered.";
        }

        // Check uniqueness of registration number
        $stmtCheckReg = $db->prepare("SELECT COUNT(*) AS cnt FROM students WHERE registration_no = ?");
        $stmtCheckReg->execute([$formData['registration_no']]);
        if (!$error && (int)$stmtCheckReg->fetch(PDO::FETCH_ASSOC)['cnt'] > 0) {
            $error = "The registration number '{$formData['registration_no']}' is already associated with an existing student account.";
        }

        if (!$error) {
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $userData = [
                    'email' => $formData['email'],
                    'password_hash' => $passwordHash
                ];
                $studentData = [
                    'full_name' => $formData['full_name'],
                    'registration_no' => $formData['registration_no'],
                    'department_id' => (int)$formData['department_id'],
                    'course' => $formData['course'],
                    'academic_year' => (int)$formData['academic_year']
                ];

                $studentId = $studentRepo->createStudent($userData, $studentData);

                // Check verification state
                $created = $studentRepo->findById($studentId);
                if ($created && $created['is_verified']) {
                    set_flash('success', 'Registration successful! Your registration number was matched with the verified whitelist. You can now log in.');
                } else {
                    set_flash('info', 'Registration successful! Your student profile is pending verification by the Election Commission. Once approved, you can cast votes.');
                }

                redirect(base_url('login.php'));
            } catch (Throwable $e) {
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    } else {
        $error = $validator->firstError();
    }
}

$pageTitle = 'Student Registration';
require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-7">
            <div class="card card-academic shadow-sm p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; font-size: 1.6rem;">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Student Voter Registration</h3>
                    <p class="text-secondary small">Register to participate in official campus council elections</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 px-3 small d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= base_url('register.php') ?>" data-prevent-double-submit="true">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label small fw-semibold">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($formData['full_name']) ?>" placeholder="e.g. Ananya Iyer" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label small fw-semibold">College Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($formData['email']) ?>" placeholder="ananya@student.college.edu" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="registration_no" class="form-label small fw-semibold">Student Registration No. *</label>
                            <input type="text" class="form-control text-uppercase" id="registration_no" name="registration_no" value="<?= e($formData['registration_no']) ?>" placeholder="REG2024006" required>
                            <div class="form-text" style="font-size: 0.75rem;">Whitelisted numbers auto-verify instantly.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="department_id" class="form-label small fw-semibold">Department *</label>
                            <select class="form-select" id="department_id" name="department_id" required>
                                <option value="">Select Department...</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>" <?= (string)$dept['id'] === $formData['department_id'] ? 'selected' : '' ?>>
                                        <?= e($dept['name']) ?> (<?= e($dept['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="course" class="form-label small fw-semibold">Course / Degree Program *</label>
                            <input type="text" class="form-control" id="course" name="course" value="<?= e($formData['course']) ?>" placeholder="e.g. B.E. Computer Science" required>
                        </div>
                        <div class="col-md-4">
                            <label for="academic_year" class="form-label small fw-semibold">Year of Study *</label>
                            <select class="form-select" id="academic_year" name="academic_year" required>
                                <option value="1" <?= $formData['academic_year'] === '1' ? 'selected' : '' ?>>Year 1 (Freshman)</option>
                                <option value="2" <?= $formData['academic_year'] === '2' ? 'selected' : '' ?>>Year 2 (Sophomore)</option>
                                <option value="3" <?= $formData['academic_year'] === '3' ? 'selected' : '' ?>>Year 3 (Junior)</option>
                                <option value="4" <?= $formData['academic_year'] === '4' ? 'selected' : '' ?>>Year 4 (Senior)</option>
                                <option value="5" <?= $formData['academic_year'] === '5' ? 'selected' : '' ?>>Year 5 (PG / Integrated)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="password" class="form-label small fw-semibold">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Min. 8 chars, 1 upper, 1 number" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label small fw-semibold">Confirm Password *</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Re-type password" required>
                        </div>
                    </div>

                    <div class="alert alert-light border small text-muted mb-4">
                        <i class="bi bi-shield-lock text-primary me-1"></i>
                        Passwords are encrypted with industry-standard bcrypt hashing. Student registration numbers are verified against official registrar lists.
                    </div>

                    <button type="submit" class="btn btn-navy w-100 py-2 fw-semibold">
                        <i class="bi bi-person-plus-fill me-1"></i> Complete Registration
                    </button>
                </form>

                <div class="text-center small text-muted mt-3">
                    Already registered? <a href="<?= base_url('login.php') ?>" class="text-primary fw-semibold text-decoration-none">Sign in here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
