<?php
/**
 * College Voting System - Secure Vote Casting Endpoint
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/require-student.php';
require_once __DIR__ . '/../app/includes/csrf.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/services/VotingService.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('elections.php'));
}

require_csrf();

$electionId = (int)($_POST['election_id'] ?? 0);
$candidateId = (int)($_POST['candidate_id'] ?? 0);

if ($electionId <= 0 || $candidateId <= 0) {
    set_flash('danger', 'Invalid election or candidate selected.');
    redirect(base_url('elections.php'));
}

$db = Database::getConnection();
$user = Auth::user();
$student = $user['student'] ?? null;
$studentId = (int)($student['id'] ?? 0);

if ($studentId <= 0) {
    set_flash('danger', 'Student profile record could not be resolved.');
    redirect(base_url('dashboard.php'));
}

$votingService = new VotingService($db);
$electionRepo = new ElectionRepository($db);
$election = $electionRepo->findById($electionId);

if (!$election) {
    set_flash('danger', 'Election not found.');
    redirect(base_url('elections.php'));
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

try {
    $result = $votingService->castVote(
        $electionId,
        $studentId,
        $candidateId,
        $ipAddress,
        $userAgent
    );

    // Store non-sensitive receipt in session for one-time confirmation display
    $_SESSION['last_vote_receipt'] = [
        'election_id' => $electionId,
        'election_title' => $election['title'],
        'academic_year' => $election['academic_year'],
        'receipt_hash' => $result['receipt_hash'],
        'voted_at' => $result['voted_at']
    ];

    redirect(base_url('vote-confirmation.php'));

} catch (RuntimeException $e) {
    set_flash('danger', $e->getMessage());
    redirect(base_url("election-details.php?id={$electionId}"));
} catch (Throwable $e) {
    error_log("Critical vote casting error: " . $e->getMessage());
    set_flash('danger', 'An unexpected error occurred during vote processing. No ballot was cast. Please try again.');
    redirect(base_url("election-details.php?id={$electionId}"));
}
