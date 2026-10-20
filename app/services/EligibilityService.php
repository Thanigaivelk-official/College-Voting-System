<?php
/**
 * College Voting System - Voter Eligibility Service
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/BallotRepository.php';

class EligibilityService {
    private PDO $db;
    private BallotRepository $ballotRepo;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        $this->ballotRepo = new BallotRepository($this->db);
    }

    /**
     * Comprehensive eligibility validation for a student in an election
     */
    public function check(int $electionId, int $studentId): array {
        // 1. Fetch student
        $stmtStudent = $this->db->prepare("
            SELECT s.*, u.status AS user_status 
            FROM students s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.id = ?
        ");
        $stmtStudent->execute([$studentId]);
        $student = $stmtStudent->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            return ['eligible' => false, 'reason' => 'Student record not found.'];
        }

        if ($student['user_status'] !== 'active') {
            return ['eligible' => false, 'reason' => 'Your student account is not active.'];
        }

        if ((int)$student['is_verified'] !== 1) {
            return ['eligible' => false, 'reason' => 'Your student account is pending verification by the Election Commission. Only verified students may participate.'];
        }

        // 2. Fetch election
        $stmtElec = $this->db->prepare("SELECT * FROM elections WHERE id = ?");
        $stmtElec->execute([$electionId]);
        $election = $stmtElec->fetch(PDO::FETCH_ASSOC);

        if (!$election) {
            return ['eligible' => false, 'reason' => 'Election record not found.'];
        }

        if ($election['status'] !== 'open') {
            return ['eligible' => false, 'reason' => "This election is currently not open for voting (Current status: {$election['status']})."];
        }

        $now = time();
        $start = strtotime($election['start_time']);
        $end = strtotime($election['end_time']);

        if ($now < $start) {
            return ['eligible' => false, 'reason' => 'The voting window for this election has not started yet.'];
        }

        if ($now > $end) {
            return ['eligible' => false, 'reason' => 'The voting window for this election has concluded.'];
        }

        // 3. Check if already voted
        if ($this->ballotRepo->hasStudentVoted($electionId, $studentId)) {
            return ['eligible' => false, 'reason' => 'You have already cast your vote in this election. Only one vote per student is permitted.'];
        }

        // 4. Check eligibility criteria
        $eligibilityType = $election['eligibility_type'];

        if ($eligibilityType === 'by_department') {
            $stmtDept = $this->db->prepare("
                SELECT COUNT(*) AS cnt 
                FROM election_eligibility 
                WHERE election_id = ? AND department_id = ?
            ");
            $stmtDept->execute([$electionId, $student['department_id']]);
            $isDeptEligible = (int)($stmtDept->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) > 0;

            if (!$isDeptEligible) {
                return ['eligible' => false, 'reason' => 'This election is restricted to students of specific departments. Your department is not eligible.'];
            }
        } elseif ($eligibilityType === 'by_year') {
            $stmtYear = $this->db->prepare("
                SELECT COUNT(*) AS cnt 
                FROM election_eligibility 
                WHERE election_id = ? AND academic_year = ?
            ");
            $stmtYear->execute([$electionId, $student['academic_year']]);
            $isYearEligible = (int)($stmtYear->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) > 0;

            if (!$isYearEligible) {
                return ['eligible' => false, 'reason' => 'This election is restricted to specific academic years. Your year of study is not eligible.'];
            }
        }

        return [
            'eligible' => true,
            'reason' => 'You are verified and eligible to cast your vote.'
        ];
    }
}
