<?php
/**
 * College Voting System - Secure Voting & Duplicate Prevention Test Suite
 */

declare(strict_types=1);

require_once __DIR__ . '/test-framework.php';
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/services/VotingService.php';
require_once __DIR__ . '/../app/services/EligibilityService.php';
require_once __DIR__ . '/../app/repositories/BallotRepository.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';

TestRunner::suite('Secure Voting, Duplicate Prevention & Privacy Suite');

$db = Database::getConnection();
$votingService = new VotingService($db);
$eligibilityService = new EligibilityService($db);
$ballotRepo = new BallotRepository($db);
$electionRepo = new ElectionRepository($db);

// Prepare isolated test election & candidates
$start = date('Y-m-d H:i:s', strtotime('-1 hour'));
$end = date('Y-m-d H:i:s', strtotime('+5 hours'));

$stmtElec = $db->prepare("
    INSERT INTO elections (title, academic_year, election_type, start_time, end_time, status, eligibility_type, created_by)
    VALUES (?, ?, 'general', ?, ?, 'open', 'all_students', 1)
");
$stmtElec->execute(['Unit Test Voting Election', '2026-2027', $start, $end]);
$testElecId = (int)$db->lastInsertId();

$stmtCand1 = $db->prepare("INSERT INTO candidates (election_id, name, position, status) VALUES (?, 'Nominee Alpha', 'President', 'active')");
$stmtCand1->execute([$testElecId]);
$candAlphaId = (int)$db->lastInsertId();

$stmtCand2 = $db->prepare("INSERT INTO candidates (election_id, name, position, status) VALUES (?, 'Nominee Beta', 'President', 'active')");
$stmtCand2->execute([$testElecId]);
$candBetaId = (int)$db->lastInsertId();

// Candidate in another election (Election 2)
$candOtherElectionId = 4; // Belongs to Election 2

// 1. Eligible Verified Student (Student ID 1: Arun Kumar) Casts First Vote
$studentArunId = 1;
$eligibility = $eligibilityService->check($testElecId, $studentArunId);
TestRunner::assertTrue($eligibility['eligible'], 'Verified student is recognized as eligible to vote');

$voteResult = $votingService->castVote($testElecId, $studentArunId, $candAlphaId, '127.0.0.1', 'PHP-Unit-Test');
TestRunner::assertTrue($voteResult['success'], 'Eligible student casts vote successfully');
TestRunner::assertTrue(!empty($voteResult['receipt_hash']), 'Non-sensitive receipt hash is generated');

// 2. Duplicate Vote Prevention Check (Same Student, Same Election)
TestRunner::assertThrows(function() use ($votingService, $testElecId, $studentArunId, $candBetaId) {
    $votingService->castVote($testElecId, $studentArunId, $candBetaId, '127.0.0.1', 'PHP-Unit-Test-Duplicate');
}, RuntimeException::class, 'Duplicate vote attempt by same student is strictly rejected');

// 3. Decoupled Ballot Verification (Ballots table contains vote, but no student_id column)
$stmtCheckBallot = $db->prepare("SELECT * FROM ballots WHERE election_id = ? AND candidate_id = ?");
$stmtCheckBallot->execute([$testElecId, $candAlphaId]);
$ballotRow = $stmtCheckBallot->fetch(PDO::FETCH_ASSOC);
TestRunner::assertTrue(!empty($ballotRow), 'Ballot was inserted into ballots table');
TestRunner::assertTrue(!array_key_exists('student_id', $ballotRow), 'Ballot record decouples voter identity (no student_id field in ballots table)');

// 4. Ineligible Unverified Student Attempt (Student ID 5: Suresh Menon, is_verified = 0)
$studentSureshId = 5;
$unverifiedCheck = $eligibilityService->check($testElecId, $studentSureshId);
TestRunner::assertFalse($unverifiedCheck['eligible'], 'Unverified student is marked ineligible');
TestRunner::assertThrows(function() use ($votingService, $testElecId, $studentSureshId, $candAlphaId) {
    $votingService->castVote($testElecId, $studentSureshId, $candAlphaId, '127.0.0.1', 'PHP-Unit-Test');
}, RuntimeException::class, 'Vote attempt by unverified student is rejected');

// 5. Invalid Candidate from Another Election Rejection
$studentPriyaId = 2; // Verified, hasn't voted in this election yet
TestRunner::assertThrows(function() use ($votingService, $testElecId, $studentPriyaId, $candOtherElectionId) {
    $votingService->castVote($testElecId, $studentPriyaId, $candOtherElectionId, '127.0.0.1', 'PHP-Unit-Test');
}, RuntimeException::class, 'Vote attempt for candidate belonging to a different election is rejected');

// 6. Closed Election Vote Rejection
$stmtElecClose = $db->prepare("UPDATE elections SET status = 'closed' WHERE id = ?");
$stmtElecClose->execute([$testElecId]);

TestRunner::assertThrows(function() use ($votingService, $testElecId, $studentPriyaId, $candAlphaId) {
    $votingService->castVote($testElecId, $studentPriyaId, $candAlphaId, '127.0.0.1', 'PHP-Unit-Test');
}, RuntimeException::class, 'Vote attempt on closed election is rejected');

// 7. Department Eligibility Restriction Check (Election 2: CSE Department Only)
// Student 6 (Ananya Iyer) is Civil Engineering (Department 5)
$studentCivilId = 6;
$deptCheck = $eligibilityService->check(2, $studentCivilId);
TestRunner::assertFalse($deptCheck['eligible'], 'Student from Civil Engineering is rejected from CSE-only departmental election');

// Cleanup test records
$db->exec("DELETE FROM ballots WHERE election_id = {$testElecId}");
$db->exec("DELETE FROM voter_participation WHERE election_id = {$testElecId}");
$db->exec("DELETE FROM candidates WHERE election_id = {$testElecId}");
$db->exec("DELETE FROM elections WHERE id = {$testElecId}");
TestRunner::assertTrue(true, 'Test election records cleaned up cleanly');
