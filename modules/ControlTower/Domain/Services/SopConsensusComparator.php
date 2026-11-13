<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

use Modules\ControlTower\Domain\ValueObjects\SopScenarioProjection;

final class SopConsensusComparator
{
    /**
     * @param  list<SopScenarioProjection>  $scenarios
     * @return array{
     *     recommended_scenario: string,
     *     max_margin_idr: int,
     *     balanced_count: int
     * }
     */
    public function compare(array $scenarios): array
    {
        $best = null;
        $balanced = 0;

        foreach ($scenarios as $scenario) {
            if ($scenario->supplyGapQty() >= 0 && $scenario->capacityUtilizationBps <= 9500) {
                $balanced++;
            }
            if ($best === null || $scenario->grossMarginEstIdr > $best->grossMarginEstIdr) {
                $best = $scenario;
            }
        }

        return [
            'recommended_scenario' => $best?->scenarioType ?? 'moderate',
            'max_margin_idr' => $best?->grossMarginEstIdr ?? 0,
            'balanced_count' => $balanced,
        ];
    }
}
