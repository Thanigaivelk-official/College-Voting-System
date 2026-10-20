# Security Notes — College Voting System

## Purpose

This document describes the security controls implemented in the College Voting System, their intended protection scope, and known limitations. This project is designed for academic demonstration. It is **not** suitable for official elections without an independent security audit.

---

## Implemented Security Controls

### 1. SQL Injection Prevention
- **Control:** All database queries use PDO prepared statements with parameterized values.
- **Coverage:** Every `SELECT`, `INSERT`, `UPDATE`, `DELETE` operation.
- **Risk if removed:** Attackers could read, modify, or delete any database record.

### 2. Cross-Site Scripting (XSS)
- **Control:** All dynamic output is passed through `htmlspecialchars()` via the `e()` helper function before being inserted into HTML.
- **Coverage:** All user-supplied values rendered in HTML (names, emails, manifestos, etc.).
- **Risk if removed:** Attackers could inject malicious scripts into pages viewed by other users.

### 3. Cross-Site Request Forgery (CSRF)
- **Control:** A `bin2hex(random_bytes(32))` token is generated per session and stored in `$_SESSION['csrf_token']`. All state-changing forms embed it as a hidden field. The server validates it using `hash_equals()` (timing-safe comparison) before processing any POST request.
- **Coverage:** Login, registration, vote casting, all admin forms.
- **Risk if removed:** Attackers could forge requests on behalf of logged-in users.

### 4. Duplicate Vote Prevention
- **Database Layer:** `UNIQUE KEY uq_election_student (election_id, student_id)` on `voter_participation` table. The database rejects any second insert for the same student and election.
- **Application Layer:** The transaction checks for an existing vote record before inserting.
- **Race Condition:** Two simultaneous submissions get past the application check but only one insert succeeds at the database level. The second receives error code `23000` which is caught and returned as a user-friendly message.

### 5. Password Security
- **Control:** `password_hash($password, PASSWORD_DEFAULT)` (bcrypt) for storage; `password_verify()` for authentication.
- **Plaintext:** Never stored, logged, or transmitted.

### 6. Session Security
```php
ini_set('session.use_strict_mode', '1');    // Reject unknown session IDs
ini_set('session.use_only_cookies', '1');   // No URL-based session IDs
// Cookie: httponly=true, samesite=Lax
session_regenerate_id(true);                // After successful login (session fixation)
```
- **Session timeout:** Configurable inactivity timeout destroys idle sessions.

### 7. Login Rate Limiting
- **Control:** Failed login attempts are recorded in `login_attempts` table with IP and email.
- **Lockout:** After 5 failures within 15 minutes (configurable in `.env`), the IP/email combination is blocked.
- **Timing safety:** The check occurs before password verification to prevent timing attacks from revealing account existence.

### 8. Role-Based Access Control
- **Admin pages:** `app/middleware/require-admin.php` enforces `Auth::isAdmin()` server-side.
- **Student pages:** `app/middleware/require-student.php` enforces `Auth::isStudent()` server-side.
- **Vote submission:** `cast-vote.php` re-validates eligibility, election status, and candidate validity inside the transaction on every submission — client-side state is never trusted.

### 9. File Upload Security
- **Allowed types:** Only `image/jpeg`, `image/png`, `image/webp` (validated via `finfo` MIME detection, not file extension alone).
- **Size limit:** 2MB maximum.
- **Filenames:** Server-generated UUIDs. Client-supplied filenames are never used.
- **Storage:** `public/uploads/candidates/` — served as static images, not executed.
- **Recommendation:** In production, serve uploads from outside the PHP-executable web root.

### 10. Audit Logging
- All critical administrative actions are recorded in `audit_logs`.
- **Never logged:** Passwords, session tokens, CSRF tokens, candidate selections.
- **Important:** The `CAST_VOTE` audit entry records only the receipt hash and election ID — **not** the candidate ID — to preserve ballot privacy.

---

## Known Limitations

### Ballot Privacy (Not True Anonymity)
The application separates `voter_participation` (who voted) from `ballots` (what was chosen) with no direct FK between them. However:

- Timestamps on both records are close together and could be correlated.
- Sequential database IDs could be correlated if inserted in the same transaction.
- Application logs, web server access logs, and database general query logs may still reveal the pairing.
- Database administrators with direct table access can query both tables and attempt correlation.

**This model is a privacy improvement over direct voter-candidate storage, but it does not constitute a cryptographically strong anonymous ballot.**

### True Ballot Secrecy Requirements
Genuine ballot secrecy in high-stakes elections requires:
- Homomorphic encryption or mix-net schemes
- Verifiable shuffle proofs
- Independently reviewed cryptographic implementation
- Operational controls (separate database operators, secured logs)
- Independent audit

### Application-Level vs. Database-Level Restrictions
Administrative role checks are enforced in PHP. A developer with direct MySQL access can query any table. Audit log restrictions apply only at the application layer.

### No Email Verification
Student accounts are verified manually by an administrator. Optional SMTP email verification is configurable but disabled by default.

---

## Production Security Recommendations

1. Set `APP_DEBUG=false` (hides stack traces and error details).
2. Configure HTTPS (`SESSION_SECURE=true` once TLS is active).
3. Use a dedicated MySQL user with only `SELECT, INSERT, UPDATE, DELETE` on `college_voting.*` — no `DROP`, `CREATE`, or `GRANT`.
4. Store `.env` outside the web root or use system environment variables.
5. Configure `open_basedir` and `disable_functions` in `php.ini`.
6. Enable `ModSecurity` or an equivalent WAF.
7. Configure `Content-Security-Policy`, `X-Frame-Options`, and `X-Content-Type-Options` headers.
8. Schedule daily database backups and test restoration.
9. Review access logs regularly for unusual voting patterns.

---

## Disclaimer

This system is built for educational and portfolio purposes. The author does not claim it is tamper-proof, fully anonymous, or suitable for binding official elections without independent security review.
