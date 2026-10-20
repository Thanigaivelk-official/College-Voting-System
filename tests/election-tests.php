<?php
/**
 * College Voting System - Election Management Test Suite
 */

declare(strict_types=1);

require_once __DIR__ . '/test-framework.php';
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';
require_once __DIR__ . '/../app/services/ElectionService.php';

TestRunner::suite('Election Management & Scheduling Suite');

$db = Database::getConnection();
$electionRepo = new ElectionRepository($db);
$electionService = new ElectionService($db);

// 1. Validate Invalid Timeline (End time before Start time)
TestRunner::assertThrows(function() use ($electionService) {
    $electionService->createElection([
        'title' => 'Invalid Timeline Election',
        'academic_year' => '2026-2027',
        'election_type' => 'general',
        'start_time' => '2026-10-15 10:00:00',
        'end_time' => '2026-10-15 09:00:00', // Earlier!
        'status' => 'draft',
        'eligibility_type' => 'all_students',
        'created_by' => 1
    ]);
}, InvalidArgumentException::class, 'Reject election where end_time is earlier than start_time');

// 2. Validate Successful Election Creation
$startValid = date('Y-m-d H:i:s', strtotime('+1 hour'));
$endValid = date('Y-m-d H:i:s', strtotime('+24 hours'));
$testElectionId = $electionService->createElection([
    'title' => 'Unit Test General Election',
    'description' => 'Test election for automated suite',
    'academic_year' => '2026-2027',
    'election_type' => 'general',
    'start_time' => $startValid,
    'end_time' => $endValid,
    'status' => 'scheduled',
    'eligibility_type' => 'all_students',
    'created_by' => 1
]);
TestRunner::assertTrue($testElectionId > 0, 'New election created successfully with generated ID');

$fetched = $electionRepo->findById($testElectionId);
TestRunner::assertEquals('Unit Test General Election', $fetched['title'], 'Fetched election title matches');
TestRunner::assertEquals('scheduled', $fetched['status'], 'Election initial status is scheduled');

// 3. Status Transition Test
$electionRepo->updateStatus($testElectionId, 'open');
$openFetched = $electionRepo->findById($testElectionId);
TestRunner::assertEquals('open', $openFetched['status'], 'Election status updated to open');

$electionRepo->updateStatus($testElectionId, 'closed');
$closedFetched = $electionRepo->findById($testElectionId);
TestRunner::assertEquals('closed', $closedFetched['status'], 'Election status updated to closed');

// Cleanup test election
$electionRepo->delete($testElectionId);
TestRunner::assertTrue($electionRepo->findById($testElectionId) === null, 'Test election deleted cleanly');

// 4. Automatic Schedule Synchronization Test
// Create an election scheduled in past that should auto-open
$pastStart = date('Y-m-d H:i:s', strtotime('-2 hours'));
$futureEnd = date('Y-m-d H:i:s', strtotime('+2 hours'));
$autoOpenId = $electionService->createElection([
    'title' => 'Auto Sync Test Election',
    'academic_year' => '2026-2027',
    'election_type' => 'general',
    'start_time' => $pastStart,
    'end_time' => $futureEnd,
    'status' => 'scheduled',
    'eligibility_type' => 'all_students',
    'created_by' => 1
]);

$electionService->syncStatuses();
$synced = $electionRepo->findById($autoOpenId);
TestRunner::assertEquals('open', $synced['status'], 'Election auto-synchronized from scheduled to open based on timestamp');

$electionRepo->delete($autoOpenId);
