-- =====================================================================
-- College Voting System - Database Schema (MySQL 8.x)
-- Character Set: utf8mb4, Collation: utf8mb4_unicode_ci, Engine: InnoDB
-- =====================================================================

CREATE DATABASE IF NOT EXISTS college_voting
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE college_voting;

-- 1. Departments Table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Base Users Table (Authentication & RBAC)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student') NOT NULL DEFAULT 'student',
    status ENUM('pending', 'active', 'suspended') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_role (role),
    INDEX idx_user_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Administrator Profiles
CREATE TABLE IF NOT EXISTS administrators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    staff_id VARCHAR(50) NOT NULL UNIQUE,
    department VARCHAR(100) NOT NULL DEFAULT 'Election Commission',
    phone VARCHAR(20) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Student Profiles
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    registration_no VARCHAR(50) NOT NULL UNIQUE,
    department_id INT NOT NULL,
    course VARCHAR(50) NOT NULL,
    academic_year INT NOT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_at DATETIME DEFAULT NULL,
    verified_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_student_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
    CONSTRAINT fk_student_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student_verified (is_verified),
    INDEX idx_student_dept (department_id),
    INDEX idx_student_year (academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Whitelist Registration Numbers (For Admin Import & Pre-Verification)
CREATE TABLE IF NOT EXISTS whitelist_registration_numbers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_no VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    department_code VARCHAR(20) NOT NULL,
    academic_year INT NOT NULL,
    is_registered TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Elections Table
CREATE TABLE IF NOT EXISTS elections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    academic_year VARCHAR(20) NOT NULL,
    election_type ENUM('general', 'departmental', 'club', 'special') NOT NULL DEFAULT 'general',
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('draft', 'scheduled', 'open', 'closed', 'results_published', 'cancelled') NOT NULL DEFAULT 'draft',
    eligibility_type ENUM('all_students', 'by_department', 'by_year') NOT NULL DEFAULT 'all_students',
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_election_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_election_status (status),
    INDEX idx_election_dates (start_time, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Election Department/Year Eligibility Rules
CREATE TABLE IF NOT EXISTS election_eligibility (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    department_id INT DEFAULT NULL,
    academic_year INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_eligibility_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    CONSTRAINT fk_eligibility_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    INDEX idx_eligibility_election (election_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Candidates Table
CREATE TABLE IF NOT EXISTS candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    student_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    position VARCHAR(100) NOT NULL,
    department_id INT DEFAULT NULL,
    manifesto TEXT DEFAULT NULL,
    photo_url VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'withdrawn', 'disqualified') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_candidate_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    CONSTRAINT fk_candidate_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    CONSTRAINT fk_candidate_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_candidate_election (election_id),
    INDEX idx_candidate_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Voter Participation Table (Enforces 1 vote per student per election)
CREATE TABLE IF NOT EXISTS voter_participation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    student_id INT NOT NULL,
    voted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    receipt_hash VARCHAR(64) NOT NULL,
    CONSTRAINT fk_part_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    CONSTRAINT fk_part_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY uq_election_student (election_id, student_id),
    INDEX idx_part_election (election_id),
    INDEX idx_part_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Ballots Table (Decoupled Secret Ballot Choices)
CREATE TABLE IF NOT EXISTS ballots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    candidate_id INT NOT NULL,
    ballot_token VARCHAR(64) NOT NULL UNIQUE,
    cast_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ballot_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    CONSTRAINT fk_ballot_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    INDEX idx_ballot_election_candidate (election_id, candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Frozen / Published Election Results
CREATE TABLE IF NOT EXISTS election_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL UNIQUE,
    total_eligible_voters INT NOT NULL DEFAULT 0,
    total_votes_cast INT NOT NULL DEFAULT 0,
    turnout_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    winner_candidate_id INT DEFAULT NULL,
    winner_name VARCHAR(100) DEFAULT NULL,
    is_tie TINYINT(1) NOT NULL DEFAULT 0,
    tie_details TEXT DEFAULT NULL,
    candidate_breakdown JSON DEFAULT NULL,
    published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_by INT DEFAULT NULL,
    CONSTRAINT fk_result_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    CONSTRAINT fk_result_candidate FOREIGN KEY (winner_candidate_id) REFERENCES candidates(id) ON DELETE SET NULL,
    CONSTRAINT fk_result_publisher FOREIGN KEY (published_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. In-App Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') NOT NULL DEFAULT 'info',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notify_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notify_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Administrative Audit Logs
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    actor_email VARCHAR(150) DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    object_type VARCHAR(50) NOT NULL,
    object_id INT DEFAULT NULL,
    summary TEXT NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_object (object_type, object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Rate Limiting Table for Login Attempts
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    email VARCHAR(150) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_successful TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_attempt_window (ip_address, email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
