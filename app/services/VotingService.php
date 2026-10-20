<?php
/**
 * College Voting System - Secure Voting Service
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../repositories/BallotRepository.php';
require_once __DIR__ . '/EligibilityService.php';

class VotingService {
    private PDO $db;
    private EligibilityService $eligibilityService;
    private BallotRepository $ballotRepo;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        $this->eligibilityService = new EligibilityService($this->db);
        $this->ballotRepo = new BallotRepository($this->db);
    }

    /**
     * Cast vote with strict end-to-end validation and transaction integrity
     */
    public function castVote(
        int $electionId,
        int $studentId,
        int $candidateId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = ''
    ): array {
        // 1. Re-validate voter eligibility
        $eligibility = $this->eligibilityService->check($electionId, $studentId);
        if (!$eligibility['eligible']) {
            throw new RuntimeException($eligibility['reason']);
        }

        // 2. Validate candidate integrity
        $stmtCand = $this->db->prepare("
            SELECT * FROM candidates 
            WHERE id = ? AND election_id = ? AND status = 'active'
        ");
        $stmtCand->execute([$candidateId, $electionId]);
        $candidate = $stmtCand->fetch(PDO::FETCH_ASSOC);

        if (!$candidate) {
            throw new RuntimeException("Selected candidate is invalid, withdrawn, or does not belong to this election.");
        }

        // 3. Execute atomic transaction
        $result = $this->ballotRepo->recordVoteTransaction(
            $electionId,
            $studentId,
            $candidateId,
            $ipAddress,
            $userAgent
        );

        // 4. Retrieve student user_id for in-app notification
        $stmtUser = $this->db->prepare("SELECT user_id, full_name FROM students WHERE id = ?");
        $stmtUser->execute([$studentId]);
        $stud = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($stud && !empty($stud['user_id'])) {
            create_notification(
                $this->db,
                (int)$stud['user_id'],
                'Vote Recorded Successfully',
                "Your vote for election #{$electionId} has been successfully recorded. Verification Receipt Code: {$result['receipt_hash']}",
                'success'
            );
        }

        // 5. Safe audit logging - never logs candidate selection
        log_audit(
            $this->db,
            $stud ? (int)$stud['user_id'] : null,
            'voter@system',
            'CAST_VOTE',
            'election',
            $electionId,
            "A verified vote was cast in election #{$electionId}. Receipt: {$result['receipt_hash']}"
        );

        return $result;
    }
}
