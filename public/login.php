<?php
/**
 * College Voting System - User Authentication & Login
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/csrf.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/validation.php';

initSession();

// Redirect if already logged in
if (Auth::check()) {
    redirect(Auth::isAdmin() ? admin_url('dashboard.php') : public_url('dashboard.php'));
}

$error = null;
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $emailInput = trim((string)($_POST['email'] ?? ''));
    $passwordInput = (string)($_POST['password'] ?? '');

    $validator = new Validator($_POST);
    $validator->required('email', 'Email Address')
              ->email('email', 'Email Address')
              ->required('password', 'Password');

    if ($validator->passes()) {
        $result = Auth::login($emailInput, $passwordInput);
        if ($result['success']) {
            set_flash('success', 'Welcome back! You have successfully signed in.');
            if ($result['role'] === 'admin') {
                redirect(admin_url('dashboard.php'));
            } else {
                redirect(public_url('dashboard.php'));
            }
        } else {
            $error = $result['error'];
        }
    } else {
        $error = $validator->firstError();
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card card-academic shadow-sm p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; font-size: 1.6rem;">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Portal Sign In</h3>
                    <p class="text-secondary small">Access voter services or administrative election commission tools</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 px-3 small d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= base_url('login.php') ?>" data-prevent-double-submit="true">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold">College Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($emailInput) ?>" placeholder="user@college.edu" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label small fw-semibold mb-0">Password</label>
                            <span class="text-muted small">Case-sensitive</span>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-navy w-100 py-2 fw-semibold mb-3">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Account
                    </button>
                </form>

                <div class="text-center small text-muted mb-4">
                    New student? <a href="<?= base_url('register.php') ?>" class="text-primary fw-semibold text-decoration-none">Create a student account</a>
                </div>

                <!-- Demo Credentials Quick Switcher -->
                <div class="p-3 bg-light rounded-3 border">
                    <div class="fw-bold small text-dark mb-2 d-flex align-items-center gap-1">
                        <i class="bi bi-stars text-warning"></i> Demo Credentials (Click to fill)
                    </div>
                    <div class="d-flex flex-column gap-1 small">
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start py-1 px-2 demo-btn" data-email="thanigaivel@college.edu" data-pass="Admin@123">
                            <i class="bi bi-shield-fill-check text-danger me-1"></i> <strong>Admin (Thanigaivel):</strong> thanigaivel@college.edu / Admin@123
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start py-1 px-2 demo-btn" data-email="admin@college.edu" data-pass="Admin@123">
                            <i class="bi bi-shield-lock text-secondary me-1"></i> <strong>Admin:</strong> admin@college.edu / Admin@123
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start py-1 px-2 demo-btn" data-email="arun@student.college.edu" data-pass="Student@123">
                            <i class="bi bi-check-circle text-success me-1"></i> <strong>Verified Voter:</strong> arun@student.college.edu / Student@123
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start py-1 px-2 demo-btn" data-email="suresh@student.college.edu" data-pass="Student@123">
                            <i class="bi bi-clock-history text-warning me-1"></i> <strong>Unverified Voter:</strong> suresh@student.college.edu / Student@123
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.demo-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('email').value = btn.getAttribute('data-email');
        document.getElementById('password').value = btn.getAttribute('data-pass');
    });
});
</script>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
