<?php
/**
 * College Voting System - Candidate Repository
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class CandidateRepository {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function getAll(array $filters = []): array {
        $sql = "
            SELECT c.*, e.title AS election_title, e.status AS election_status,
                   d.name AS department_name, d.code AS department_code,
                   s.registration_no AS student_reg_no,
                   (SELECT COUNT(*) FROM ballots b WHERE b.candidate_id = c.id) AS vote_count
            FROM candidates c
            JOIN elections e ON c.election_id = e.id
            LEFT JOIN departments d ON c.department_id = d.id
            LEFT JOIN students s ON c.student_id = s.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['election_id'])) {
            $sql .= " AND c.election_id = ?";
            $params[] = (int)$filters['election_id'];
        }

        if (!empty($filters['position'])) {
            $sql .= " AND c.position = ?";
            $params[] = $filters['position'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (c.name LIKE ? OR c.position LIKE ? OR c.manifesto LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $sql .= " ORDER BY c.election_id DESC, c.position ASC, c.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT c.*, e.title AS election_title, e.status AS election_status,
                   d.name AS department_name, d.code AS department_code,
                   s.registration_no AS student_reg_no,
                   (SELECT COUNT(*) FROM ballots b WHERE b.candidate_id = c.id) AS vote_count
            FROM candidates c
            JOIN elections e ON c.election_id = e.id
            LEFT JOIN departments d ON c.department_id = d.id
            LEFT JOIN students s ON c.student_id = s.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function getByElectionId(int $electionId, bool $activeOnly = true): array {
        $sql = "
            SELECT c.*, d.name AS department_name, d.code AS department_code,
                   (SELECT COUNT(*) FROM ballots b WHERE b.candidate_id = c.id) AS vote_count
            FROM candidates c
            LEFT JOIN departments d ON c.department_id = d.id
            WHERE c.election_id = ?
        ";
        if ($activeOnly) {
            $sql .= " AND c.status = 'active'";
        }
        $sql .= " ORDER BY c.position ASC, c.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$electionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO candidates (
                election_id, student_id, name, position, 
                department_id, manifesto, photo_url, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            $data['election_id'],
            $data['student_id'] ?? null,
            $data['name'],
            $data['position'],
            $data['department_id'] ?? null,
            $data['manifesto'] ?? null,
            $data['photo_url'] ?? null,
            $data['status'] ?? 'active',
            $now
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE candidates SET
                election_id = ?,
                student_id = ?,
                name = ?,
                position = ?,
                department_id = ?,
                manifesto = ?,
                photo_url = ?,
                status = ?,
                updated_at = ?
            WHERE id = ?
        ");
        $now = date('Y-m-d H:i:s');
        return $stmt->execute([
            $data['election_id'],
            $data['student_id'] ?? null,
            $data['name'],
            $data['position'],
            $data['department_id'] ?? null,
            $data['manifesto'] ?? null,
            $data['photo_url'] ?? null,
            $data['status'] ?? 'active',
            $now,
            $id
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM candidates WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getTotalCandidates(): int {
        $stmt = $this->db->query("SELECT COUNT(*) AS cnt FROM candidates WHERE status = 'active'");
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    }
}
