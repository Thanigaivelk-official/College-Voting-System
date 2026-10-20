<?php
/**
 * College Voting System - Election Management Service
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/ElectionRepository.php';
require_once __DIR__ . '/../includes/functions.php';

class ElectionService {
    private PDO $db;
    private ElectionRepository $electionRepo;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        $this->electionRepo = new ElectionRepository($this->db);
    }

    /**
     * Synchronize election statuses based on current timestamp
     */
    public function syncStatuses(): void {
        $now = date('Y-m-d H:i:s');

        // Transition scheduled elections to open if start_time reached
        $stmtOpen = $this->db->prepare("
            UPDATE elections 
            SET status = 'open', updated_at = ? 
            WHERE status = 'scheduled' AND start_time <= ? AND end_time > ?
        ");
        $stmtOpen->execute([$now, $now, $now]);

        // Transition open elections to closed if end_time reached
        $stmtClose = $this->db->prepare("
            UPDATE elections 
            SET status = 'closed', updated_at = ? 
            WHERE status = 'open' AND end_time <= ?
        ");
        $stmtClose->execute([$now, $now]);
    }

    public function createElection(array $data, array $eligibilityRules = []): int {
        // Validate dates
        if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
            throw new InvalidArgumentException("Election end time must be later than start time.");
        }

        $this->db->beginTransaction();
        try {
            $electionId = $this->electionRepo->create($data);

            // Save eligibility rules if specified
            if (!empty($eligibilityRules['departments']) && $data['eligibility_type'] === 'by_department') {
                $stmtDept = $this->db->prepare("INSERT INTO election_eligibility (election_id, department_id) VALUES (?, ?)");
                foreach ($eligibilityRules['departments'] as $deptId) {
                    $stmtDept->execute([$electionId, (int)$deptId]);
                }
            } elseif (!empty($eligibilityRules['academic_years']) && $data['eligibility_type'] === 'by_year') {
                $stmtYear = $this->db->prepare("INSERT INTO election_eligibility (election_id, academic_year) VALUES (?, ?)");
                foreach ($eligibilityRules['academic_years'] as $yr) {
                    $stmtYear->execute([$electionId, (int)$yr]);
                }
            }

            $this->db->commit();
            return $electionId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateElection(int $id, array $data, array $eligibilityRules = []): bool {
        if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
            throw new InvalidArgumentException("Election end time must be later than start time.");
        }

        $this->db->beginTransaction();
        try {
            $this->electionRepo->update($id, $data);

            // Reset and update eligibility rules
            $stmtDel = $this->db->prepare("DELETE FROM election_eligibility WHERE election_id = ?");
            $stmtDel->execute([$id]);

            if (!empty($eligibilityRules['departments']) && $data['eligibility_type'] === 'by_department') {
                $stmtDept = $this->db->prepare("INSERT INTO election_eligibility (election_id, department_id) VALUES (?, ?)");
                foreach ($eligibilityRules['departments'] as $deptId) {
                    $stmtDept->execute([$id, (int)$deptId]);
                }
            } elseif (!empty($eligibilityRules['academic_years']) && $data['eligibility_type'] === 'by_year') {
                $stmtYear = $this->db->prepare("INSERT INTO election_eligibility (election_id, academic_year) VALUES (?, ?)");
                foreach ($eligibilityRules['academic_years'] as $yr) {
                    $stmtYear->execute([$id, (int)$yr]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
