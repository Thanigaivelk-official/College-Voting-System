<?php
/**
 * College Voting System - Results Generation & Publication Service
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/BallotRepository.php';
require_once __DIR__ . '/../repositories/ElectionRepository.php';
require_once __DIR__ . '/../includes/functions.php';

class ResultService {
    private PDO $db;
    private BallotRepository $ballotRepo;
    private ElectionRepository $electionRepo;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        $this->ballotRepo = new BallotRepository($this->db);
        $this->electionRepo = new ElectionRepository($this->db);
    }

    /**
     * Compute real-time election result metrics
     */
    public function calculateResults(int $electionId): array {
        $election = $this->electionRepo->findById($electionId);
        if (!$election) {
            throw new InvalidArgumentException("Election #{$electionId} does not exist.");
        }

        $candidates = $this->ballotRepo->getVoteCountsByCandidate($electionId);
        $totalVotes = $this->ballotRepo->getTotalVotes($electionId);
        $totalEligible = $this->ballotRepo->getEligibleVotersCount($electionId);

        // Turnout calculation
        $turnout = $totalEligible > 0 ? round(($totalVotes / $totalEligible) * 100, 2) : 0.0;

        // Candidate breakdown with percentage
        $breakdown = [];
        $maxVotes = -1;
        $topCandidates = [];

        foreach ($candidates as $cand) {
            $votes = (int)$cand['vote_count'];
            $pct = $totalVotes > 0 ? round(($votes / $totalVotes) * 100, 2) : 0.0;

            $candData = [
                'candidate_id' => (int)$cand['candidate_id'],
                'name' => $cand['name'],
                'position' => $cand['position'],
                'department_name' => $cand['department_name'] ?? 'General',
                'votes' => $votes,
                'percentage' => $pct
            ];
            $breakdown[] = $candData;

            if ($votes > $maxVotes && $votes > 0) {
                $maxVotes = $votes;
                $topCandidates = [$candData];
            } elseif ($votes === $maxVotes && $votes > 0) {
                $topCandidates[] = $candData;
            }
        }

        $isTie = count($topCandidates) > 1;
        $winner = null;
        $tieDetails = null;

        if ($isTie) {
            $names = array_map(fn($c) => $c['name'], $topCandidates);
            $tieDetails = "Tie detected between " . implode(' and ', $names) . " with {$maxVotes} votes each.";
        } elseif (count($topCandidates) === 1) {
            $winner = $topCandidates[0];
        }

        return [
            'election' => $election,
            'total_eligible_voters' => $totalEligible,
            'total_votes_cast' => $totalVotes,
            'turnout_percentage' => $turnout,
            'candidates' => $breakdown,
            'is_tie' => $isTie,
            'winner' => $winner,
            'tie_details' => $tieDetails
        ];
    }

    /**
     * Publish official results snapshot
     */
    public function publishResults(int $electionId, int $adminUserId): array {
        $results = $this->calculateResults($electionId);
        $now = date('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            $breakdownJson = json_encode($results['candidates']);
            $winnerId = $results['winner']['candidate_id'] ?? null;
            $winnerName = $results['winner']['name'] ?? null;
            $isTieInt = $results['is_tie'] ? 1 : 0;

            // Delete prior snapshot if re-publishing
            $stmtDel = $this->db->prepare("DELETE FROM election_results WHERE election_id = ?");
            $stmtDel->execute([$electionId]);

            $stmt = $this->db->prepare("
                INSERT INTO election_results (
                    election_id, total_eligible_voters, total_votes_cast, turnout_percentage,
                    winner_candidate_id, winner_name, is_tie, tie_details, candidate_breakdown,
                    published_at, published_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $electionId,
                $results['total_eligible_voters'],
                $results['total_votes_cast'],
                $results['turnout_percentage'],
                $winnerId,
                $winnerName,
                $isTieInt,
                $results['tie_details'],
                $breakdownJson,
                $now,
                $adminUserId
            ]);

            // Update election status
            $this->electionRepo->updateStatus($electionId, 'results_published');

            // Notify all verified students
            $stmtStudents = $this->db->query("SELECT user_id FROM students WHERE is_verified = 1");
            $studentUsers = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

            foreach ($studentUsers as $su) {
                create_notification(
                    $this->db,
                    (int)$su['user_id'],
                    'Election Results Published',
                    "Official results for '{$results['election']['title']}' have been finalized and published.",
                    'info'
                );
            }

            // Audit log
            log_audit(
                $this->db,
                $adminUserId,
                'admin@commission',
                'PUBLISH_RESULTS',
                'election',
                $electionId,
                "Published verified results for election #{$electionId} ('{$results['election']['title']}')."
            );

            $this->db->commit();
            return $results;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get published snapshot for public/student view
     */
    public function getPublishedResults(int $electionId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM election_results WHERE election_id = ?");
        $stmt->execute([$electionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['candidate_breakdown'] = json_decode($row['candidate_breakdown'] ?? '[]', true);
        return $row;
    }

    /**
     * Generate safe CSV string for election results
     */
    public function exportResultsCsv(int $electionId): string {
        $data = $this->calculateResults($electionId);
        $output = fopen('php://temp', 'r+');

        // Header
        fputcsv($output, ['Election Results Report']);
        fputcsv($output, ['Title', sanitize_csv_field($data['election']['title'])]);
        fputcsv($output, ['Academic Year', sanitize_csv_field($data['election']['academic_year'])]);
        fputcsv($output, ['Status', sanitize_csv_field($data['election']['status'])]);
        fputcsv($output, ['Total Eligible Voters', $data['total_eligible_voters']]);
        fputcsv($output, ['Total Votes Cast', $data['total_votes_cast']]);
        fputcsv($output, ['Turnout Percentage', $data['turnout_percentage'] . '%']);
        fputcsv($output, ['Outcome', $data['is_tie'] ? 'Tie: ' . $data['tie_details'] : ($data['winner']['name'] ?? 'No votes cast')]);
        fputcsv($output, []); // Empty line

        // Candidate Table
        fputcsv($output, ['Candidate ID', 'Candidate Name', 'Position', 'Department', 'Votes Received', 'Percentage (%)']);
        foreach ($data['candidates'] as $cand) {
            fputcsv($output, [
                $cand['candidate_id'],
                sanitize_csv_field($cand['name']),
                sanitize_csv_field($cand['position']),
                sanitize_csv_field($cand['department_name']),
                $cand['votes'],
                $cand['percentage'] . '%'
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        return (string)$csv;
    }
}
