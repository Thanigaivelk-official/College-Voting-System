<?php
/**
 * College Voting System - Secure Administrative CSV Export Handler
 * Prevents CSV / Spreadsheet Formula Injection Attacks
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-admin.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/services/ResultService.php';

$type = $_GET['type'] ?? 'elections';
$db = Database::getConnection();
$currentUser = Auth::user();

$filename = 'export_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $type) . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Audit export operation
log_audit(
    $db,
    (int)$currentUser['id'],
    $currentUser['email'],
    'EXPORT_REPORT',
    'report',
    null,
    "Exported {$type} administrative report to CSV"
);

if ($type === 'results') {
    $electionId = (int)($_GET['election_id'] ?? 0);
    $resultService = new ResultService($db);
    echo $resultService->exportResultsCsv($electionId);
    exit;
}

if ($type === 'elections') {
    fputcsv($output, ['ID', 'Title', 'Academic Year', 'Type', 'Status', 'Start Time', 'End Time', 'Eligibility Rule', 'Total Votes']);
    $stmt = $db->query("
        SELECT e.id, e.title, e.academic_year, e.election_type, e.status, e.start_time, e.end_time, e.eligibility_type,
               (SELECT COUNT(*) FROM voter_participation vp WHERE vp.election_id = e.id) AS total_votes
        FROM elections e
        ORDER BY e.start_time DESC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            sanitize_csv_field($row['title']),
            sanitize_csv_field($row['academic_year']),
            sanitize_csv_field($row['election_type']),
            sanitize_csv_field($row['status']),
            $row['start_time'],
            $row['end_time'],
            sanitize_csv_field($row['eligibility_type']),
            $row['total_votes']
        ]);
    }
} elseif ($type === 'students') {
    fputcsv($output, ['ID', 'Registration No', 'Full Name', 'Email', 'Department', 'Course', 'Year', 'Verified', 'Registered At']);
    $stmt = $db->query("
        SELECT s.id, s.registration_no, s.full_name, u.email, d.name AS dept_name, s.course, s.academic_year, s.is_verified, s.created_at
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN departments d ON s.department_id = d.id
        ORDER BY s.id ASC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            sanitize_csv_field($row['registration_no']),
            sanitize_csv_field($row['full_name']),
            sanitize_csv_field($row['email']),
            sanitize_csv_field($row['dept_name'] ?? 'General'),
            sanitize_csv_field($row['course']),
            $row['academic_year'],
            $row['is_verified'] ? 'Yes' : 'No',
            $row['created_at']
        ]);
    }
} elseif ($type === 'candidates') {
    fputcsv($output, ['ID', 'Candidate Name', 'Contested Position', 'Election Title', 'Department', 'Status', 'Votes Received']);
    $stmt = $db->query("
        SELECT c.id, c.name, c.position, e.title AS election_title, d.name AS dept_name, c.status,
               (SELECT COUNT(*) FROM ballots b WHERE b.candidate_id = c.id) AS vote_count
        FROM candidates c
        JOIN elections e ON c.election_id = e.id
        LEFT JOIN departments d ON c.department_id = d.id
        ORDER BY c.election_id DESC, c.position ASC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            sanitize_csv_field($row['name']),
            sanitize_csv_field($row['position']),
            sanitize_csv_field($row['election_title']),
            sanitize_csv_field($row['dept_name'] ?? 'General'),
            sanitize_csv_field($row['status']),
            $row['vote_count']
        ]);
    }
} elseif ($type === 'department_participation') {
    fputcsv($output, ['Department Code', 'Department Name', 'Total Students', 'Verified Voters', 'Votes Cast Across Elections']);
    $stmt = $db->query("
        SELECT d.code, d.name,
               (SELECT COUNT(*) FROM students s WHERE s.department_id = d.id) AS total_students,
               (SELECT COUNT(*) FROM students s WHERE s.department_id = d.id AND s.is_verified = 1) AS verified_students,
               (SELECT COUNT(*) FROM voter_participation vp JOIN students s ON vp.student_id = s.id WHERE s.department_id = d.id) AS total_votes_cast
        FROM departments d
        ORDER BY d.code ASC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            sanitize_csv_field($row['code']),
            sanitize_csv_field($row['name']),
            $row['total_students'],
            $row['verified_students'],
            $row['total_votes_cast']
        ]);
    }
}

fclose($output);
exit;
