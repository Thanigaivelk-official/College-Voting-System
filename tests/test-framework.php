<?php
/**
 * College Voting System - Lightweight Test Framework
 */

declare(strict_types=1);

class TestRunner {
    private static int $passed = 0;
    private static int $failed = 0;
    private static array $failures = [];
    private static string $currentSuite = '';

    public static function suite(string $name): void {
        self::$currentSuite = $name;
        echo "\n\033[1;34m=== Running Suite: {$name} ===\033[0m\n";
    }

    public static function assertTrue(bool $condition, string $message): void {
        if ($condition) {
            self::$passed++;
            echo "  \033[32m✔ PASS\033[0m: {$message}\n";
        } else {
            self::$failed++;
            self::$failures[] = "[Suite: " . self::$currentSuite . "] FAIL: {$message}";
            echo "  \033[31m✘ FAIL\033[0m: {$message}\n";
        }
    }

    public static function assertFalse(bool $condition, string $message): void {
        self::assertTrue(!$condition, $message);
    }

    public static function assertEquals(mixed $expected, mixed $actual, string $message): void {
        $matches = ($expected === $actual);
        if ($matches) {
            self::$passed++;
            echo "  \033[32m✔ PASS\033[0m: {$message}\n";
        } else {
            self::$failed++;
            $detail = "Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true);
            self::$failures[] = "[Suite: " . self::$currentSuite . "] FAIL: {$message} ({$detail})";
            echo "  \033[31m✘ FAIL\033[0m: {$message} ({$detail})\n";
        }
    }

    public static function assertThrows(callable $fn, string $expectedExceptionClass, string $message): void {
        try {
            $fn();
            self::$failed++;
            self::$failures[] = "[Suite: " . self::$currentSuite . "] FAIL: Expected exception {$expectedExceptionClass} was not thrown for: {$message}";
            echo "  \033[31m✘ FAIL\033[0m: Expected exception {$expectedExceptionClass} was not thrown for: {$message}\n";
        } catch (Throwable $e) {
            if ($e instanceof $expectedExceptionClass) {
                self::$passed++;
                echo "  \033[32m✔ PASS\033[0m: {$message} (threw {$expectedExceptionClass})\n";
            } else {
                self::$failed++;
                self::$failures[] = "[Suite: " . self::$currentSuite . "] FAIL: Caught different exception " . get_class($e) . " instead of {$expectedExceptionClass} for: {$message}";
                echo "  \033[31m✘ FAIL\033[0m: Caught " . get_class($e) . " instead of {$expectedExceptionClass}\n";
            }
        }
    }

    public static function summary(): int {
        echo "\n====================================================\n";
        echo " Test Execution Summary\n";
        echo "====================================================\n";
        echo " Total Passed: \033[32m" . self::$passed . "\033[0m\n";
        echo " Total Failed: \033[31m" . self::$failed . "\033[0m\n";

        if (self::$failed > 0) {
            echo "\nFailed Assertions:\n";
            foreach (self::$failures as $f) {
                echo " - \033[31m{$f}\033[0m\n";
            }
            echo "\nStatus: FAILED\n";
            return 1;
        }

        echo "\n\033[32mStatus: ALL TESTS PASSED!\033[0m\n";
        return 0;
    }
}
