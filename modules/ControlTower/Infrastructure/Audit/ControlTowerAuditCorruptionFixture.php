<?php

declare(strict_types=1);

namespace Modules\ControlTower\Infrastructure\Audit;

use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\OrderPromise;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\Core\Contracts\ClockInterface;

final class ControlTowerAuditCorruptionFixture
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    public function injectAtpOverPromise(): void
    {
        EchelonStock::query()->updateOrCreate(
            ['item_code' => 'SABOTAGE-CT-01', 'echelon_node' => 'DC'],
            [
                'item_name' => 'Sabotage Item',
                'on_hand_qty' => 5,
                'in_transit_qty' => 0,
                'reserved_qty' => 0,
                'safety_stock_qty' => 1,
                'abc_class' => 'A',
                'xyz_class' => 'X',
            ],
        );

        OrderPromise::query()->create([
            'promise_code' => 'PRM-SABOTAGE-001',
            'order_reference' => 'SO-SABOTAGE-1',
            'item_code' => 'SABOTAGE-CT-01',
            'requested_qty' => 50,
            'atp_confirmed_qty' => 50,
            'ctp_manufacturing_qty' => 0,
            'promised_delivery_date' => $this->clock->now()->addDays(2)->toDateString(),
            'promise_status' => 'confirmed',
        ]);
    }

    public function injectInvalidSopState(): void
    {
        SopCycle::query()->create([
            'cycle_code' => 'SOP-SABOTAGE-99',
            'period' => '2026-11',
            'status' => 'corrupted_unknown_state',
            'scenario_type' => 'moderate',
            'demand_plan_qty' => 100,
            'supply_plan_qty' => 100,
            'capacity_utilization_bps' => 8000,
            'gross_margin_est_idr' => 10_000_000,
            'cash_impact_est_idr' => 5_000_000,
        ]);
    }
}
