<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Modules\Shared\Application\BaseAction;

class CentralKitchenYieldVarianceAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        $batchNumber = (string) ($params['batch_number'] ?? 'CK-BATCH-'.uniqid());
        $expectedUnits = (int) $params['expected_yield_units'];
        $actualUnits = (int) $params['actual_yield_units'];
        $stdCostPerUnit = (int) $params['standard_cost_per_unit_idr'];

        if ($expectedUnits <= 0) {
            throw new \InvalidArgumentException('Expected yield units harus lebih besar dari 0.');
        }

        $varianceUnits = $actualUnits - $expectedUnits;
        $varianceCostIdr = $varianceUnits * $stdCostPerUnit;
        $yieldPercent = round(($actualUnits / $expectedUnits) * 100, 2);

        $classification = $varianceUnits >= 0 ? 'favorable' : 'unfavorable';

        $result = [
            'batch_number' => $batchNumber,
            'recipe_id' => $params['recipe_id'] ?? null,
            'expected_yield_units' => $expectedUnits,
            'actual_yield_units' => $actualUnits,
            'yield_percentage' => $yieldPercent,
            'variance_units' => $varianceUnits,
            'variance_cost_idr' => $varianceCostIdr,
            'classification' => $classification,
        ];

        $this->audit(
            action: 'central_kitchen_yield_variance_calculated',
            newValues: [
                'batch' => $batchNumber,
                'yield_pct' => $yieldPercent,
                'variance_cost' => $varianceCostIdr,
            ],
            impactType: 'kitchen_yield_control',
        );

        return $result;
    }
}
