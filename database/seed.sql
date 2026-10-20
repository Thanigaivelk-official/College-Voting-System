-- =====================================================================
-- College Voting System - Seed Data (Fictional Demo Data)
-- =====================================================================

USE college_voting;

-- 1. Departments
INSERT INTO departments (id, code, name) VALUES
(1, 'CSE', 'Computer Science & Engineering'),
(2, 'ECE', 'Electronics & Communication Engineering'),
(3, 'MECH', 'Mechanical Engineering'),
(4, 'IT', 'Information Technology'),
(5, 'CIVIL', 'Civil Engineering')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- 2. Users (Admin password: Admin@123 | Student password: Student@123)
-- Hash: $2y$10$bA7ViZLa9HmBv6CKPvMoDOYyOws1ogzucPH/N/4ibwQs9LQOir1mC (Admin@123)
-- Hash: $2y$10$kARMEiLaoVKWj38biRDBOuAQ1NC9VpQ8H0.Sk8JHlzI9YE1sZTj5K (Student@123)
INSERT INTO users (id, email, password_hash, role, status, created_at) VALUES
(1, 'admin@college.edu', '$2y$10$bA7ViZLa9HmBv6CKPvMoDOYyOws1ogzucPH/N/4ibwQs9LQOir1mC', 'admin', 'active', NOW()),
(2, 'arun@student.college.edu', '$2y$10$kARMEiLaoVKWj38biRDBOuAQ1NC9VpQ8H0.Sk8JHlzI9YE1sZTj5K', 'student', 'active', NOW()),
(3, 'priya@student.college.edu', '$2y$10$kARMEiLaoVKWj38biRDBOuAQ1NC9VpQ8H0.Sk8JHlzI9YE1sZTj5K', 'student', 'active', NOW()),
(4, 'karthik@student.college.edu', '$2y$10$kARMEiLaoVKWj38biRDBOuAQ1NC9VpQ8H0.Sk8JHlzI9YE1sZTj5K', 'student', 'active', NOW()),
(5, 'divya@student.college.edu', '$2y$10$kARMEiLaoVKWj38biRDBOuAQ1NC9VpQ8H0.Sk8JHlzI9YE1sZTj5K', 'student', 'active', NOW()),
(6, 'suresh@student.college.edu', '$2y$10$kARMEiLaoVKWj38biRDBOuAQ1NC9VpQ8H0.Sk8JHlzI9YE1sZTj5K', 'student', 'pending', NOW()),
(7, 'ananya@student.college.edu', '$2y$10$kARMEiLaoVKWj38biRDBOuAQ1NC9VpQ8H0.Sk8JHlzI9YE1sZTj5K', 'student', 'active', NOW())
ON DUPLICATE KEY UPDATE email=VALUES(email);

-- 3. Administrator Profile
INSERT INTO administrators (id, user_id, full_name, staff_id, department, phone) VALUES
(1, 1, 'Dr. A. Sharma', 'STF-2024-001', 'Election Commission & Academic Dean', '+91 98765 43210')
ON DUPLICATE KEY UPDATE full_name=VALUES(full_name);

-- 4. Student Profiles
INSERT INTO students (id, user_id, full_name, registration_no, department_id, course, academic_year, is_verified, verified_at, verified_by) VALUES
(1, 2, 'Arun Kumar', 'REG2024001', 1, 'B.E. Computer Science', 3, 1, NOW(), 1),
(2, 3, 'Priya Sundaram', 'REG2024002', 2, 'B.E. Electronics', 3, 1, NOW(), 1),
(3, 4, 'Karthik Raja', 'REG2024003', 1, 'B.E. Computer Science', 2, 1, NOW(), 1),
(4, 5, 'Divya Ramesh', 'REG2024004', 4, 'B.Tech Information Tech', 4, 1, NOW(), 1),
(5, 6, 'Suresh Menon', 'REG2024005', 3, 'B.E. Mechanical', 2, 0, NULL, NULL),
(6, 7, 'Ananya Iyer', 'REG2024006', 5, 'B.E. Civil Engineering', 1, 1, NOW(), 1)
ON DUPLICATE KEY UPDATE registration_no=VALUES(registration_no);

