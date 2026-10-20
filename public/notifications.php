<?php
/**
 * College Voting System - In-App Notifications Feed
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-login.php';
require_once __DIR__ . '/../app/includes/csrf.php';
require_once __DIR__ . '/../app/includes/functions.php';

$pageTitle = 'Notifications';
$db = Database::getConnection();
$user = Auth::user();
$userId = (int)$user['id'];

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    require_csrf();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
    set_flash('success', 'All notifications have been marked as read.');
    redirect(base_url('notifications.php'));
}

// Fetch user notifications
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <!-- Sidebar depending on role -->
        <?php if (Auth::isAdmin()): ?>
            <?php require_once __DIR__ . '/../app/includes/admin-sidebar.php'; ?>
        <?php else: ?>
            <?php require_once __DIR__ . '/../app/includes/student-sidebar.php'; ?>
        <?php endif; ?>

        <div class="col-lg-9 col-xl-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Notifications Feed</h1>
                    <p class="text-secondary small mb-0">System updates, voter verification notices, and election announcements.</p>
                </div>
                <?php if (!empty($notifications)): ?>
                    <form method="POST" action="<?= base_url('notifications.php') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-check2-all me-1"></i> Mark All as Read
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card card-academic p-4">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-bell-slash fs-1 mb-2"></i>
                        <h5 class="fw-bold mb-1">No Notifications Yet</h5>
                        <p class="small mb-0">You're all caught up! You will be notified when elections open or results are published.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $n): ?>
                            <div class="list-group-item px-0 py-3 <?= empty($n['is_read']) ? 'bg-light-subtle' : '' ?>">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="stat-icon-wrapper bg-<?= e($n['type']) ?>-subtle text-<?= e($n['type']) ?> flex-shrink-0" style="width: 40px; height: 40px; font-size: 1.1rem;">
                                        <i class="bi <?= $n['type'] === 'success' ? 'bi-check-circle-fill' : ($n['type'] === 'danger' ? 'bi-exclamation-octagon-fill' : ($n['type'] === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-bell-fill')) ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <h6 class="fw-bold text-dark mb-0">
                                                <?= e($n['title']) ?>
                                                <?php if (empty($n['is_read'])): ?>
                                                    <span class="badge bg-primary ms-1" style="font-size: 0.65rem;">New</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted"><?= format_datetime($n['created_at']) ?></small>
                                        </div>
                                        <p class="text-secondary small mb-0"><?= e($n['message']) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
