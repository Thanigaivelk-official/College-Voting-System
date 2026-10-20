# Test Plan — College Voting System

## Test Environment Setup

1. Use the development/test database (`college_voting`) populated with `database/seed.sql`
2. **Never run destructive tests against a production database**
3. Demo accounts: see `README.md` → Demo Credentials

---

## Running Automated Tests

```powershell
php tests/run-tests.php
```

Each test module can be run independently:

```powershell
php tests/authentication-tests.php
php tests/voting-tests.php
php tests/election-tests.php
php tests/results-tests.php
php tests/security-tests.php
```

---

## Module 1 — Authentication Tests

| # | Test Case | Expected Result | Status |
|---|-----------|----------------|--------|
| A01 | Valid admin login | Redirect to `/admin/dashboard.php` | ✅ |
| A02 | Valid student login | Redirect to `/public/dashboard.php` | ✅ |
| A03 | Invalid password | Error message, no session created | ✅ |
| A04 | Non-existent email | Generic error (no account existence leak) | ✅ |
| A05 | 5th failed attempt | Rate limit error for 15 minutes | ✅ |
| A06 | Logout | Session destroyed, redirect to login | ✅ |
| A07 | Access admin page as student | HTTP 403 Forbidden | ✅ |
| A08 | Access admin page as guest | Redirect to login | ✅ |
| A09 | Session timeout after inactivity | Session destroyed, redirect to login | ✅ |
| A10 | Unverified student access to voting | Blocked with verification message | ✅ |
| A11 | Suspended account login | "Account suspended" error | ✅ |
| A12 | Session fixation (session_regenerate_id) | New session ID after login | ✅ |

---

## Module 2 — Election Management Tests

| # | Test Case | Expected Result |
|---|-----------|----------------|
| E01 | Create election with valid data | Election created with `draft` status |
| E02 | Create election with end before start | Validation error |
| E03 | Auto-transition scheduled → open at start_time | Status changes via `ElectionService::syncStatuses()` |
| E04 | Auto-transition open → closed at end_time | Status changes via `syncStatuses()` |
| E05 | Vote before election starts | Eligibility check blocks submission |
| E06 | Vote after election closes | Eligibility check blocks submission |
| E07 | Open election manually | Status set to `open`, audit logged |
| E08 | Cancel election with votes | Allowed (status → cancelled), ballots preserved |
| E09 | Delete election with votes | Blocked with error message |
| E10 | Publish results before closing | Not permitted |

---

## Module 3 — Voting Integrity Tests

| # | Test Case | Expected Result |
|---|-----------|----------------|
| V01 | Eligible verified student casts one vote | Vote recorded, confirmation shown |
| V02 | Ineligible department student (by_department election) | Blocked with eligibility error |
| V03 | Unverified student submits vote | Blocked: account not verified |
| V04 | Second vote attempt (same election) | "Already voted" error |
| V05 | Concurrent duplicate submissions | Only one recorded; second gets error |
| V06 | Invalid candidate_id in POST | Validation error, no ballot recorded |
| V07 | Candidate from different election in POST | Candidate validation fails |
| V08 | Missing CSRF token in vote form | HTTP 403 |
| V09 | Tampered CSRF token | HTTP 403 |
| V10 | Vote on closed election (direct POST) | Eligibility check blocks |
| V11 | Database rollback on partial failure | No orphaned voter_participation record |
| V12 | GET request to cast-vote.php | Redirect to elections, no vote cast |

### Manual Concurrency Test (V05)

1. Log in as an eligible student in **two browser windows** simultaneously
2. Navigate both to the same election details page
3. Submit the vote form in both windows within milliseconds of each other
4. **Expected:** Only one `voter_participation` record is inserted. The second window shows an error message.

---

## Module 4 — Results Tests

