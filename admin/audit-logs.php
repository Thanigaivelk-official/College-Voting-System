<?php
/**
 * College Voting System - Administrative Audit Logs Viewer
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-admin.php';
require_once __DIR__ . '/../app/includes/functions.php';

$pageTitle = 'Audit Logs';
$db = Database::getConnection();

$actionFilter = $_GET['action'] ?? '';
$searchFilter = $_GET['search'] ?? '';

$sql = "SELECT * FROM audit_logs WHERE 1=1";
$params = [];

if (!empty($actionFilter)) {
    $sql .= " AND action = ?";
    $params[] = $actionFilter;
}

if (!empty($searchFilter)) {
    $sql .= " AND (summary LIKE ? OR actor_email LIKE ?)";
    $params[] = "%{$searchFilter}%";
    $params[] = "%{$searchFilter}%";
}

$sql .= " ORDER BY created_at DESC LIMIT 100";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct actions for dropdown
$stmtActions = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");
$distinctActions = $stmtActions->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <?php require_once __DIR__ . '/../app/includes/admin-sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Administrative Audit Trail</h1>
                    <p class="text-secondary small mb-0">Immutable records of election management actions, voter verifications, and publications.</p>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="card card-academic p-3 mb-4">
                <form method="GET" action="<?= base_url('audit-logs.php') ?>" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <select class="form-select form-select-sm" name="action" onchange="this.form.submit()">
                            <option value="">All Administrative Actions...</option>
                            <?php foreach ($distinctActions as $act): ?>
                                <option value="<?= e($act) ?>" <?= $act === $actionFilter ? 'selected' : '' ?>>
                                    <?= e($act) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" name="search" value="<?= e($searchFilter) ?>" placeholder="Search actor email or summary...">
                            <button class="btn btn-navy" type="submit">Filter Logs</button>
                            <?php if (!empty($actionFilter) || !empty($searchFilter)): ?>
                                <a href="<?= base_url('audit-logs.php') ?>" class="btn btn-outline-secondary">Reset</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Audit Logs Table -->
            <div class="card card-academic p-4 mb-4">
                <?php if (empty($logs)): ?>
                    <p class="text-center text-muted py-5 mb-0">No audit logs matching the criteria.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Actor</th>
                                    <th>Action</th>
                                    <th>Target Object</th>
                                    <th>Summary Description</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td class="text-muted text-nowrap"><?= format_datetime($l['created_at'], 'M d, Y h:i:s A') ?></td>
                                        <td class="fw-semibold text-dark"><?= e($l['actor_email'] ?? 'System') ?></td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary font-monospace">
                                                <?= e($l['action']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border text-capitalize">
                                                <?= e($l['object_type']) ?> <?= $l['object_id'] ? '#' . (int)$l['object_id'] : '' ?>
                                            </span>
                                        </td>
                                        <td class="text-secondary"><?= e($l['summary']) ?></td>
                                        <td><code class="text-muted"><?= e($l['ip_address']) ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Security Architecture Note -->
            <div class="card card-academic p-4 bg-light border-0">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-shield-shaded text-primary fs-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Audit Trail & Privacy Compliance</h6>
                        <p class="text-secondary small mb-0">
                            Administrative actions such as election scheduling, student account verification, and result publication are logged for accountability. By design, student candidate selections and ballots are <strong>never</strong> recorded in audit logs, guaranteeing that audit inspectability does not compromise student ballot secrecy.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
