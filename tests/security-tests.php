<?php
/**
 * College Voting System - Security Controls & Threat Defense Test Suite
 */

declare(strict_types=1);

require_once __DIR__ . '/test-framework.php';
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/csrf.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/validation.php';

TestRunner::suite('Security Controls & Vulnerability Defense Suite');

$db = Database::getConnection();

// 1. Cross-Site Scripting (XSS) Output Escaping
$xssPayload = '<script>alert("XSS Attack!");</script>';
$escaped = e($xssPayload);
TestRunner::assertEquals('&lt;script&gt;alert(&quot;XSS Attack!&quot;);&lt;/script&gt;', $escaped, 'e() helper properly escapes HTML tags and quotes');
TestRunner::assertFalse(str_contains($escaped, '<script>'), 'Raw HTML tag is not present in escaped output');

// 2. Cross-Site Request Forgery (CSRF) Protection
$token = csrf_token();
TestRunner::assertTrue(strlen($token) === 64, 'CSRF token is 64 hex characters (32 bytes entropy)');
TestRunner::assertTrue(validate_csrf($token), 'Valid CSRF token validates successfully');
TestRunner::assertFalse(validate_csrf('invalid_tampered_token_string_1234567890'), 'Tampered CSRF token is rejected');
TestRunner::assertFalse(validate_csrf(null), 'Missing CSRF token is rejected');

// 3. Spreadsheet CSV Formula Injection Defense
TestRunner::assertEquals("'=1+1", sanitize_csv_field('=1+1'), 'Formula starting with = is prefixed with apostrophe');
TestRunner::assertEquals("'+cmd|' /C calc'!A0", sanitize_csv_field("+cmd|' /C calc'!A0"), 'Formula starting with + is prefixed with apostrophe');
TestRunner::assertEquals("'-1+2", sanitize_csv_field('-1+2'), 'Formula starting with - is prefixed with apostrophe');
TestRunner::assertEquals("'@SUM(A1:A5)", sanitize_csv_field('@SUM(A1:A5)'), 'Formula starting with @ is prefixed with apostrophe');
TestRunner::assertEquals("Normal Text", sanitize_csv_field('Normal Text'), 'Standard benign text remains unaltered');

// 4. SQL Injection Resistance via Prepared Statements
$sqlInjectionEmail = "admin@college.edu' OR '1'='1";
$sqlInjectionPass = "' OR '1'='1";
$authRes = Auth::login($sqlInjectionEmail, $sqlInjectionPass);
TestRunner::assertFalse($authRes['success'], 'SQL injection authentication payload is completely neutralized');

// 5. Password Hashing Security
$stmtPass = $db->query("SELECT password_hash FROM users WHERE id = 1");
$hash = $stmtPass->fetch(PDO::FETCH_ASSOC)['password_hash'] ?? '';
TestRunner::assertTrue(str_starts_with($hash, '$2y$'), 'Passwords are cryptographically secured using bcrypt algorithm ($2y$)');
TestRunner::assertFalse(str_contains($hash, 'Admin@123'), 'Plaintext password is never stored in database');

// 6. Safe File Upload Validation Defense
$fakeMaliciousFile = [
    'name' => 'exploit.php',
    'type' => 'application/x-php',
    'tmp_name' => __DIR__ . '/test-framework.php',
    'error' => UPLOAD_ERR_OK,
    'size' => 1024
];
$uploadErr = Validator::validateImageUpload($fakeMaliciousFile);
TestRunner::assertTrue(!empty($uploadErr), 'Malicious PHP script upload is blocked by image validator');
TestRunner::assertTrue(str_contains((string)$uploadErr, 'Invalid file type') || str_contains((string)$uploadErr, 'Invalid file extension'), 'Appropriate rejection message returned for unauthorized file type');
