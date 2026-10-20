<?php
/**
 * College Voting System - Ballot & Voter Participation Repository
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

class BallotRepository {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function hasStudentVoted(int $electionId, int $studentId): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS cnt 
            FROM voter_participation 
            WHERE election_id = ? AND student_id = ?
        ");
        $stmt->execute([$electionId, $studentId]);
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) > 0;
    }

    public function getParticipationRecord(int $electionId, int $studentId): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM voter_participation 
            WHERE election_id = ? AND student_id = ?
        ");
        $stmt->execute([$electionId, $studentId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function getStudentVotingHistory(int $studentId): array {
        $stmt = $this->db->prepare("
            SELECT vp.id, vp.election_id, vp.voted_at, vp.receipt_hash,
                   e.title AS election_title, e.academic_year, e.status AS election_status
            FROM voter_participation vp
            JOIN elections e ON vp.election_id = e.id
            WHERE vp.student_id = ?
            ORDER BY vp.voted_at DESC
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Executes the atomic voting transaction.
     * Enforces the secret-ballot privacy model:
     * - Records student participation in `voter_participation` with unique constraint.
     * - Records candidate choice in `ballots` WITHOUT student_id.
     */
    public function recordVoteTransaction(
        int $electionId,
        int $studentId,
        int $candidateId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = ''
    ): array {
        $this->db->beginTransaction();

        try {
            // Check within transaction if student already voted
            if ($this->hasStudentVoted($electionId, $studentId)) {
                $this->db->rollBack();
                throw new RuntimeException("You have already cast a vote in this election. Duplicate votes are strictly prohibited.");
            }

            // 1. Insert voter participation record
            $receiptHash = generate_receipt_hash($electionId, $studentId);
            $now = date('Y-m-d H:i:s');

            $stmtPart = $this->db->prepare("
                INSERT INTO voter_participation (
                    election_id, student_id, voted_at, ip_address, user_agent, receipt_hash
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtPart->execute([
                $electionId,
                $studentId,
                $now,
                $ipAddress,
                substr($userAgent, 0, 250),
                $receiptHash
            ]);

            // 2. Insert ballot record with anonymous token (NO student identity attached)
            $ballotToken = 'blt_' . bin2hex(random_bytes(24));
            $stmtBallot = $this->db->prepare("
                INSERT INTO ballots (election_id, candidate_id, ballot_token, cast_at)
                VALUES (?, ?, ?, ?)
            ");
            $stmtBallot->execute([
                $electionId,
                $candidateId,
                $ballotToken,
                $now
            ]);

            // Commit atomic transaction
            $this->db->commit();

            return [
                'success' => true,
                'receipt_hash' => $receiptHash,
                'voted_at' => $now
            ];

        } catch (PDOException $e) {
            $this->db->rollBack();
            // Handle duplicate key error code 23000
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE constraint') || str_contains($e->getMessage(), 'Duplicate entry')) {
                throw new RuntimeException("A vote submission for your student account is already recorded or is currently processing.");
            }
            throw new RuntimeException("Voting transaction failed: " . $e->getMessage());
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getTotalVotes(int $electionId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS total FROM ballots WHERE election_id = ?");
        $stmt->execute([$electionId]);
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function getVoteCountsByCandidate(int $electionId): array {
        $stmt = $this->db->prepare("
            SELECT c.id AS candidate_id, c.name, c.position, c.photo_url,
                   d.name AS department_name, d.code AS department_code,
                   COUNT(b.id) AS vote_count
            FROM candidates c
            LEFT JOIN departments d ON c.department_id = d.id
            LEFT JOIN ballots b ON b.candidate_id = c.id AND b.election_id = ?
            WHERE c.election_id = ? AND c.status = 'active'
            GROUP BY c.id, c.name, c.position, c.photo_url, d.name, d.code
            ORDER BY vote_count DESC, c.name ASC
        ");
        $stmt->execute([$electionId, $electionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEligibleVotersCount(int $electionId): int {
        // Query election type & eligibility
        $stmtElec = $this->db->prepare("SELECT eligibility_type FROM elections WHERE id = ?");
        $stmtElec->execute([$electionId]);
        $elec = $stmtElec->fetch(PDO::FETCH_ASSOC);

        if (!$elec) return 0;

        $type = $elec['eligibility_type'];

        if ($type === 'all_students') {
            $stmt = $this->db->query("SELECT COUNT(*) AS cnt FROM students WHERE is_verified = 1");
            return (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        }

        if ($type === 'by_department') {
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT s.id) AS cnt 
                FROM students s
                JOIN election_eligibility ee ON ee.department_id = s.department_id
                WHERE ee.election_id = ? AND s.is_verified = 1
            ");
            $stmt->execute([$electionId]);
            return (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        }

        if ($type === 'by_year') {
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT s.id) AS cnt 
                FROM students s
                JOIN election_eligibility ee ON ee.academic_year = s.academic_year
                WHERE ee.election_id = ? AND s.is_verified = 1
            ");
            $stmt->execute([$electionId]);
            return (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        }

        return 0;
    }
}
