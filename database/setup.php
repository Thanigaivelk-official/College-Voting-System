<?php
/**
 * Database Setup Script — College Voting System
 * Run from project root: php database/setup.php
 */
declare(strict_types=1);

// Load .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k); $v = trim($v, " \t\"'");
            putenv("$k=$v"); $_ENV[$k] = $v;
        }
    }
}

$host   = getenv('DB_HOST') ?: '127.0.0.1';
$port   = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'college_voting';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') ?: '';

echo "\n=== College Voting System — Database Setup ===\n";
echo "Host   : $host:$port\n";
echo "DB     : $dbname\n";
echo "User   : $user\n\n";

// Step 1 — Connect WITHOUT selecting a database
try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "[OK] Connected to MySQL server\n";
} catch (PDOException $e) {
    echo "[FAIL] Cannot connect to MySQL: " . $e->getMessage() . "\n\n";
    echo "Troubleshooting:\n";
    echo "  1. Make sure MySQL / XAMPP / MariaDB is running\n";
    echo "  2. Check DB_HOST, DB_USER, DB_PASS in .env\n";
    echo "  3. Verify the PDO MySQL extension is enabled in php.ini\n\n";
    echo "  Run: php -m | findstr pdo_mysql\n";
    exit(1);
}

// Step 2 — Create database
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "[OK] Database '$dbname' created (or already exists)\n";
} catch (PDOException $e) {
    echo "[FAIL] Could not create database: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 3 — Select the database
$pdo->exec("USE `$dbname`");

// Step 4 — Import schema.sql
$schemaFile = __DIR__ . '/schema.sql';
if (!file_exists($schemaFile)) {
    echo "[SKIP] schema.sql not found — skipping schema import\n";
} else {
    echo "[..] Importing schema.sql ...\n";
    $sql = file_get_contents($schemaFile);
    // Remove USE statement (already selected)
    $sql = preg_replace('/^\s*USE\s+\S+\s*;/im', '', $sql);
    try {
        $pdo->exec($sql);
        echo "[OK] Schema imported successfully\n";
    } catch (PDOException $e) {
        echo "[WARN] Schema import warning: " . $e->getMessage() . "\n";
    }
}

// Step 5 — Import seed.sql
$seedFile = __DIR__ . '/seed.sql';
if (!file_exists($seedFile)) {
    echo "[SKIP] seed.sql not found — skipping seed data\n";
} else {
    echo "[..] Importing seed.sql ...\n";
    $sql = file_get_contents($seedFile);
    $sql = preg_replace('/^\s*USE\s+\S+\s*;/im', '', $sql);
    try {
        $pdo->exec($sql);
        echo "[OK] Seed data imported successfully\n";
    } catch (PDOException $e) {
        echo "[WARN] Seed import warning: " . $e->getMessage() . "\n";
    }
}

// Step 6 — Verify tables
$tables = $pdo->query("SHOW TABLES FROM `$dbname`")->fetchAll(PDO::FETCH_COLUMN);
echo "\n[OK] Tables in '$dbname' (" . count($tables) . " total):\n";
foreach ($tables as $t) echo "      - $t\n";

// Step 7 — Quick data check
try {
    $users    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $students = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $elections = (int)$pdo->query("SELECT COUNT(*) FROM elections")->fetchColumn();
    $candidates = (int)$pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
    echo "\n[OK] Data verification:\n";
    echo "      Users     : $users\n";
    echo "      Students  : $students\n";
    echo "      Elections : $elections\n";
    echo "      Candidates: $candidates\n";
} catch (PDOException $e) {
    echo "[WARN] Verification skipped: " . $e->getMessage() . "\n";
}

echo "\n==============================================\n";
echo "Setup complete!\n";
echo "Open your browser and navigate to:\n";
echo "  http://localhost/college-voting-system/public/\n";
echo "\nDemo Credentials:\n";
echo "  Admin   : admin@college.edu      / Admin@123\n";
echo "  Student : arun@student.college.edu / Student@123\n";
echo "==============================================\n\n";
