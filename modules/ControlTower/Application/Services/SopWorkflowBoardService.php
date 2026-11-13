<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Application\Actions\StartSopCycleAction;
use Modules\ControlTower\Application\Actions\TransitionSopCycleAction;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Domain\Services\SopConsensusComparator;
use Modules\ControlTower\Domain\Services\SopFinancialBalancer;
use Modules\ControlTower\Domain\ValueObjects\SopScenarioProjection;

final class SopWorkflowBoardService
{
    public function __construct(
        private readonly StartSopCycleAction $startAction,
        private readonly TransitionSopCycleAction $transitionAction,
        private readonly SopFinancialBalancer $balancer,
        private readonly SopConsensusComparator $comparator,
    ) {}

    public function initiateWithFinancials(
        string $period,
        string $scenarioType,
        int $demandQty,
        int $supplyQty,
        int $avgPriceIdr,
        int $unitCostIdr,
        int $holdingCostIdr,
        int $capacityUtilizationBps,
        ?int $actorUserId = null,
    ): SopCycle {
        $fin = $this->balancer->balance($demandQty, $supplyQty, $avgPriceIdr, $unitCostIdr, $holdingCostIdr);

        return $this->startAction->execute(
            period: $period,
            scenarioType: $scenarioType,
            demandPlanQty: $demandQty,
            supplyPlanQty: $supplyQty,
            capacityUtilizationBps: $capacityUtilizationBps,
            grossMarginEstIdr: $fin['gross_margin_idr'],
            cashImpactEstIdr: $fin['working_capital_delta_idr'],
            actorUserId: $actorUserId,
        );
    }

    public function advance(SopCycle $cycle, string $toStatus, ?int $actorUserId = null, ?string $notes = null): SopCycle
    {
        return $this->transitionAction->execute($cycle, $toStatus, $actorUserId, $notes);
    }

    /**
     * @return array{recommended_scenario: string, max_margin_idr: int, balanced_count: int}
     */
    public function comparePeriodScenarios(string $period): array
    {
        $projections = SopCycle::query()
            ->where('period', $period)
            ->get()
            ->map(static fn (SopCycle $c): SopScenarioProjection => new SopScenarioProjection(
                scenarioType: $c->scenario_type,
                demandPlanQty: (int) $c->demand_plan_qty,
                supplyPlanQty: (int) $c->supply_plan_qty,
                capacityUtilizationBps: (int) $c->capacity_utilization_bps,
                grossMarginEstIdr: (int) $c->gross_margin_est_idr,
                cashImpactEstIdr: (int) $c->cash_impact_est_idr,
            ))
            ->all();

        return $this->comparator->compare($projections);
    }
}
