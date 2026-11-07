<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Underground;

use InvalidArgumentException;

/**
 * Algoritma M7: Hardy-Cross Underground Mine Ventilation Network Solver.
 * Menyelesaikan kesetimbangan aliran udara multi-branch dan multi-loop pada jaringan tambang bawah tanah.
 */
final class HardyCrossVentilationNetworkSolver
{
    /**
     * @param  list<AirwayBranch>  $branches
     * @param  list<VentilationLoop>  $loops
     */
    public function solve(
        array $branches,
        array $loops,
        float $tolerance = 0.001,
        int $maxIterations = 100,
        float $fanEfficiency = 0.75
    ): HardyCrossVentilationNetworkSolverResult {
        if (empty($branches)) {
            throw new InvalidArgumentException('Jaringan ventilasi tambang harus memiliki setidaknya satu cabang.');
        }

        if (empty($loops)) {
            throw new InvalidArgumentException('Jaringan ventilasi tambang harus memiliki setidaknya satu simpal tertutup (loop).');
        }

        // Peta debit aliran udara saat ini per branch ID
        /** @var array<string, float> $flows */
        $flows = [];
        /** @var array<string, AirwayBranch> $branchMap */
        $branchMap = [];

        foreach ($branches as $branch) {
            $flows[$branch->branchId] = $branch->initialFlowQ;
            $branchMap[$branch->branchId] = $branch;
        }

        $converged = false;
        $iteration = 0;
        $maxResidual = 0.0;

        while ($iteration < $maxIterations) {
            $iteration++;
            $maxCorrectionInThisIteration = 0.0;

            foreach ($loops as $loop) {
                $numerator = 0.0;   // sum of head losses: sum(dir * (R * Q^2 - H_fan))
                $denominator = 0.0; // sum of derivatives: 2 * sum(R * |Q|)

                foreach ($loop->branches as $item) {
                    $bId = $item['branch_id'];
                    $dir = (float) $item['direction'];

                    if (! isset($branchMap[$bId])) {
                        throw new InvalidArgumentException("Cabang ventilasi {$bId} tidak terdaftar dalam matriks cabang.");
                    }

                    $branch = $branchMap[$bId];
                    $currentQ = $flows[$bId];

                    // Penurunan tekanan gesekan cabang: Hf = R * Q^2
                    // Arah aliran aktual relatif terhadap arah loop
                    $flowSigned = $currentQ * $dir;
                    $headLossFriction = $branch->resistanceR * ($flowSigned * abs($flowSigned));
                    $fanPressure = $branch->isFanBranch ? ($branch->fanPressurePa * $dir) : 0.0;

                    $numerator += ($headLossFriction - $fanPressure);
                    $denominator += (2.0 * $branch->resistanceR * abs($currentQ));
                }

                // Cegah pembagian dengan nol jika seluruh aliran bernilai 0
                if ($denominator < 1e-6) {
                    $denominator = 1e-6;
                }

                $deltaQ = -($numerator / $denominator);

                if (abs($deltaQ) > $maxCorrectionInThisIteration) {
                    $maxCorrectionInThisIteration = abs($deltaQ);
                }

                // Perbarui debit cabang dalam loop ini
                foreach ($loop->branches as $item) {
                    $bId = $item['branch_id'];
                    $dir = (float) $item['direction'];
                    $flows[$bId] += ($deltaQ * $dir);
                }
            }

            $maxResidual = $maxCorrectionInThisIteration;

            if ($maxCorrectionInThisIteration <= $tolerance) {
                $converged = true;
                break;
            }
        }

        // Hitung penurunan tekanan final dan daya kipas total
        $finalHeadLosses = [];
        $totalFanPowerWatts = 0.0;

        foreach ($branches as $branch) {
            $q = $flows[$branch->branchId];
            $hl = $branch->resistanceR * ($q * abs($q));
            $finalHeadLosses[$branch->branchId] = round($hl, 3);

            if ($branch->isFanBranch && $branch->fanPressurePa > 0.0) {
                $fanPower = ($branch->fanPressurePa * abs($q)) / max(0.1, $fanEfficiency);
                $totalFanPowerWatts += $fanPower;
            }
        }

        $totalFanPowerKw = round($totalFanPowerWatts / 1000.0, 3);

        $roundedFlows = [];
        foreach ($flows as $bId => $qVal) {
            $roundedFlows[$bId] = round($qVal, 4);
        }

        $signature = HardyCrossVentilationNetworkVerifier::sign(
            $roundedFlows,
            $iteration,
            $converged,
            $totalFanPowerKw
        );

        return new HardyCrossVentilationNetworkSolverResult(
            finalFlowsQ: $roundedFlows,
            finalHeadLossesPa: $finalHeadLosses,
            iterationsCount: $iteration,
            converged: $converged,
            maxResidualFlow: round($maxResidual, 6),
            totalFanPowerKw: $totalFanPowerKw,
            hmacSignature: $signature
        );
    }
}
