<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Shared\Application\BaseAction;

class PlanMineBlockExtractionAndStrippingRatioAction extends BaseAction
{
    /**
     * Plan mine block extraction schedule and compute stripping ratio (SR) economic cutoff.
     *
     * @return array{pit: MiningPit, ore_tonnage: float, waste_overburden_bcm: float, stripping_ratio: float, is_economically_viable: bool, estimated_cost_idr: int}
     */
    public function handle(
        string $siteId,
        string $pitId,
        float $oreTonnage,
        float $wasteOverburdenBcm,
        float $targetGradeNiPct = 1.80
    ): array {
        return DB::transaction(function () use ($pitId, $oreTonnage, $wasteOverburdenBcm) {
            $pit = MiningPit::lockForUpdate()->findOrFail($pitId);

            if ($oreTonnage <= 0) {
                throw new \InvalidArgumentException('Ore tonnage must be greater than zero.');
            }

            // Stripping Ratio = Waste Overburden (BCM) / Ore (Ton)
            $strippingRatio = round($wasteOverburdenBcm / $oreTonnage, 2);

            // Economic cutoff SR for open-pit nickel/coal is typically <= 4.5
            $isEconomicallyViable = ($strippingRatio <= 4.5);

            // Unit costs: IDR 65,000 / ton ore extraction + IDR 32,000 / BCM overburden stripping
            $oreExtractionCostIdr = (int) round($oreTonnage * 65000);
            $wasteStrippingCostIdr = (int) round($wasteOverburdenBcm * 32000);
            $totalEstimatedCostIdr = $oreExtractionCostIdr + $wasteStrippingCostIdr;

            $pit->target_production_ton = (int) round($oreTonnage);
            $pit->save();

            $this->audit(
                action: 'mining.plan.block_extracted',
                auditable: $pit,
                oldValues: null,
                newValues: [
                    'pit_code' => $pit->pit_code,
                    'ore_tonnage' => $oreTonnage,
                    'waste_overburden_bcm' => $wasteOverburdenBcm,
                    'stripping_ratio' => $strippingRatio,
                    'is_economically_viable' => $isEconomicallyViable,
                    'estimated_cost_idr' => $totalEstimatedCostIdr,
                ],
                correlationId: "min_plan_{$pit->pit_code}_".date('YmdHis'),
                impactType: 'mine_planning_sr'
            );

            return [
                'pit' => $pit,
                'ore_tonnage' => $oreTonnage,
                'waste_overburden_bcm' => $wasteOverburdenBcm,
                'stripping_ratio' => $strippingRatio,
                'is_economically_viable' => $isEconomicallyViable,
                'estimated_cost_idr' => $totalEstimatedCostIdr,
            ];
        });
    }
}
