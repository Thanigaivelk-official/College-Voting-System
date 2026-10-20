<?php
/**
 * College Voting System - Voter Whitelist & CSV Import Manager
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-admin.php';
require_once __DIR__ . '/../app/includes/csrf.php';
require_once __DIR__ . '/../app/includes/functions.php';

$pageTitle = 'Voter Whitelist Management';
$db = Database::getConnection();
$currentUser = Auth::user();

$importStats = null;
$error = null;

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_csv') {
    require_csrf();

    if (empty($_FILES['csv_file']['tmp_name'])) {
        $error = "Please select a valid CSV file to upload.";
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');

        if ($handle === false) {
            $error = "Failed to open uploaded CSV file.";
        } else {
            $importedCount = 0;
            $skippedCount = 0;
            $matchedStudents = 0;

            // Read header
            $header = fgetcsv($handle);

            $stmtCheck = $db->prepare("SELECT id FROM whitelist_registration_numbers WHERE registration_no = ?");
            $stmtInsert = $db->prepare("
                INSERT INTO whitelist_registration_numbers (registration_no, full_name, department_code, academic_year, is_registered)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtFindStud = $db->prepare("SELECT id, user_id FROM students WHERE registration_no = ?");
            $stmtVerifyStud = $db->prepare("UPDATE students SET is_verified = 1, verified_at = NOW(), verified_by = ? WHERE id = ?");

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 4) {
                    $skippedCount++;
                    continue;
                }

                $regNo = trim(strtoupper((string)$row[0]));
                $name = trim((string)$row[1]);
                $deptCode = trim(strtoupper((string)$row[2]));
                $year = (int)$row[3];

                if (empty($regNo) || empty($name)) {
                    $skippedCount++;
                    continue;
                }

                // Check duplicate in whitelist
                $stmtCheck->execute([$regNo]);
                if ($stmtCheck->fetch()) {
                    $skippedCount++;
                    continue;
                }

                // Check if student already registered in system
                $stmtFindStud->execute([$regNo]);
                $stud = $stmtFindStud->fetch(PDO::FETCH_ASSOC);
                $isRegistered = $stud ? 1 : 0;

                try {
                    $stmtInsert->execute([$regNo, $name, $deptCode, $year, $isRegistered]);
                    $importedCount++;

                    // Auto-verify if student is already created
                    if ($stud) {
                        try {
                            $stmtVerifyStud->execute([(int)$currentUser['id'], $stud['id']]);
                        } catch (Exception $e) {
                            $now = date('Y-m-d H:i:s');
                            $stmtFallback = $db->prepare("UPDATE students SET is_verified = 1, verified_at = ?, verified_by = ? WHERE id = ?");
                            $stmtFallback->execute([$now, (int)$currentUser['id'], $stud['id']]);
                        }
                        $matchedStudents++;
                    }
                } catch (Exception $e) {
                    $skippedCount++;
                }
            }
            fclose($handle);

            log_audit(
                $db,
                (int)$currentUser['id'],
                $currentUser['email'],
                'IMPORT_WHITELIST',
                'whitelist',
                null,
                "Imported {$importedCount} voter records via CSV ({$matchedStudents} registered students auto-verified)."
            );

            $importStats = [
                'imported' => $importedCount,
                'skipped' => $skippedCount,
                'matched' => $matchedStudents
            ];

            set_flash('success', "Import completed: {$importedCount} records added, {$matchedStudents} existing student accounts auto-verified.");
        }
    }
}

// Fetch whitelist items
$stmtWhitelist = $db->query("SELECT * FROM whitelist_registration_numbers ORDER BY id DESC LIMIT 50");
$whitelistItems = $stmtWhitelist->fetchAll(PDO::FETCH_ASSOC);

// Total counts
$totalWhitelisted = (int)($db->query("SELECT COUNT(*) AS cnt FROM whitelist_registration_numbers")->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
$totalRegisteredFromWhitelist = (int)($db->query("SELECT COUNT(*) AS cnt FROM whitelist_registration_numbers WHERE is_registered = 1")->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row g-4">
        <?php require_once __DIR__ . '/../app/includes/admin-sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Voter Eligibility Whitelist</h1>
                    <p class="text-secondary small mb-0">Pre-approve enrolled registration numbers for instant automated student verification.</p>
                </div>
            </div>

            <!-- Import Card -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card card-academic p-4 h-100">
                        <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-arrow-up text-primary"></i> Batch CSV Whitelist Upload
                        </h5>

                        <?php if ($error): ?>
                            <div class="alert alert-danger py-2 px-3 small mb-3"><?= e($error) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="<?= base_url('eligibility.php') ?>" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="import_csv">

                            <div class="mb-3">
                                <label for="csv_file" class="form-label small fw-semibold">Select CSV Roster File *</label>
                                <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                                <div class="form-text small">
                                    CSV Format required: <code>registration_no,full_name,department_code,academic_year</code>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-navy w-100 py-2 fw-semibold">
                                <i class="bi bi-upload me-1"></i> Upload & Process Whitelist
                            </button>
                        </form>

                        <div class="mt-3 p-3 bg-light rounded-2 small text-muted">
                            <div class="fw-bold text-dark mb-1">Example CSV Content:</div>
                            <pre class="mb-0 text-dark font-monospace" style="font-size: 0.75rem;">registration_no,full_name,department_code,academic_year
REG2024009,Akash Patel,CSE,3
REG2024010,Kavita Rao,ECE,2</pre>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card card-academic p-4 h-100">
                        <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-pie-chart text-success"></i> Whitelist Statistics
                        </h5>

                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <div class="p-3 bg-light rounded-3 text-center">
                                    <div class="fs-3 fw-bold text-dark"><?= $totalWhitelisted ?></div>
                                    <div class="text-muted small">Total Pre-Approved</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 bg-light rounded-3 text-center">
                                    <div class="fs-3 fw-bold text-success"><?= $totalRegisteredFromWhitelist ?></div>
                                    <div class="text-muted small">Active Accounts Claimed</div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-light border small text-muted mb-0">
                            <i class="bi bi-shield-check text-success me-1"></i>
                            <strong>Eligibility Policy:</strong> When new students register with a registration number found in this list, their account is instantly verified. If an unverified account already exists, uploading this roster will auto-verify them.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Whitelist Table -->
            <div class="card card-academic p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Enrolled Student Whitelist Records</h5>
                    <span class="text-muted small">Showing latest 50 records</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Reg No.</th>
                                <th>Student Full Name</th>
                                <th>Department</th>
                                <th>Year</th>
                                <th>Account State</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($whitelistItems as $item): ?>
                                <tr>
                                    <td><code class="text-primary fw-bold"><?= e($item['registration_no']) ?></code></td>
                                    <td class="fw-semibold text-dark"><?= e($item['full_name']) ?></td>
                                    <td><span class="badge bg-light text-secondary border"><?= e($item['department_code']) ?></span></td>
                                    <td>Year <?= (int)$item['academic_year'] ?></td>
                                    <td>
                                        <?php if ($item['is_registered']): ?>
                                            <span class="badge bg-success-subtle text-success">Account Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Unclaimed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
