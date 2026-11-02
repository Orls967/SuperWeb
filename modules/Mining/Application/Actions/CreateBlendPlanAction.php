<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Brick\Math\BigRational;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\MiningBlendPlan;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\Algorithms\Lp\IntegerBlendRounder;
use Modules\Shared\Domain\Algorithms\Lp\LpStatus;
use Modules\Shared\Domain\Algorithms\Lp\RationalSimplex;

class CreateBlendPlanAction extends BaseAction
{
    public function __construct(
        private readonly RationalSimplex $simplex,
        private readonly IntegerBlendRounder $rounder
    ) {}

    /**
     * @param  array<string, int>  $targetSpecs
     * @param  list<string>  $availableStockpileIds
     */
    public function handle(
        string $siteId,
        string $targetVesselName,
        int $targetMinKg,
        int $targetMaxKg,
        array $targetSpecs,
        array $availableStockpileIds,
        string $idempotencyKey = ''
    ): object {
        if (empty(trim($idempotencyKey))) {
            throw new \InvalidArgumentException('Idempotency key wajib diisi.');
        }

        // Fetch stockpiles
        $stockpiles = MiningStockpile::whereIn('id', $availableStockpileIds)
            ->where('site_id', $siteId)
            ->orderBy('id')
            ->get();

        $totalAvailable = $stockpiles->sum('total_kg');
        if ($totalAvailable < $targetMinKg) {
            throw new \DomainException("Total stok tersedia ({$totalAvailable}kg) tidak cukup untuk target minimum ({$targetMinKg}kg).");
        }

        $n = $stockpiles->count();
        if ($n === 0) {
            throw new \DomainException('Tidak ada stockpile yang tersedia.');
        }

        // 1. Setup LP Problem: Minimize cost / metal giveaway (Maximize -grade * x)
        // Variables: x_0, x_1, ..., x_{n-1}
        $c = [];
        foreach ($stockpiles as $stk) {
            $grade = (int) $stk->average_metal_bps;
            $c[] = -($grade > 0 ? $grade : 1);
        }
        $A = [];
        $b = [];

        // Constraints 1: Stockpile capacities x_i <= S_i
        $stockpileCaps = [];
        foreach ($stockpiles as $i => $stk) {
            $cap = (int) $stk->total_kg;
            $stockpileCaps[] = $cap;
            $row = array_fill(0, $n, 0);
            $row[$i] = 1;
            $A[] = $row;
            $b[] = $cap;
        }

        // Constraint 2: Sum(x_i) <= targetMaxKg
        $A[] = array_fill(0, $n, 1);
        $b[] = $targetMaxKg;

        // Constraint 3: -Sum(x_i) <= -targetMinKg
        $A[] = array_fill(0, $n, -1);
        $b[] = -$targetMinKg;

        // Constraint 4: Quality specs (e.g. min_metal_bps)
        $ratioConstraints = [];
        if (isset($targetSpecs['min_metal_bps'])) {
            $minMetal = (int) $targetSpecs['min_metal_bps'];
            $specRow = [];
            foreach ($stockpiles as $stk) {
                $grade = (int) $stk->average_metal_bps;
                // Sum(x_i * grade_i) >= minMetal * Sum(x_i)
                // <=> Sum(x_i * (minMetal - grade_i)) <= 0
                $specRow[] = $minMetal - $grade;
            }
            $A[] = $specRow;
            $b[] = 0;
            $ratioConstraints[] = $specRow;
        }

        $cR = array_map(fn ($v) => BigRational::of($v), $c);
        $AR = array_map(fn ($row) => array_map(fn ($v) => BigRational::of($v), $row), $A);
        $bR = array_map(fn ($v) => BigRational::of($v), $b);

        $lpProblem = ['c' => $c, 'A' => $A, 'b' => $b];

        // 2. Solve Simplex
        $result = $this->simplex->solve($cR, $AR, $bR);

        if ($result->status === LpStatus::INFEASIBLE) {
            $farkasRay = array_map(fn ($val) => (string) $val, $result->y);

            return (object) [
                'isFeasible' => false,
                'farkasRayCertificate' => $farkasRay,
                'lpProblem' => $lpProblem,
            ];
        }

        if ($result->status !== LpStatus::OPTIMAL) {
            return (object) [
                'isFeasible' => false,
                'farkasRayCertificate' => [],
                'lpProblem' => $lpProblem,
            ];
        }

        // 3. Integer Rounding (B0.8)
        $roundResult = $this->rounder->round(
            $result->x,
            $targetMinKg,
            $targetMaxKg,
            $stockpileCaps,
            $ratioConstraints
        );

        $allocations = [];
        foreach ($stockpiles as $idx => $stk) {
            $allocations[$stk->id] = $roundResult['x'][$idx];
        }

        $totalAllocatedKg = array_sum($allocations);
        $certificate = array_map(fn ($val) => (string) $val, $result->y);
        $rationalSolution = array_map(fn ($val) => (string) $val, $result->x);

        // 4. Persist Blend Plan
        $plan = DB::transaction(function () use (
            $siteId,
            $targetVesselName,
            $targetMinKg,
            $targetMaxKg,
            $targetSpecs,
            $allocations,
            $totalAllocatedKg,
            $certificate,
            $idempotencyKey
        ) {
            return MiningBlendPlan::create([
                'id' => (string) Str::uuid(),
                'plan_uuid' => $idempotencyKey,
                'site_id' => $siteId,
                'target_vessel_name' => $targetVesselName,
                'target_min_kg' => $targetMinKg,
                'target_max_kg' => $targetMaxKg,
                'target_specs_json' => json_encode($targetSpecs),
                'allocated_kg' => $totalAllocatedKg,
                'allocations_json' => json_encode($allocations),
                'certificate_json' => json_encode($certificate),
                'status' => 'SOLVED',
            ]);
        });

        return (object) [
            'isFeasible' => true,
            'totalAllocatedKg' => $totalAllocatedKg,
            'allocations' => $allocations,
            'plan' => $plan,
            'lpProblem' => $lpProblem,
            'rationalSolution' => $rationalSolution,
        ];
    }
}