-- 5. Whitelist Registration Numbers
INSERT INTO whitelist_registration_numbers (id, registration_no, full_name, department_code, academic_year, is_registered) VALUES
(1, 'REG2024001', 'Arun Kumar', 'CSE', 3, 1),
(2, 'REG2024002', 'Priya Sundaram', 'ECE', 3, 1),
(3, 'REG2024003', 'Karthik Raja', 'CSE', 2, 1),
(4, 'REG2024004', 'Divya Ramesh', 'IT', 4, 1),
(5, 'REG2024005', 'Suresh Menon', 'MECH', 2, 1),
(6, 'REG2024006', 'Ananya Iyer', 'CIVIL', 1, 1),
(7, 'REG2024007', 'Rahul Verma', 'CSE', 3, 0),
(8, 'REG2024008', 'Sneha Kapoor', 'ECE', 2, 0)
ON DUPLICATE KEY UPDATE full_name=VALUES(full_name);

-- 6. Elections
-- Election 1: Active General Election
-- Election 2: Active CSE Department Election
-- Election 3: Completed & Published Club Election
-- Election 4: Upcoming Scheduled Election
INSERT INTO elections (id, title, description, academic_year, election_type, start_time, end_time, status, eligibility_type, created_by, created_at) VALUES
(1, 'Student Council General Election 2026-2027', 'Annual campus-wide election for Student Council President and executive positions.', '2026-2027', 'general', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 3 DAY), 'open', 'all_students', 1, NOW()),
(2, 'CSE Department Representative Election 2026', 'Election for Department Student Representative for Computer Science & Engineering.', '2026-2027', 'departmental', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 2 DAY), 'open', 'by_department', 1, NOW()),
(3, 'Sports Committee President Election 2026', 'Election to choose the Sports Council President for the upcoming academic year.', '2026-2027', 'club', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 'results_published', 'all_students', 1, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(4, 'Cultural Secretary Election 2026', 'Upcoming annual election for Cultural Affairs Coordinator.', '2026-2027', 'general', DATE_ADD(NOW(), INTERVAL 2 DAY), DATE_ADD(NOW(), INTERVAL 5 DAY), 'scheduled', 'all_students', 1, NOW())
ON DUPLICATE KEY UPDATE title=VALUES(title);

-- 7. Election Eligibility Rules
INSERT INTO election_eligibility (election_id, department_id, academic_year) VALUES
(2, 1, NULL) -- CSE Department only for Election 2
ON DUPLICATE KEY UPDATE election_id=VALUES(election_id);

-- 8. Candidates
INSERT INTO candidates (id, election_id, student_id, name, position, department_id, manifesto, photo_url, status) VALUES
(1, 1, 1, 'Arun Kumar', 'Council President', 1, 'Committed to 24/7 digital library resources, transparent council budgeting, and modern innovation hackathons for all departments.', 'arun_kumar.png', 'active'),
(2, 1, 2, 'Priya Sundaram', 'Council President', 2, 'Dedicated to student wellness programs, modernized engineering labs, and robust industry campus placement partnerships.', 'priya_sundaram.png', 'active'),
(3, 1, 4, 'Divya Ramesh', 'Council President', 4, 'Advocating for campus-wide high-speed Wi-Fi, grievance redressing portal, and peer-to-peer technical mentorship.', 'divya_ramesh.png', 'active'),
(4, 2, 3, 'Karthik Raja', 'CSE Department Representative', 1, 'Promoting departmental coding leagues, open-source workshops, and better computer lab equipment maintenance.', NULL, 'active'),
(5, 2, NULL, 'Vikram Nathan', 'CSE Department Representative', 1, 'Focused on research symposiums, student study circles, and peer tutoring networks.', NULL, 'active'),
(6, 3, NULL, 'Manoj Kumar', 'Sports Secretary', 3, 'Upgrading outdoor grounds, modernizing the college gym, and hosting state-level athletics fests.', NULL, 'active'),
(7, 3, NULL, 'Sneha Reddy', 'Sports Secretary', 5, 'Expanding women in sports initiatives, organizing intra-department tournaments, and certified coaching sessions.', NULL, 'active')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- 9. Sample Voting Records for Completed Election (Election 3)
-- Students 1, 2, 3, 4 voted in election 3
INSERT INTO voter_participation (election_id, student_id, voted_at, ip_address, user_agent, receipt_hash) VALUES
(3, 1, DATE_SUB(NOW(), INTERVAL 3 DAY), '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', SHA2(CONCAT(3, 1, 'seed_salt_1'), 256)),
(3, 2, DATE_SUB(NOW(), INTERVAL 3 DAY), '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', SHA2(CONCAT(3, 2, 'seed_salt_2'), 256)),
(3, 3, DATE_SUB(NOW(), INTERVAL 2 DAY), '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', SHA2(CONCAT(3, 3, 'seed_salt_3'), 256)),
(3, 4, DATE_SUB(NOW(), INTERVAL 2 DAY), '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', SHA2(CONCAT(3, 4, 'seed_salt_4'), 256))
ON DUPLICATE KEY UPDATE receipt_hash=VALUES(receipt_hash);

-- 10. Decoupled Ballots for Election 3 (Candidate 6 got 3 votes, Candidate 7 got 1 vote)
INSERT INTO ballots (election_id, candidate_id, ballot_token, cast_at) VALUES
(3, 6, 'token_e3_b1_98a7bc6d5e4f3a21', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 6, 'token_e3_b2_87b6ca5d4e3f2a10', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 6, 'token_e3_b3_76c5ba4d3e2f1a09', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 7, 'token_e3_b4_65d4cb3e2f1a0987', DATE_SUB(NOW(), INTERVAL 2 DAY))
ON DUPLICATE KEY UPDATE ballot_token=VALUES(ballot_token);

-- 11. Published Result Snapshot for Election 3
INSERT INTO election_results (
    election_id, total_eligible_voters, total_votes_cast, turnout_percentage, 
    winner_candidate_id, winner_name, is_tie, tie_details, candidate_breakdown, 
    published_at, published_by
) VALUES (
    3, 5, 4, 80.00, 
    6, 'Manoj Kumar', 0, NULL, 
    '[{"candidate_id":6,"name":"Manoj Kumar","position":"Sports Secretary","votes":3,"percentage":75.0},{"candidate_id":7,"name":"Sneha Reddy","position":"Sports Secretary","votes":1,"percentage":25.0}]', 
    DATE_SUB(NOW(), INTERVAL 1 DAY), 1
)
ON DUPLICATE KEY UPDATE winner_name=VALUES(winner_name);

-- 12. Notifications
INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES
(1, 'System Initialized', 'College Voting System database and admin commission profiles have been configured.', 'info', 1, NOW()),
(2, 'Voter Account Verified', 'Your student voter account has been verified by the Election Commission. You are eligible to cast votes in open elections.', 'success', 0, NOW()),
(3, 'Voter Account Verified', 'Your student voter account has been verified by the Election Commission. You are eligible to cast votes in open elections.', 'success', 0, NOW()),
(2, 'Election Open', 'The Student Council General Election 2026-2027 is now open for voting.', 'info', 0, NOW()),
(1, 'Results Published', 'Official election results for Sports Committee President Election 2026 have been published.', 'success', 1, DATE_SUB(NOW(), INTERVAL 1 DAY));

-- 13. Audit Logs
INSERT INTO audit_logs (user_id, actor_email, action, object_type, object_id, summary, ip_address, created_at) VALUES
(1, 'admin@college.edu', 'CREATE_ELECTION', 'election', 1, 'Created election "Student Council General Election 2026-2027"', '127.0.0.1', NOW()),
(1, 'admin@college.edu', 'CREATE_ELECTION', 'election', 2, 'Created election "CSE Department Representative Election 2026"', '127.0.0.1', NOW()),
(1, 'admin@college.edu', 'APPROVE_CANDIDATE', 'candidate', 1, 'Approved candidate nomination for Arun Kumar in election 1', '127.0.0.1', NOW()),
(1, 'admin@college.edu', 'APPROVE_CANDIDATE', 'candidate', 2, 'Approved candidate nomination for Priya Sundaram in election 1', '127.0.0.1', NOW()),
(1, 'admin@college.edu', 'PUBLISH_RESULTS', 'election', 3, 'Published official verified results for election 3 (Sports Committee)', '127.0.0.1', DATE_SUB(NOW(), INTERVAL 1 DAY));
