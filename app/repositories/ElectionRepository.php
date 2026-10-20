<?php
/**
 * College Voting System - Election Repository
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class ElectionRepository {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function getAll(array $filters = []): array {
        $sql = "
            SELECT e.*, u.email AS creator_email,
                   (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id AND c.status = 'active') AS candidate_count,
                   (SELECT COUNT(*) FROM voter_participation vp WHERE vp.election_id = e.id) AS total_votes
            FROM elections e
            LEFT JOIN users u ON e.created_by = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND e.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['academic_year'])) {
            $sql .= " AND e.academic_year = ?";
            $params[] = $filters['academic_year'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (e.title LIKE ? OR e.description LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $sql .= " ORDER BY e.start_time DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT e.*, u.email AS creator_email,
                   (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id AND c.status = 'active') AS candidate_count,
                   (SELECT COUNT(*) FROM voter_participation vp WHERE vp.election_id = e.id) AS total_votes
            FROM elections e
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO elections (
                title, description, academic_year, election_type, 
                start_time, end_time, status, eligibility_type, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['academic_year'],
            $data['election_type'] ?? 'general',
            $data['start_time'],
            $data['end_time'],
            $data['status'] ?? 'draft',
            $data['eligibility_type'] ?? 'all_students',
            $data['created_by'] ?? null,
            $now
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE elections SET
                title = ?,
                description = ?,
                academic_year = ?,
                election_type = ?,
                start_time = ?,
                end_time = ?,
                status = ?,
                eligibility_type = ?,
                updated_at = ?
            WHERE id = ?
        ");
        $now = date('Y-m-d H:i:s');
        return $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['academic_year'],
            $data['election_type'],
            $data['start_time'],
            $data['end_time'],
            $data['status'],
            $data['eligibility_type'],
            $now,
            $id
        ]);
    }

    public function updateStatus(int $id, string $status): bool {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("UPDATE elections SET status = ?, updated_at = ? WHERE id = ?");
        return $stmt->execute([$status, $now, $id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM elections WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getStats(): array {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) AS total_elections,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) AS scheduled_elections,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_elections,
                SUM(CASE WHEN status IN ('closed', 'results_published') THEN 1 ELSE 0 END) AS completed_elections,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft_elections
            FROM elections
        ");
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmtVotes = $this->db->query("SELECT COUNT(*) AS total_votes FROM ballots");
        $totalVotes = (int)($stmtVotes->fetch(PDO::FETCH_ASSOC)['total_votes'] ?? 0);

        return [
            'total_elections' => (int)($counts['total_elections'] ?? 0),
            'scheduled_elections' => (int)($counts['scheduled_elections'] ?? 0),
            'open_elections' => (int)($counts['open_elections'] ?? 0),
            'completed_elections' => (int)($counts['completed_elections'] ?? 0),
            'draft_elections' => (int)($counts['draft_elections'] ?? 0),
            'total_votes' => $totalVotes
        ];
    }
}
