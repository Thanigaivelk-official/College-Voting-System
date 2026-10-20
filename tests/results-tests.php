<?php
/**
 * College Voting System - Results Calculation & Publication Test Suite
 */

declare(strict_types=1);

require_once __DIR__ . '/test-framework.php';
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/services/ResultService.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';

TestRunner::suite('Results Calculation, Tie Detection & Publication Suite');

$db = Database::getConnection();
$resultService = new ResultService($db);
$electionRepo = new ElectionRepository($db);

// 1. Zero-Vote Election Handling (Does not trigger division by zero)
$nowStr = date('Y-m-d H:i:s');
$stmtZero = $db->prepare("
    INSERT INTO elections (title, academic_year, election_type, start_time, end_time, status, eligibility_type, created_by)
    VALUES ('Zero Vote Test Election', '2026-2027', 'general', ?, ?, 'closed', 'all_students', 1)
");
$stmtZero->execute([$nowStr, $nowStr]);
$zeroElecId = (int)$db->lastInsertId();

$stmtZeroCand = $db->prepare("INSERT INTO candidates (election_id, name, position, status) VALUES (?, 'Solo Nominee', 'President', 'active')");
$stmtZeroCand->execute([$zeroElecId]);

$zeroResults = $resultService->calculateResults($zeroElecId);
TestRunner::assertEquals(0, $zeroResults['total_votes_cast'], 'Zero-vote election returns 0 total votes cast');
TestRunner::assertEquals(0.0, (float)$zeroResults['turnout_percentage'], 'Turnout percentage is 0.0 without division by zero');
TestRunner::assertFalse($zeroResults['is_tie'], 'Zero-vote election is not a tie');
TestRunner::assertTrue($zeroResults['winner'] === null, 'No winner declared for zero-vote election');
TestRunner::assertEquals(0.0, (float)$zeroResults['candidates'][0]['percentage'], 'Candidate percentage is 0.0 without division by zero');

// 2. Clear Winner Calculation Test
// Add 2 votes to Solo Nominee
$stmtBallot1 = $db->prepare("INSERT INTO ballots (election_id, candidate_id, ballot_token, cast_at) VALUES (?, ?, 'tok_w1', ?)");
$stmtBallot1->execute([$zeroElecId, $zeroResults['candidates'][0]['candidate_id'], $nowStr]);
$stmtBallot2 = $db->prepare("INSERT INTO ballots (election_id, candidate_id, ballot_token, cast_at) VALUES (?, ?, 'tok_w2', ?)");
$stmtBallot2->execute([$zeroElecId, $zeroResults['candidates'][0]['candidate_id'], $nowStr]);

$winnerResults = $resultService->calculateResults($zeroElecId);
TestRunner::assertEquals(2, $winnerResults['total_votes_cast'], 'Total votes cast calculated correctly as 2');
TestRunner::assertFalse($winnerResults['is_tie'], 'No tie detected when one candidate has most votes');
TestRunner::assertEquals('Solo Nominee', $winnerResults['winner']['name'], 'Winner candidate identified correctly');
TestRunner::assertEquals(100.0, (float)$winnerResults['winner']['percentage'], 'Solo winner received 100% of votes');

// 3. Official Tie Detection Test
// Add second candidate and give 2 votes as well (Tie: 2 vs 2)
$stmtTieCand = $db->prepare("INSERT INTO candidates (election_id, name, position, status) VALUES (?, 'Tied Opponent', 'President', 'active')");
$stmtTieCand->execute([$zeroElecId]);
$tiedOpponentId = (int)$db->lastInsertId();

$stmtBallot3 = $db->prepare("INSERT INTO ballots (election_id, candidate_id, ballot_token, cast_at) VALUES (?, ?, 'tok_t1', ?)");
$stmtBallot3->execute([$zeroElecId, $tiedOpponentId, $nowStr]);
$stmtBallot4 = $db->prepare("INSERT INTO ballots (election_id, candidate_id, ballot_token, cast_at) VALUES (?, ?, 'tok_t2', ?)");
$stmtBallot4->execute([$zeroElecId, $tiedOpponentId, $nowStr]);

$tieResults = $resultService->calculateResults($zeroElecId);
TestRunner::assertEquals(4, $tieResults['total_votes_cast'], 'Total votes cast calculated as 4');
TestRunner::assertTrue($tieResults['is_tie'], 'Tie successfully detected when two candidates share max votes');
TestRunner::assertTrue($tieResults['winner'] === null, 'No single winner declared in a tie');
TestRunner::assertTrue(str_contains((string)$tieResults['tie_details'], 'Tie detected between'), 'Tie details explanation string generated');

// 4. Result Snapshot Publication
$publishRes = $resultService->publishResults($zeroElecId, 1);
TestRunner::assertTrue(!empty($publishRes), 'ResultService::publishResults returns published payload');

$publishedSnapshot = $resultService->getPublishedResults($zeroElecId);
TestRunner::assertTrue(!empty($publishedSnapshot), 'Published snapshot retrieved from election_results table');
TestRunner::assertEquals(4, (int)$publishedSnapshot['total_votes_cast'], 'Snapshot total votes matches');
TestRunner::assertEquals(1, (int)$publishedSnapshot['is_tie'], 'Snapshot correctly recorded tie state');

// Verify election status updated to results_published
$updatedElec = $electionRepo->findById($zeroElecId);
TestRunner::assertEquals('results_published', $updatedElec['status'], 'Election status transitioned to results_published');

// Cleanup
$db->exec("DELETE FROM election_results WHERE election_id = {$zeroElecId}");
$db->exec("DELETE FROM ballots WHERE election_id = {$zeroElecId}");
$db->exec("DELETE FROM candidates WHERE election_id = {$zeroElecId}");
$db->exec("DELETE FROM elections WHERE id = {$zeroElecId}");
TestRunner::assertTrue(true, 'Test results cleaned up');
