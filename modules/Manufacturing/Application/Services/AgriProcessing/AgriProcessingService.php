<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Services\AgriProcessing;

use Brick\Math\BigRational;
use Modules\Shared\Domain\Algorithms\Lp\IntegerBlendRounder;
use Modules\Shared\Domain\Algorithms\Lp\LpCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Lp\LpStatus;
use Modules\Shared\Domain\Algorithms\Lp\RationalSimplex;

class AgriProcessingService
{
    /**
     * D168-2 Optimasi blending komoditas menggunakan RationalSimplex & LpCertificateVerifier.
     *
     * @param  list<array{id: string, cost_per_kg: int, protein_bps: int, moisture_bps: int, available_kg: int}>  $lots
     * @param  array{target_weight_kg: int, min_protein_bps: int, max_moisture_bps: int}  $target
     */
    public function optimizeBlending(array $lots, array $target): AgriBlendingResult
    {
        $n = count($lots);
        if ($n === 0) {
            return new AgriBlendingResult(
                status: LpStatus::INFEASIBLE,
                allocations: [],
                totalCostIdr: 0,
                isCertificateValid: true
            );
        }

        // Objective: Min sum(cost_i * x_i) <=> Max sum(-cost_i * x_i)
        $c = [];
        foreach ($lots as $lot) {
            $c[] = -$lot['cost_per_kg'];
        }

        $A = [];
        $b = [];

        // 1. Kapasitas maksimum per lot: x_i <= available_kg
        foreach ($lots as $i => $lot) {
            $row = array_fill(0, $n, 0);
            $row[$i] = 1;
            $A[] = $row;
            $b[] = $lot['available_kg'];
        }

        $targetWeight = $target['target_weight_kg'];

        // 2. Total berat target: sum(x_i) <= W_target
        $A[] = array_fill(0, $n, 1);
        $b[] = $targetWeight;

        // 3. Total berat target: sum(x_i) >= W_target <=> -sum(x_i) <= -W_target
        $A[] = array_fill(0, $n, -1);
        $b[] = -$targetWeight;

        // 4. Syarat kadar protein minimum: sum(protein_i * x_i) >= min_protein * W_target
        // <=> sum( (min_protein - protein_i) * x_i ) <= 0
        $proteinRow = [];
        $minProtein = $target['min_protein_bps'];
        foreach ($lots as $lot) {
            $proteinRow[] = $minProtein - $lot['protein_bps'];
        }
        $A[] = $proteinRow;
        $b[] = 0;

        // 5. Syarat kadar air maksimum: sum(moisture_i * x_i) <= max_moisture * W_target
        // <=> sum( (moisture_i - max_moisture) * x_i ) <= 0
        $moistureRow = [];
        $maxMoisture = $target['max_moisture_bps'];
        foreach ($lots as $lot) {
            $moistureRow[] = $lot['moisture_bps'] - $maxMoisture;
        }
        $A[] = $moistureRow;
        $b[] = 0;

        $simplex = new RationalSimplex;
        $lpResult = $simplex->solve($c, $A, $b);

        $verifier = new LpCertificateVerifier;
        $cR = array_map(fn ($v) => BigRational::of($v), $c);
        $AR = array_map(fn ($r) => array_map(fn ($v) => BigRational::of($v), $r), $A);
        $bR = array_map(fn ($v) => BigRational::of($v), $b);
        $verified = $verifier->verify($cR, $AR, $bR, $lpResult);

        if ($lpResult->status !== LpStatus::OPTIMAL) {
            $farkas = null;
            if (! empty($lpResult->y)) {
                $farkas = [
                    'vector' => array_map(fn ($v) => (string) $v, $lpResult->y),
                ];
            }

            return new AgriBlendingResult(
                status: $lpResult->status,
                allocations: [],
                totalCostIdr: 0,
                isCertificateValid: $verified,
                farkasCertificate: $farkas
            );
        }

        // Bulatkan rasional ke integer bulat menggunakan IntegerBlendRounder
        $rounder = new IntegerBlendRounder;
        $stockpileCaps = array_column($lots, 'available_kg');
        $roundResult = $rounder->round(
            $lpResult->x,
            $targetWeight,
            $targetWeight,
            $stockpileCaps,
            [$proteinRow, $moistureRow]
        );

        $allocations = [];
        $totalCost = 0;
        foreach ($lots as $i => $lot) {
            $qty = $roundResult['x'][$i] ?? ($lpResult->x[$i]->getNumerator()->quotient($lpResult->x[$i]->getDenominator())->toInt());
            $allocations[$lot['id']] = $qty;
            $totalCost += $qty * $lot['cost_per_kg'];
        }

        return new AgriBlendingResult(
            status: LpStatus::OPTIMAL,
            allocations: $allocations,
            totalCostIdr: $totalCost,
            isCertificateValid: $verified,
            farkasCertificate: null
        );
    }
}
