<?php
/**
 * College Voting System - Shared Footer Layout
 */

declare(strict_types=1);
?>

<footer class="app-footer">
    <div class="container-fluid px-3 px-md-4">
        <div class="row align-items-center gy-2">
            <div class="col-md-6 text-center text-md-start">
                <span class="fw-semibold text-dark">College Voting System</span> &copy; <?= date('Y') ?> &bull; Student Council Election Commission
            </div>
            <div class="col-md-6 text-center text-md-end">
                <span class="badge bg-light text-secondary border me-2">
                    <i class="bi bi-shield-check text-success me-1"></i> Transaction-Safe &bull; Decoupled Ballot Model
                </span>
                <span class="text-muted small">PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?> &bull; MySQL 8.x Ready</span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS (Includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Application JS -->
<script src="<?= base_url('assets/js/app.js') ?>"></script>

</body>
</html>
