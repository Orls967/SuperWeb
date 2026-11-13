<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Domain\Models\SopEvent;
use Modules\Core\Contracts\ClockInterface;

final class StartSopCycleAction
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    public function execute(
        string $period,
        string $scenarioType,
        int $demandPlanQty,
        int $supplyPlanQty,
        int $capacityUtilizationBps,
        int $grossMarginEstIdr,
        int $cashImpactEstIdr,
        ?int $actorUserId = null,
    ): SopCycle {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new InvalidArgumentException('INVALID_PERIOD: Format periode harus YYYY-MM.');
        }

        if ($demandPlanQty < 0 || $supplyPlanQty < 0) {
            throw new InvalidArgumentException('NEGATIVE_PLAN_QTY: Rencana demand/supply tidak boleh negatif.');
        }

        $allowedScenarios = ['conservative', 'moderate', 'aggressive'];
        $normalizedScenario = strtolower($scenarioType);
        if (! in_array($normalizedScenario, $allowedScenarios, true)) {
            throw new InvalidArgumentException('INVALID_SCENARIO_TYPE: Skenario harus conservative, moderate, atau aggressive.');
        }

        return DB::transaction(function () use (
            $period,
            $normalizedScenario,
            $demandPlanQty,
            $supplyPlanQty,
            $capacityUtilizationBps,
            $grossMarginEstIdr,
            $cashImpactEstIdr,
            $actorUserId
        ): SopCycle {
            $suffix = hash('crc32b', $period.'|'.$normalizedScenario.'|'.(string) SopCycle::count());
            $cycle = SopCycle::create([
                'cycle_code' => 'SOP-'.str_replace('-', '', $period).'-'.strtoupper(substr($suffix, 0, 4)),
                'period' => $period,
                'status' => 'draft',
                'scenario_type' => $normalizedScenario,
                'demand_plan_qty' => $demandPlanQty,
                'supply_plan_qty' => $supplyPlanQty,
                'capacity_utilization_bps' => max(0, min(20_000, $capacityUtilizationBps)),
                'gross_margin_est_idr' => $grossMarginEstIdr,
                'cash_impact_est_idr' => $cashImpactEstIdr,
            ]);

            SopEvent::create([
                'sop_cycle_id' => $cycle->id,
                'from_status' => 'none',
                'to_status' => 'draft',
                'actor_user_id' => $actorUserId,
                'notes' => 'Inisiasi siklus S&OP',
                'created_at' => $this->clock->now(),
            ]);

            return $cycle;
        });
    }
}
