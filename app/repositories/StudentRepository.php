<?php
/**
 * College Voting System - Student Repository
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class StudentRepository {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function getAll(array $filters = []): array {
        $sql = "
            SELECT s.*, u.email, u.status AS user_status, d.name AS department_name, d.code AS department_code,
                   v.email AS verifier_email
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN departments d ON s.department_id = d.id
            LEFT JOIN users v ON s.verified_by = v.id
            WHERE 1=1
        ";
        $params = [];

        if (isset($filters['is_verified']) && $filters['is_verified'] !== '') {
            $sql .= " AND s.is_verified = ?";
            $params[] = (int)$filters['is_verified'];
        }

        if (!empty($filters['department_id'])) {
            $sql .= " AND s.department_id = ?";
            $params[] = (int)$filters['department_id'];
        }

        if (!empty($filters['academic_year'])) {
            $sql .= " AND s.academic_year = ?";
            $params[] = (int)$filters['academic_year'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (s.full_name LIKE ? OR s.registration_no LIKE ? OR u.email LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $sql .= " ORDER BY s.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*, u.email, u.status AS user_status, d.name AS department_name, d.code AS department_code
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN departments d ON s.department_id = d.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function findByUserId(int $userId): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*, u.email, u.status AS user_status, d.name AS department_name, d.code AS department_code
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN departments d ON s.department_id = d.id
            WHERE s.user_id = ?
        ");
        $stmt->execute([$userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function findByRegistrationNo(string $regNo): ?array {
        $stmt = $this->db->prepare("SELECT * FROM students WHERE registration_no = ?");
        $stmt->execute([$regNo]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function createStudent(array $userData, array $studentData): int {
        $this->db->beginTransaction();
        try {
            // Check whitelist if exists
            $stmtWhite = $this->db->prepare("SELECT * FROM whitelist_registration_numbers WHERE registration_no = ?");
            $stmtWhite->execute([$studentData['registration_no']]);
            $whitelisted = $stmtWhite->fetch(PDO::FETCH_ASSOC);

            // Auto-verify if pre-whitelisted, otherwise pending verification
            $isVerified = $whitelisted ? 1 : 0;
            $userStatus = $whitelisted ? 'active' : 'pending';

            $now = date('Y-m-d H:i:s');
            $stmtUser = $this->db->prepare("
                INSERT INTO users (email, password_hash, role, status, created_at)
                VALUES (?, ?, 'student', ?, ?)
            ");
            $stmtUser->execute([
                $userData['email'],
                $userData['password_hash'],
                $userStatus,
                $now
            ]);
            $userId = (int)$this->db->lastInsertId();

            $stmtStudent = $this->db->prepare("
                INSERT INTO students (
                    user_id, full_name, registration_no, department_id, 
                    course, academic_year, is_verified, verified_at, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $verifiedAt = $isVerified ? $now : null;
            $stmtStudent->execute([
                $userId,
                $studentData['full_name'],
                $studentData['registration_no'],
                $studentData['department_id'],
                $studentData['course'],
                $studentData['academic_year'],
                $isVerified,
                $verifiedAt,
                $now
            ]);
            $studentId = (int)$this->db->lastInsertId();

            if ($whitelisted) {
                $stmtUpdateWhite = $this->db->prepare("UPDATE whitelist_registration_numbers SET is_registered = 1 WHERE registration_no = ?");
                $stmtUpdateWhite->execute([$studentData['registration_no']]);
            }

            $this->db->commit();
            return $studentId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function verifyStudent(int $studentId, int $adminUserId): bool {
        $student = $this->findById($studentId);
        if (!$student) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $stmt1 = $this->db->prepare("
                UPDATE students SET is_verified = 1, verified_at = ?, verified_by = ? WHERE id = ?
            ");
            $stmt1->execute([$now, $adminUserId, $studentId]);

            $stmt2 = $this->db->prepare("
                UPDATE users SET status = 'active' WHERE id = ?
            ");
            $stmt2->execute([$student['user_id']]);

            // Create notification for student
            create_notification(
                $this->db,
                (int)$student['user_id'],
                'Account Verified',
                'Your student account has been officially verified by the Election Commission. You are now eligible to cast votes in open elections.',
                'success'
            );

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getStats(): array {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) AS total_students,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) AS verified_students,
                SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) AS pending_students
            FROM students
        ");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_students' => (int)($res['total_students'] ?? 0),
            'verified_students' => (int)($res['verified_students'] ?? 0),
            'pending_students' => (int)($res['pending_students'] ?? 0)
        ];
    }
}
