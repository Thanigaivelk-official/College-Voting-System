<?php
/**
 * College Voting System - Authentication Test Suite
 */

declare(strict_types=1);

require_once __DIR__ . '/test-framework.php';
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/repositories/StudentRepository.php';

TestRunner::suite('Authentication & RBAC Suite');

$db = Database::getConnection();

// 1. Valid Admin Login
$resAdmin = Auth::login('admin@college.edu', 'Admin@123');
TestRunner::assertTrue($resAdmin['success'], 'Admin can log in with valid credentials');
TestRunner::assertEquals('admin', $resAdmin['role'], 'Admin role is correctly returned');
TestRunner::assertTrue(Auth::isAdmin(), 'Auth::isAdmin() returns true for logged-in administrator');
TestRunner::assertFalse(Auth::isStudent(), 'Auth::isStudent() returns false for administrator');
Auth::logout();
TestRunner::assertFalse(Auth::check(), 'Auth::check() returns false after logout');

// 2. Valid Student Login
$resStudent = Auth::login('arun@student.college.edu', 'Student@123');
TestRunner::assertTrue($resStudent['success'], 'Verified student can log in with valid credentials');
TestRunner::assertEquals('student', $resStudent['role'], 'Student role is correctly returned');
TestRunner::assertTrue(Auth::isStudent(), 'Auth::isStudent() returns true for logged-in student');
TestRunner::assertFalse(Auth::isAdmin(), 'Auth::isAdmin() returns false for student');

$user = Auth::user();
TestRunner::assertTrue(isset($user['student']), 'Student profile is automatically populated in Auth::user()');
TestRunner::assertEquals('REG2024001', $user['student']['registration_no'], 'Student registration number matches');
Auth::logout();

// 3. Invalid Password Rejection
$resBadPass = Auth::login('admin@college.edu', 'WrongPassword!456');
TestRunner::assertFalse($resBadPass['success'], 'Login fails with wrong password');
TestRunner::assertEquals('Invalid email address or password.', $resBadPass['error'], 'Generic error message returned without account enumeration');

// 4. Non-Existent User Rejection
$resNonExistent = Auth::login('nonexistent@student.college.edu', 'SomePassword123');
TestRunner::assertFalse($resNonExistent['success'], 'Login fails for non-existent user');
TestRunner::assertEquals('Invalid email address or password.', $resNonExistent['error'], 'Same generic error message returned for non-existent user');

// 5. Unverified Student State
// Ensure we test a student who is strictly unverified
$db->exec("UPDATE students SET is_verified = 0 WHERE id = 5");
$resUnverified = Auth::login('suresh@student.college.edu', 'Student@123');
TestRunner::assertTrue($resUnverified['success'], 'Unverified student can authenticate');
$unverifiedUser = Auth::user();
TestRunner::assertEquals(0, (int)$unverifiedUser['student']['is_verified'], 'Unverified student has is_verified = 0');
Auth::logout();

// 6. Rate Limiting Check
$dummyEmail = 'ratelimit_test@college.edu';
$dummyIp = '192.168.1.100';
Auth::clearLoginAttempts($db, $dummyEmail, $dummyIp);
TestRunner::assertFalse(Auth::isRateLimited($db, $dummyEmail, $dummyIp), 'Account is initially not rate limited');

for ($i = 0; $i < 5; $i++) {
    Auth::recordLoginAttempt($db, $dummyEmail, $dummyIp, false);
}
TestRunner::assertTrue(Auth::isRateLimited($db, $dummyEmail, $dummyIp), 'Account is rate limited after 5 failed attempts');
Auth::clearLoginAttempts($db, $dummyEmail, $dummyIp);
TestRunner::assertFalse(Auth::isRateLimited($db, $dummyEmail, $dummyIp), 'Rate limit is cleared after reset');
