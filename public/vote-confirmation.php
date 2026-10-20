<?php
/**
 * College Voting System - Official Vote Confirmation Receipt
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/middleware/require-student.php';
require_once __DIR__ . '/../app/includes/functions.php';

initSession();

$receipt = $_SESSION['last_vote_receipt'] ?? null;

if (!$receipt) {
    redirect(base_url('dashboard.php'));
}

$pageTitle = 'Ballot Confirmation Receipt';
require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card card-academic shadow-sm p-4 p-md-5 text-center">
                <!-- Success Emblem -->
                <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mb-3 mx-auto" style="width: 72px; height: 72px; font-size: 2.2rem;">
                    <i class="bi bi-shield-check"></i>
                </div>

                <h2 class="h3 fw-bold text-dark mb-1">Vote Successfully Cast!</h2>
                <p class="text-secondary small mb-4">
                    Your official ballot has been committed to the election database.
                </p>

                <!-- Receipt Card -->
                <div class="bg-light p-4 rounded-3 border text-start mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <span class="text-muted small fw-semibold text-uppercase">Election Receipt</span>
                        <span class="badge bg-success">Committed & Verified</span>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">Election Title</div>
                        <div class="fw-bold text-dark"><?= e($receipt['election_title']) ?></div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="text-muted small">Academic Year</div>
                            <div class="fw-semibold text-dark"><?= e($receipt['academic_year']) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Recorded Timestamp</div>
                            <div class="fw-semibold text-dark"><?= format_datetime($receipt['voted_at']) ?></div>
                        </div>
                    </div>

                    <div class="p-3 bg-white rounded-2 border text-center">
                        <div class="text-muted small mb-1">Audit Verification Hash</div>
                        <code class="fs-5 fw-bold text-primary tracking-wide">
                            <?= e($receipt['receipt_hash']) ?>
                        </code>
                    </div>
                </div>

                <div class="alert alert-light border small text-muted text-start mb-4">
                    <i class="bi bi-info-circle-fill text-primary me-1"></i>
                    <strong>Ballot Privacy Policy:</strong> Your candidate selection is decoupled from your student identity and does not appear on this receipt to ensure complete vote confidentiality.
                </div>

                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-secondary px-3" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print Receipt
                    </button>
                    <a href="<?= base_url('dashboard.php') ?>" class="btn btn-navy px-4 fw-semibold">
                        <i class="bi bi-speedometer2 me-1"></i> Return to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