| # | Test Case | Expected Result |
|---|-----------|----------------|
| R01 | Count votes for completed election (Election 3) | Candidate 6: 3 votes, Candidate 7: 1 vote |
| R02 | Percentage calculation | 75.0% and 25.0% |
| R03 | Turnout: 4 of 5 eligible voted | 80.00% |
| R04 | Tie detection | Both candidates with equal max votes listed as tied |
| R05 | Zero-vote election | No division-by-zero error; 0% turnout |
| R06 | Publish results before election closes | Not permitted |
| R07 | View results before publication (student) | "Results not yet published" message |
| R08 | Published results consistent after republish | Same snapshot values |
| R09 | CSV export correctness | Matches database counts |
| R10 | Formula injection in CSV | Fields starting with `=` are prefixed with `'` |

---

## Module 5 — Security Tests

| # | Test Case | Expected Result |
|---|-----------|----------------|
| S01 | SQL injection in login email field | Login fails, no database error |
| S02 | XSS payload in student name | Escaped in all HTML output |
| S03 | XSS payload in candidate manifesto | Escaped when rendered |
| S04 | CSRF token missing on vote form | HTTP 403 |
| S05 | Direct access to `/admin/dashboard.php` as student | HTTP 403 |
| S06 | Direct access to `/admin/results.php` as guest | Redirect to login |
| S07 | File upload: PHP script as candidate photo | Blocked (MIME validation fails) |
| S08 | File upload: oversized image | Blocked with size error |
| S09 | Access another student's vote history | Not possible (own student_id from session only) |
| S10 | Access unpublished results as student | Blocked |
| S11 | Tamper election_id or candidate_id in POST | Server-side validation rejects mismatches |

### Manual Security Tests

**S01 — SQL Injection in Login:**
- Email field: `' OR '1'='1`
- Expected: "Invalid email address or password." error — no login

**S02 — XSS in Registration:**
- Full name: `<script>alert('XSS')</script>`
- Expected: The name renders as literal text `<script>alert('XSS')</script>` in the browser — no popup executes

**S07 — PHP Upload:**
- Upload a file named `shell.php` as a candidate photo
- Expected: Validation rejects non-image MIME types

---

## Module 6 — User Interface Tests

| # | Test Case | Expected |
|---|-----------|----------|
| U01 | Responsive layout on mobile (375px) | No horizontal overflow |
| U02 | Flash messages appear and auto-dismiss | ✅ |
| U03 | Form double-submit prevention | Second click disabled after first |
| U04 | Vote confirmation modal displays correctly | Candidate name and position shown |
| U05 | Empty state on elections page | "No elections available" card shown |
| U06 | Admin sidebar active link highlights | Correct link highlighted per page |
| U07 | Notification badge count updates | Badge shows unread count |

---

## Test Data Reset

To reset all demo data to initial state:

```powershell
# Drop and recreate the database
C:\xampp\mysql\bin\mysql.exe -u root -p -e "DROP DATABASE IF EXISTS college_voting; CREATE DATABASE college_voting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Re-import schema and seed
C:\xampp\mysql\bin\mysql.exe -u root -p college_voting < database/schema.sql
C:\xampp\mysql\bin\mysql.exe -u root -p college_voting < database/seed.sql
```

---

## Demonstration Checklist

Use this checklist before a live project demonstration:

- [ ] Database seeded with fresh demo data
- [ ] XAMPP Apache and MySQL running
- [ ] Application opens at `http://localhost/college-voting-system/public/`
- [ ] Admin login works (`admin@college.edu` / `Admin@123`)
- [ ] Admin dashboard shows correct statistics
- [ ] Student login works (`arun@student.college.edu` / `Student@123`)
- [ ] Student can see open elections
- [ ] Student can cast a vote on Election 1
- [ ] Vote confirmation receipt displays
- [ ] Duplicate vote attempt shows correct error
- [ ] Admin can view live election monitoring for Election 1
- [ ] Admin can publish results for Election 3 (already published in seed)
- [ ] Published results show chart and turnout
- [ ] CSV export downloads correctly
- [ ] Audit log shows recent actions
- [ ] Unverified student (`suresh@student.college.edu`) cannot vote
