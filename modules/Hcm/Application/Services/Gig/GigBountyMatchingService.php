<?php

declare(strict_types=1);

namespace Modules\Hcm\Application\Services\Gig;

use InvalidArgumentException;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\Gig\GovBounty;
use Modules\Shared\Domain\Algorithms\Assignment\AssignmentCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Assignment\AssignmentResult;
use Modules\Shared\Domain\Algorithms\Assignment\HungarianSolver;

class GigBountyMatchingService
{
    public function __construct(
        private ?HungarianSolver $solver = null,
        private ?AssignmentCertificateVerifier $verifier = null
    ) {
        $this->solver = $solver ?? new HungarianSolver;
        $this->verifier = $verifier ?? new AssignmentCertificateVerifier;
    }

    /**
     * Memetakan kandidat pekerja ke lowongan bounty dengan Hungarian algorithm.
     * Menggunakan transformasi maksimasi skor ke minimasi biaya:
     * cost = max_score - score.
     *
     * @param  list<Employee>  $workers
     * @param  list<GovBounty>  $bounties
     * @param  array<int, array<int, int>>  $scoreMatrix  [worker_idx => [bounty_idx => score]]
     * @return array{
     *     result: AssignmentResult,
     *     matches: list<array{worker_id: int, bounty_id: int, score: int}>,
     *     is_valid_certificate: bool
     * }
     */
    public function matchBounties(array $workers, array $bounties, array $scoreMatrix): array
    {
        $n = count($workers);
        $m = count($bounties);

        if ($n === 0 || $m === 0) {
            return [
                'result' => new AssignmentResult([], 0, [], []),
                'matches' => [],
                'is_valid_certificate' => true,
            ];
        }

        if ($n > $m) {
            throw new InvalidArgumentException("Jumlah pekerja ({$n}) tidak boleh melebihi jumlah bounty ({$m})");
        }

        // Cari max score untuk transformasi min-cost
        $maxScore = 0;
        foreach ($scoreMatrix as $row) {
            foreach ($row as $score) {
                if ($score > $maxScore) {
                    $maxScore = $score;
                }
            }
        }
        $maxScore += 10; // margin non-negative

        $costMatrix = [];
        for ($i = 0; $i < $n; $i++) {
            $costMatrix[$i] = [];
            for ($j = 0; $j < $m; $j++) {
                $score = $scoreMatrix[$i][$j] ?? 0;
                $costMatrix[$i][$j] = $maxScore - $score;
            }
        }

        $result = $this->solver->solve($costMatrix, $this->verifier);
        $isValidCert = $this->verifier->verify($costMatrix, $result);

        $matches = [];
        foreach ($result->assignment as $workerIdx => $bountyIdx) {
            $matches[] = [
                'worker_id' => $workers[$workerIdx]->id,
                'bounty_id' => $bounties[$bountyIdx]->id,
                'score' => $scoreMatrix[$workerIdx][$bountyIdx] ?? 0,
            ];
        }

        return [
            'result' => $result,
            'matches' => $matches,
            'is_valid_certificate' => $isValidCert,
        ];
    }
}
