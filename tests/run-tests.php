<?php
/**
 * College Voting System - Master Test Runner
 * Usage: php tests/run-tests.php
 */

declare(strict_types=1);

echo "\n";
echo "====================================================\n";
echo " College Voting System - Automated Test Suite\n";
echo "====================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "OS: " . PHP_OS_FAMILY . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";

require_once __DIR__ . '/test-framework.php';

// Run suites in logical sequence
require_once __DIR__ . '/authentication-tests.php';
require_once __DIR__ . '/election-tests.php';
require_once __DIR__ . '/voting-tests.php';
require_once __DIR__ . '/results-tests.php';
require_once __DIR__ . '/security-tests.php';

$exitCode = TestRunner::summary();
exit($exitCode);
